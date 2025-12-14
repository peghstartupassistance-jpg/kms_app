<?php
/**
 * API centralisée pour la gestion des litiges/retours avec synchronisation complète
 * stock + caisse + comptabilité
 * 
 * Principes :
 * 1. Chaque action de litige (création, résolution, remboursement, remplacement) 
 *    est une TRANSACTION englobant stock + caisse + compta
 * 2. Les mouvements de stock sont TOUJOURS via stock.php:stock_enregistrer_mouvement()
 * 3. Les flux de trésorerie sont TOUJOURS via caisse.php:caisse_enregistrer_operation()
 * 4. Les écritures comptables sont TOUJOURS via compta.php functions
 * 5. Chaque opération laisse une trace exploitable et vérifiable
 */

require_once __DIR__ . '/stock.php';
require_once __DIR__ . '/caisse.php';
require_once __DIR__ . '/compta.php';

function litiges_creer_avec_retour($pdo, $client_id, $produit_id, $vente_id, $type_probleme, 
                                   $motif_detaille, $responsable_id, $options = []) {
    $date_retour = $options['date_retour'] ?? date('Y-m-d');
    $quantite_retournee = $options['quantite_retournee'] ?? 0;
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO retours_litiges 
            (date_retour, client_id, produit_id, vente_id, motif, type_probleme, 
             responsable_suivi_id, statut_traitement)
            VALUES 
            (:date_retour, :client_id, :produit_id, :vente_id, :motif, :type_probleme, 
             :responsable_id, 'EN_COURS')
        ");
        $stmt->execute([
            'date_retour'    => $date_retour,
            'client_id'      => $client_id,
            'produit_id'     => $produit_id,
            'vente_id'       => $vente_id ?: null,
            'motif'          => $motif_detaille,
            'type_probleme'  => $type_probleme,
            'responsable_id' => $responsable_id,
        ]);
        
        $litige_id = (int)$pdo->lastInsertId();
        
        if ($quantite_retournee > 0) {
            stock_enregistrer_mouvement(
                $pdo,
                [
                    'produit_id'      => $produit_id,
                    'type_mouvement'  => 'ENTREE',
                    'quantite'        => $quantite_retournee,
                    'date_mouvement'  => $date_retour,
                    'commentaire'     => "Retour client - Litige #{$litige_id} - {$motif_detaille}",
                    'source_type'     => 'LITIGE',
                    'source_id'       => $litige_id,
                    'utilisateur_id'  => $responsable_id,
                ]
            );
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'id'      => $litige_id,
            'message' => "Litige créé avec succès (ID: #{$litige_id})"
        ];
        
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function litiges_resoudre_avec_remboursement($pdo, $litige_id, $montant_rembourse, 
                                             $solution, $options = []) {
    $utilisateur_id = $options['utilisateur_id'] ?? 0;
    $date_resolution = $options['date_resolution'] ?? date('Y-m-d H:i:s');
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM retours_litiges WHERE id = :id");
        $stmt->execute(['id' => $litige_id]);
        $litige = $stmt->fetch();
        
        if (!$litige) {
            throw new Exception("Litige #{$litige_id} non trouvé");
        }
        
        $client_id = $litige['client_id'];
        $vente_id = $litige['vente_id'];
        
        caisse_enregistrer_operation(
            $pdo,
            'REMBOURSEMENT_CLIENT_LITIGE',
            $montant_rembourse,
            "Remboursement client litige #{$litige_id}",
            [
                'litige_id'      => $litige_id,
                'vente_id'       => $vente_id,
                'client_id'      => $client_id,
                'utilisateur_id' => $utilisateur_id,
                'date_operation' => $date_resolution,
            ]
        );
        
        $exercice = compta_get_exercice_actif($pdo);
        if (!$exercice) {
            throw new Exception("Aucun exercice comptable actif");
        }
        
        $numero_piece = "REMB-" . date('Y-m-d') . "-" . str_pad($litige_id, 5, '0', STR_PAD_LEFT);
        
        try {
            $piece_id = compta_creer_piece(
                $pdo,
                $exercice['id'],
                5, // journal_id pour remboursement
                date('Y-m-d', strtotime($date_resolution)),
                $numero_piece,
                "Remboursement client suite litige #{$litige_id}",
                null,
                'LITIGE',
                $litige_id,
                null
            );
            
            if (!$piece_id) {
                throw new Exception("Impossible de créer la pièce comptable");
            }
            
            // Compte client - débit (réduction de crédit client)
            compta_ajouter_ecriture(
                $pdo,
                $piece_id,
                411001,
                $montant_rembourse,
                0,
                "RRR Litige #{$litige_id}",
                null,
                'LITIGE'
            );
            
            // Compte caisse - crédit (sortie de caisse)
            compta_ajouter_ecriture(
                $pdo,
                $piece_id,
                512001,
                0,
                $montant_rembourse,
                "Remboursement client",
                null,
                'LITIGE'
            );
        } catch (Exception $e) {
            throw new Exception("Erreur création écriture comptable : " . $e->getMessage());
        }
        
        $stmt = $pdo->prepare("
            UPDATE retours_litiges
            SET statut_traitement = 'REMBOURSEMENT_EFFECTUE',
                solution = :solution,
                montant_rembourse = :montant,
                date_resolution = :date_resolution
            WHERE id = :id
        ");
        $stmt->execute([
            'solution'        => $solution,
            'montant'         => $montant_rembourse,
            'date_resolution' => $date_resolution,
            'id'              => $litige_id,
        ]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => "Remboursement enregistré : {$montant_rembourse} FCFA"
        ];
        
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function litiges_resoudre_avec_remplacement($pdo, $litige_id, $quantite_remplacement, 
                                            $solution, $options = []) {
    $utilisateur_id = $options['utilisateur_id'] ?? 0;
    $date_resolution = $options['date_resolution'] ?? date('Y-m-d H:i:s');
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM retours_litiges WHERE id = :id");
        $stmt->execute(['id' => $litige_id]);
        $litige = $stmt->fetch();
        
        if (!$litige) {
            throw new Exception("Litige #{$litige_id} non trouvé");
        }
        
        $produit_id = $litige['produit_id'];
        $vente_id = $litige['vente_id'];
        
        stock_enregistrer_mouvement(
            $pdo,
            [
                'produit_id'      => $produit_id,
                'type_mouvement'  => 'ENTREE',
                'quantite'        => $quantite_remplacement,
                'date_mouvement'  => date('Y-m-d', strtotime($date_resolution)),
                'commentaire'     => "Retour produit défectueux - Litige #{$litige_id} (remplacement)",
                'source_type'     => 'LITIGE',
                'source_id'       => $litige_id,
                'utilisateur_id'  => $utilisateur_id,
            ]
        );
        
        stock_enregistrer_mouvement(
            $pdo,
            [
                'produit_id'      => $produit_id,
                'type_mouvement'  => 'SORTIE',
                'quantite'        => $quantite_remplacement,
                'date_mouvement'  => date('Y-m-d', strtotime($date_resolution)),
                'commentaire'     => "Livraison remplacement - Litige #{$litige_id}",
                'source_type'     => 'LITIGE',
                'source_id'       => $litige_id,
                'utilisateur_id'  => $utilisateur_id,
            ]
        );
        
        $stmt = $pdo->prepare("
            UPDATE retours_litiges
            SET statut_traitement = 'REMPLACEMENT_EFFECTUE',
                solution = :solution,
                date_resolution = :date_resolution
            WHERE id = :id
        ");
        $stmt->execute([
            'solution'        => $solution,
            'date_resolution' => $date_resolution,
            'id'              => $litige_id,
        ]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => "Remplacement enregistré : {$quantite_remplacement} unités"
        ];
        
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function litiges_resoudre_avec_avoir($pdo, $litige_id, $montant_avoir, 
                                      $solution, $options = []) {
    $utilisateur_id = $options['utilisateur_id'] ?? 0;
    $date_resolution = $options['date_resolution'] ?? date('Y-m-d H:i:s');
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM retours_litiges WHERE id = :id");
        $stmt->execute(['id' => $litige_id]);
        $litige = $stmt->fetch();
        
        if (!$litige) {
            throw new Exception("Litige #{$litige_id} non trouvé");
        }
        
        $vente_id = $litige['vente_id'];
        
        $exercice = compta_get_exercice_actif($pdo);
        if (!$exercice) {
            throw new Exception("Aucun exercice comptable actif");
        }
        
        $numero_piece = "AVOIR-" . date('Y-m-d') . "-" . str_pad($litige_id, 5, '0', STR_PAD_LEFT);
        
        try {
            $piece_id = compta_creer_piece(
                $pdo,
                $exercice['id'],
                5, // journal_id pour avoir
                date('Y-m-d', strtotime($date_resolution)),
                $numero_piece,
                "Avoir/RRR accordé suite litige #{$litige_id}",
                null,
                'LITIGE',
                $litige_id,
                null
            );
            
            if (!$piece_id) {
                throw new Exception("Impossible de créer la pièce comptable");
            }
            
            // Compte client - débit (réduction de crédit client)
            compta_ajouter_ecriture(
                $pdo,
                $piece_id,
                411001,
                $montant_avoir,
                0,
                "Avoir client litige #{$litige_id}",
                null,
                'LITIGE'
            );
            
            // Compte RRR - crédit (RRR/rabais)
            compta_ajouter_ecriture(
                $pdo,
                $piece_id,
                701001,
                0,
                $montant_avoir,
                "RRR litige client",
                null,
                'LITIGE'
            );
        } catch (Exception $e) {
            throw new Exception("Erreur création écriture comptable : " . $e->getMessage());
        }
        
        $stmt = $pdo->prepare("
            UPDATE retours_litiges
            SET statut_traitement = 'RESOLU',
                solution = :solution,
                montant_avoir = :montant,
                date_resolution = :date_resolution
            WHERE id = :id
        ");
        $stmt->execute([
            'solution'        => $solution,
            'montant'         => $montant_avoir,
            'date_resolution' => $date_resolution,
            'id'              => $litige_id,
        ]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => "Avoir accordé : {$montant_avoir} FCFA"
        ];
        
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function litiges_abandonner($pdo, $litige_id, $raison_abandon, $utilisateur_id = 0) {
    try {
        $stmt = $pdo->prepare("
            UPDATE retours_litiges
            SET statut_traitement = 'ABANDONNE',
                solution = :raison
            WHERE id = :id
        ");
        $stmt->execute([
            'raison' => $raison_abandon,
            'id'     => $litige_id,
        ]);
        
        return [
            'success' => true,
            'message' => "Litige marqué comme abandonné"
        ];
        
    } catch (Throwable $e) {
        throw $e;
    }
}

function litiges_charger_complet($pdo, $litige_id) {
    $stmt = $pdo->prepare("
        SELECT rl.*,
               c.nom as client_nom,
               p.code_produit, p.designation,
               v.numero as vente_numero
        FROM retours_litiges rl
        LEFT JOIN clients c ON c.id = rl.client_id
        LEFT JOIN produits p ON p.id = rl.produit_id
        LEFT JOIN ventes v ON v.id = rl.vente_id
        WHERE rl.id = :id
    ");
    $stmt->execute(['id' => $litige_id]);
    $litige = $stmt->fetch();
    
    if (!$litige) {
        return null;
    }
    
    $stmt = $pdo->prepare("
        SELECT * FROM stocks_mouvements
        WHERE raison LIKE CONCAT('%Litige #', :id, '%')
        ORDER BY date_mouvement DESC
    ");
    $stmt->execute(['id' => $litige_id]);
    $mouvements_stock = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("
        SELECT * FROM journal_caisse
        WHERE libelle LIKE CONCAT('%litige #', :id, '%')
           OR type_operation = 'REMBOURSEMENT_CLIENT_LITIGE'
        ORDER BY date_operation DESC
    ");
    $stmt->execute(['id' => $litige_id]);
    $operations_caisse = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("
        SELECT ce.*, cp.numero_piece
        FROM compta_ecritures ce
        JOIN compta_pieces cp ON cp.id = ce.piece_id
        WHERE cp.libelle LIKE CONCAT('%litige #', :id, '%')
           OR cp.numero_piece LIKE 'REMB-%'
           OR cp.numero_piece LIKE 'AVOIR-%'
        ORDER BY ce.date_ecriture DESC
    ");
    $stmt->execute(['id' => $litige_id]);
    $ecritures_compta = $stmt->fetchAll();
    
    return [
        'litige' => $litige,
        'stock'  => $mouvements_stock,
        'caisse' => $operations_caisse,
        'compta' => $ecritures_compta,
    ];
}
