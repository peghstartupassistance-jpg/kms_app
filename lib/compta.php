<?php
/**
 * Librairie de Comptabilité Générale
 * Fonctions pour la génération et gestion des écritures comptables
 */

// ===== FONCTIONS UTILITAIRES =====

/**
 * Récupère l'exercice comptable actif (non clôturé)
 */
function compta_get_exercice_actif(PDO $pdo): ?array {
    $stmt = $pdo->prepare("
        SELECT * FROM compta_exercices 
        WHERE est_clos = 0 
        ORDER BY annee DESC 
        LIMIT 1
    ");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Génère le numéro de pièce suivant pour un journal donné
 */
function compta_generer_numero_piece(PDO $pdo, int $journal_id, int $exercice_id): string {
    $stmt = $pdo->prepare("
        SELECT j.code, COUNT(*) as nb_pieces 
        FROM compta_pieces cp 
        JOIN compta_journaux j ON j.id = cp.journal_id 
        WHERE cp.journal_id = ? AND cp.exercice_id = ? 
        GROUP BY j.code
    ");
    $stmt->execute([$journal_id, $exercice_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $journal_code = $result['code'] ?? 'XX';
    $numero = str_pad(($result['nb_pieces'] ?? 0) + 1, 5, '0', STR_PAD_LEFT);
    
    return "{$journal_code}-" . date('Y') . "-{$numero}";
}

/**
 * Crée une pièce comptable
 */
function compta_creer_piece(
    PDO $pdo,
    int $exercice_id,
    int $journal_id,
    string $date_piece,
    ?string $reference_type = null,
    ?int $reference_id = null,
    ?int $tiers_client_id = null,
    ?int $tiers_fournisseur_id = null,
    ?string $observations = null
): int {
    $numero_piece = compta_generer_numero_piece($pdo, $journal_id, $exercice_id);
    
    $stmt = $pdo->prepare("
        INSERT INTO compta_pieces 
        (exercice_id, journal_id, numero_piece, date_piece, reference_type, 
         reference_id, tiers_client_id, tiers_fournisseur_id, observations)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $exercice_id, $journal_id, $numero_piece, $date_piece,
        $reference_type, $reference_id, $tiers_client_id, $tiers_fournisseur_id,
        $observations
    ]);
    
    return (int)$pdo->lastInsertId();
}

/**
 * Ajoute une ligne d'écriture à une pièce
 */
function compta_ajouter_ecriture(
    PDO $pdo,
    int $piece_id,
    int $compte_id,
    float $montant_debit = 0,
    float $montant_credit = 0,
    ?string $libelle = null,
    ?int $tiers_client_id = null,
    ?int $tiers_fournisseur_id = null,
    ?int $centre_analytique_id = null,
    int $ordre = 1
): int {
    $stmt = $pdo->prepare("
        INSERT INTO compta_ecritures 
        (piece_id, compte_id, libelle_ecriture, debit, credit, 
         tiers_client_id, tiers_fournisseur_id, centre_analytique_id, ordre_ligne)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $piece_id, $compte_id, $libelle, 
        $montant_debit, $montant_credit,
        $tiers_client_id, $tiers_fournisseur_id, $centre_analytique_id,
        $ordre
    ]);
    
    return (int)$pdo->lastInsertId();
}

/**
 * Récupère un mapping d'opération
 */
function compta_get_mapping(PDO $pdo, string $source_type, string $code_operation): ?array {
    $stmt = $pdo->prepare("
        SELECT m.*, j.code as journal_code, j.id as journal_id,
               cc.numero_compte as compte_credit_numero,
               cd.numero_compte as compte_debit_numero
        FROM compta_mapping_operations m
        JOIN compta_journaux j ON j.id = m.journal_id
        LEFT JOIN compta_comptes cc ON cc.id = m.compte_credit_id
        LEFT JOIN compta_comptes cd ON cd.id = m.compte_debit_id
        WHERE m.source_type = ? AND m.code_operation = ? AND m.actif = 1
        LIMIT 1
    ");
    $stmt->execute([$source_type, $code_operation]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// ===== GÉNÉRATION D'ÉCRITURES PAR ÉVÉNEMENT =====

/**
 * Crée les écritures pour une VENTE
 * Événement: lorsqu'une vente passe à statut = 'LIVREE'
 */
function compta_creer_ecritures_vente(PDO $pdo, int $vente_id): bool {
    try {
        // Récupérer la vente
        $stmt = $pdo->prepare("
            SELECT v.*, c.id as client_id FROM ventes v
            LEFT JOIN clients c ON c.id = v.client_id
            WHERE v.id = ?
        ");
        $stmt->execute([$vente_id]);
        $vente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$vente) {
            throw new Exception("Vente #$vente_id non trouvée");
        }
        
        // Récupérer le mapping
        $mapping = compta_get_mapping($pdo, 'VENTE', 'VENTE_PRODUITS');
        if (!$mapping) {
            throw new Exception("Mapping VENTE/VENTE_PRODUITS non configuré");
        }
        
        // Récupérer l'exercice actif
        $exercice = compta_get_exercice_actif($pdo);
        if (!$exercice) {
            throw new Exception("Aucun exercice comptable actif");
        }
        
        // Créer la pièce comptable
        $piece_id = compta_creer_piece(
            $pdo,
            $exercice['id'],
            $mapping['journal_id'],
            $vente['date_vente'] ?? date('Y-m-d'),
            'VENTE',
            $vente_id,
            $vente['client_id'],
            null,
            "Facture vente n° {$vente['numero']}"
        );
        
        $montant_ttc = (float)($vente['montant_total_ttc'] ?? 0);
        
        // Créer les écritures
        // Débit: compte client (411xxx)
        compta_ajouter_ecriture(
            $pdo, $piece_id, $mapping['compte_debit_id'],
            $montant_ttc, 0,
            "Client facture {$vente['numero']}",
            $vente['client_id'], null, null, 1
        );
        
        // Crédit: compte de ventes (707xxx)
        compta_ajouter_ecriture(
            $pdo, $piece_id, $mapping['compte_credit_id'],
            0, $montant_ttc,
            "Vente produits facture {$vente['numero']}",
            null, null, null, 2
        );
        
        // Enregistrer la trace
        $stmt = $pdo->prepare("
            INSERT INTO compta_operations_trace (source_type, source_id, piece_id, status)
            VALUES (?, ?, ?, 'success')
            ON DUPLICATE KEY UPDATE piece_id = ?, status = 'success'
        ");
        $stmt->execute(['VENTE', $vente_id, $piece_id, $piece_id]);
        
        return true;
        
    } catch (Throwable $e) {
        error_log("Erreur création écritures vente #$vente_id: " . $e->getMessage());
        
        $stmt = $pdo->prepare("
            INSERT INTO compta_operations_trace (source_type, source_id, status, messages)
            VALUES (?, ?, 'error', ?)
            ON DUPLICATE KEY UPDATE status = 'error', messages = ?
        ");
        $stmt->execute(['VENTE', $vente_id, $e->getMessage(), $e->getMessage()]);
        
        return false;
    }
}

/**
 * Crée les écritures pour un ACHAT
 * Événement: lorsqu'un achat est validé
 */
function compta_creer_ecritures_achat(PDO $pdo, int $achat_id): bool {
    try {
        // Récupérer l'achat
        $stmt = $pdo->prepare("
            SELECT * FROM achats WHERE id = ?
        ");
        $stmt->execute([$achat_id]);
        $achat = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$achat) {
            throw new Exception("Achat #$achat_id non trouvé");
        }
        
        // Récupérer le mapping
        $mapping = compta_get_mapping($pdo, 'ACHAT', 'ACHAT_STOCK');
        if (!$mapping) {
            throw new Exception("Mapping ACHAT/ACHAT_STOCK non configuré");
        }
        
        // Récupérer l'exercice actif
        $exercice = compta_get_exercice_actif($pdo);
        if (!$exercice) {
            throw new Exception("Aucun exercice comptable actif");
        }
        
        // Créer la pièce comptable
        $piece_id = compta_creer_piece(
            $pdo,
            $exercice['id'],
            $mapping['journal_id'],
            $achat['date_achat'] ?? date('Y-m-d'),
            'ACHAT',
            $achat_id,
            null,
            null,
            "Facture achat n° {$achat['numero']}"
        );
        
        $montant_ttc = (float)($achat['montant_total_ttc'] ?? 0);
        
        // Créer les écritures
        // Débit: compte de charge ou stock (60/30/31)
        compta_ajouter_ecriture(
            $pdo, $piece_id, $mapping['compte_debit_id'],
            $montant_ttc, 0,
            "Achat articles facture {$achat['numero']}",
            null, null, null, 1
        );
        
        // Crédit: compte fournisseur (401xxx)
        compta_ajouter_ecriture(
            $pdo, $piece_id, $mapping['compte_credit_id'],
            0, $montant_ttc,
            "Fournisseur facture {$achat['numero']}",
            null, null, null, 2
        );
        
        // Enregistrer la trace
        $stmt = $pdo->prepare("
            INSERT INTO compta_operations_trace (source_type, source_id, piece_id, status)
            VALUES (?, ?, ?, 'success')
            ON DUPLICATE KEY UPDATE piece_id = ?, status = 'success'
        ");
        $stmt->execute(['ACHAT', $achat_id, $piece_id, $piece_id]);
        
        return true;
        
    } catch (Throwable $e) {
        error_log("Erreur création écritures achat #$achat_id: " . $e->getMessage());
        
        $stmt = $pdo->prepare("
            INSERT INTO compta_operations_trace (source_type, source_id, status, messages)
            VALUES (?, ?, 'error', ?)
            ON DUPLICATE KEY UPDATE status = 'error', messages = ?
        ");
        $stmt->execute(['ACHAT', $achat_id, $e->getMessage(), $e->getMessage()]);
        
        return false;
    }
}

/**
 * Crée les écritures pour une opération de CAISSE
 * Gère : paiement vente, réglement fournisseur, opérations divers
 */
function compta_creer_ecritures_caisse(PDO $pdo, int $journal_caisse_id): bool {
    try {
        // Récupérer l'opération de caisse
        $stmt = $pdo->prepare("
            SELECT jc.*, 
                   v.client_id as vente_client_id,
                   f.id as fournisseur_id
            FROM journal_caisse jc
            LEFT JOIN ventes v ON v.id = jc.vente_id
            LEFT JOIN fournisseurs f ON f.id = jc.fournisseur_id
            WHERE jc.id = ?
        ");
        $stmt->execute([$journal_caisse_id]);
        $caisse = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$caisse || (int)($caisse['est_annule'] ?? 0) === 1) {
            return false; // Opération annulée ou non trouvée
        }
        
        // Récupérer l'exercice actif
        $exercice = compta_get_exercice_actif($pdo);
        if (!$exercice) {
            throw new Exception("Aucun exercice comptable actif");
        }
        
        $montant = (float)($caisse['montant'] ?? 0);
        
        // Cas 1: RECETTE + VENTE (paiement facture vente)
        if ($caisse['sens'] === 'RECETTE' && !empty($caisse['vente_id'])) {
            $mapping = compta_get_mapping($pdo, 'CAISSE_RECETTE', 'PAIEMENT_VENTE');
            if (!$mapping) return false;
            
            $piece_id = compta_creer_piece(
                $pdo, $exercice['id'], $mapping['journal_id'],
                $caisse['date_operation'] ?? date('Y-m-d'),
                'CAISSE_RECETTE', $caisse['id'],
                $caisse['vente_client_id'],
                null,
                "Paiement vente #{$caisse['vente_id']}"
            );
            
            // Débit: trésorerie (57x ou 512)
            compta_ajouter_ecriture(
                $pdo, $piece_id, $mapping['compte_debit_id'],
                $montant, 0,
                "Paiement client vente #{$caisse['vente_id']}",
                $caisse['vente_client_id'], null, null, 1
            );
            
            // Crédit: compte client (411xxx)
            compta_ajouter_ecriture(
                $pdo, $piece_id, $mapping['compte_credit_id'],
                0, $montant,
                "Encaissement client",
                $caisse['vente_client_id'], null, null, 2
            );
        }
        
        // Cas 2: DÉPENSE + FOURNISSEUR (réglement fournisseur)
        elseif ($caisse['sens'] === 'DEPENSE' && !empty($caisse['fournisseur_id'])) {
            $mapping = compta_get_mapping($pdo, 'CAISSE_DEPENSE', 'REGLEMENT_FOURNISSEUR');
            if (!$mapping) return false;
            
            $piece_id = compta_creer_piece(
                $pdo, $exercice['id'], $mapping['journal_id'],
                $caisse['date_operation'] ?? date('Y-m-d'),
                'CAISSE_DEPENSE', $caisse['id'],
                null, $caisse['fournisseur_id'],
                "Réglement fournisseur #{$caisse['fournisseur_id']}"
            );
            
            // Débit: compte fournisseur (401xxx)
            compta_ajouter_ecriture(
                $pdo, $piece_id, $mapping['compte_debit_id'],
                $montant, 0,
                "Réglement fournisseur",
                null, $caisse['fournisseur_id'], null, 1
            );
            
            // Crédit: trésorerie
            compta_ajouter_ecriture(
                $pdo, $piece_id, $mapping['compte_credit_id'],
                0, $montant,
                "Décaissement",
                null, $caisse['fournisseur_id'], null, 2
            );
        }
        
        // Cas 3: RECETTE + INSCRIPTION FORMATION
        elseif ($caisse['sens'] === 'RECETTE' && !empty($caisse['inscription_formation_id'])) {
            $mapping = compta_get_mapping($pdo, 'INSCRIPTION_FORMATION', 'PAIEMENT_FORMATION');
            if (!$mapping) return false;
            
            $piece_id = compta_creer_piece(
                $pdo, $exercice['id'], $mapping['journal_id'],
                $caisse['date_operation'] ?? date('Y-m-d'),
                'INSCRIPTION_FORMATION', $caisse['inscription_formation_id'],
                null, null,
                "Paiement inscription formation #{$caisse['inscription_formation_id']}"
            );
            
            // Écritures selon le mapping
            compta_ajouter_ecriture(
                $pdo, $piece_id, $mapping['compte_debit_id'],
                $montant, 0,
                "Recette formation",
                null, null, null, 1
            );
            
            compta_ajouter_ecriture(
                $pdo, $piece_id, $mapping['compte_credit_id'],
                0, $montant,
                "Paiement inscription",
                null, null, null, 2
            );
        }
        
        // Cas 4: RECETTE + RÉSERVATION HÔTEL
        elseif ($caisse['sens'] === 'RECETTE' && !empty($caisse['reservation_id'])) {
            $mapping = compta_get_mapping($pdo, 'RESERVATION_HOTEL', 'PAIEMENT_RESERVATION');
            if (!$mapping) return false;
            
            $piece_id = compta_creer_piece(
                $pdo, $exercice['id'], $mapping['journal_id'],
                $caisse['date_operation'] ?? date('Y-m-d'),
                'RESERVATION_HOTEL', $caisse['reservation_id'],
                null, null,
                "Paiement réservation hôtel #{$caisse['reservation_id']}"
            );
            
            compta_ajouter_ecriture(
                $pdo, $piece_id, $mapping['compte_debit_id'],
                $montant, 0,
                "Recette hôtel",
                null, null, null, 1
            );
            
            compta_ajouter_ecriture(
                $pdo, $piece_id, $mapping['compte_credit_id'],
                0, $montant,
                "Paiement réservation",
                null, null, null, 2
            );
        }
        
        return true;
        
    } catch (Throwable $e) {
        error_log("Erreur création écritures caisse #$journal_caisse_id: " . $e->getMessage());
        return false;
    }
}

// ===== FONCTIONS DE CONSULTATION =====

/**
 * Récupère les pièces d'un journal avec filtres
 */
function compta_get_pieces_journal(
    PDO $pdo,
    int $journal_id,
    ?int $exercice_id = null,
    ?string $date_debut = null,
    ?string $date_fin = null,
    int $limit = 100
): array {
    $params = [$journal_id];
    $where = "WHERE cp.journal_id = ?";
    
    if ($exercice_id) {
        $where .= " AND cp.exercice_id = ?";
        $params[] = $exercice_id;
    }
    if ($date_debut) {
        $where .= " AND cp.date_piece >= ?";
        $params[] = $date_debut;
    }
    if ($date_fin) {
        $where .= " AND cp.date_piece <= ?";
        $params[] = $date_fin;
    }
    
    $sql = "
        SELECT cp.*, c.nom as client_nom, f.nom as fournisseur_nom,
               COUNT(ce.id) as nb_ecritures
        FROM compta_pieces cp
        LEFT JOIN clients c ON c.id = cp.tiers_client_id
        LEFT JOIN fournisseurs f ON f.id = cp.tiers_fournisseur_id
        LEFT JOIN compta_ecritures ce ON ce.piece_id = cp.id
        $where
        GROUP BY cp.id
        ORDER BY cp.date_piece DESC
        LIMIT ?
    ";
    $params[] = $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les écritures d'une pièce
 */
function compta_get_ecritures_piece(PDO $pdo, int $piece_id): array {
    $stmt = $pdo->prepare("
        SELECT ce.*, cc.numero_compte, cc.libelle
        FROM compta_ecritures ce
        JOIN compta_comptes cc ON cc.id = ce.compte_id
        WHERE ce.piece_id = ?
        ORDER BY ce.ordre_ligne
    ");
    $stmt->execute([$piece_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les mouvements d'un compte (grand livre)
 */
function compta_get_grand_livre_compte(PDO $pdo, int $compte_id, ?int $exercice_id = null): array {
    $params = [$compte_id];
    $where = "WHERE ce.compte_id = ?";
    
    if ($exercice_id) {
        $where .= " AND cp.exercice_id = ?";
        $params[] = $exercice_id;
    }
    
    $stmt = $pdo->prepare("
        SELECT ce.*, cp.numero_piece, cp.date_piece,
               cc.numero_compte, cc.libelle as compte_libelle
        FROM compta_ecritures ce
        JOIN compta_pieces cp ON cp.id = ce.piece_id
        JOIN compta_comptes cc ON cc.id = ce.compte_id
        $where
        ORDER BY cp.date_piece, ce.id
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Calcule la balance générale
 */
function compta_get_balance(PDO $pdo, ?int $exercice_id = null): array {
    $params = [];
    $where = "WHERE cp.est_validee = 1";
    
    if ($exercice_id) {
        $where .= " AND cp.exercice_id = ?";
        $params[] = $exercice_id;
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            cc.id, cc.numero_compte, cc.libelle, cc.classe,
            SUM(COALESCE(ce.debit, 0)) as total_debit,
            SUM(COALESCE(ce.credit, 0)) as total_credit,
            SUM(COALESCE(ce.debit, 0)) - SUM(COALESCE(ce.credit, 0)) as solde
        FROM compta_comptes cc
        LEFT JOIN compta_ecritures ce ON ce.compte_id = cc.id
        LEFT JOIN compta_pieces cp ON cp.id = ce.piece_id
        $where
        GROUP BY cc.id, cc.numero_compte, cc.libelle, cc.classe
        HAVING SUM(COALESCE(ce.debit, 0)) != 0 OR SUM(COALESCE(ce.credit, 0)) != 0
        ORDER BY cc.numero_compte
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
