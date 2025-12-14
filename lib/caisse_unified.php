<?php
// lib/caisse.php - UNIFIED VERSION using journal_caisse
/**
 * Librairie de journal de caisse/trésorerie.
 * La table `journal_caisse` est l'unique source de vérité pour les opérations de caisse.
 *
 * Champs:
 *  - id INT PK
 *  - date_operation DATETIME (quand l'opération a eu lieu)
 *  - date_ecriture DATETIME (pour compta)
 *  - nature_operation VARCHAR(100)
 *  - type_operation ENUM (VENTE, ACHAT, ENCAISSEMENT, DECAISSEMENT, AUTRE)
 *  - sens ENUM('RECETTE','DEPENSE') pour trésorerie simple (ENTREE=RECETTE, SORTIE=DEPENSE)
 *  - montant DECIMAL(15,2)
 *  - vente_id INT NULL (lien ventes)
 *  - achat_id INT NULL (lien achats)
 *  - source_type VARCHAR(50)
 *  - source_id INT NULL
 *  - commentaire TEXT
 *  - utilisateur_id INT NULL
 *  - est_annule TINYINT
 *  - piece_id INT NULL (lien compta)
 */

function caisse_ensure_table(PDO $pdo): void
{
    $sql = "CREATE TABLE IF NOT EXISTS journal_caisse (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date_operation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        date_ecriture DATETIME DEFAULT NULL,
        nature_operation VARCHAR(100) DEFAULT NULL,
        type_operation ENUM('VENTE','ACHAT','ENCAISSEMENT','DECAISSEMENT','AUTRE') DEFAULT 'AUTRE',
        sens ENUM('RECETTE','DEPENSE') NOT NULL,
        montant DECIMAL(15,2) NOT NULL,
        vente_id INT DEFAULT NULL,
        achat_id INT DEFAULT NULL,
        source_type VARCHAR(50) DEFAULT NULL,
        source_id INT DEFAULT NULL,
        commentaire TEXT DEFAULT NULL,
        utilisateur_id INT DEFAULT NULL,
        est_annule TINYINT DEFAULT 0,
        piece_id INT DEFAULT NULL,
        INDEX idx_date_operation (date_operation),
        INDEX idx_vente_id (vente_id),
        INDEX idx_achat_id (achat_id),
        INDEX idx_sens (sens),
        CONSTRAINT fk_journal_caisse_ventes FOREIGN KEY (vente_id) REFERENCES ventes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);
}

/**
 * Enregistre une écriture dans la caisse.
 * Mapping automatique:
 *  - sens 'ENTREE' → 'RECETTE'
 *  - sens 'SORTIE' → 'DEPENSE'
 *  - sens 'RECETTE'/'DEPENSE' → unchanged
 */
function caisse_enregistrer_ecriture(PDO $pdo, string $sens, float $montant, string $source_type = null, ?int $source_id = null, ?string $commentaire = null, ?int $utilisateur_id = null, ?string $date = null, ?int $vente_id = null, ?int $achat_id = null): int
{
    // Normaliser sens
    $sens_norm = strtoupper($sens);
    if ($sens_norm === 'ENTREE') {
        $sens_norm = 'RECETTE';
    } elseif ($sens_norm === 'SORTIE') {
        $sens_norm = 'DEPENSE';
    }

    if (!in_array($sens_norm, ['RECETTE', 'DEPENSE'], true)) {
        throw new InvalidArgumentException('Sens invalide pour écriture caisse');
    }

    caisse_ensure_table($pdo);

    $sql = "INSERT INTO journal_caisse 
            (date_operation, sens, montant, source_type, source_id, commentaire, utilisateur_id, vente_id, achat_id)
            VALUES (:date_operation, :sens, :montant, :source_type, :source_id, :commentaire, :utilisateur_id, :vente_id, :achat_id)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':date_operation' => $date ?? date('Y-m-d H:i:s'),
        ':sens' => $sens_norm,
        ':montant' => $montant,
        ':source_type' => $source_type,
        ':source_id' => $source_id,
        ':commentaire' => $commentaire,
        ':utilisateur_id' => $utilisateur_id,
        ':vente_id' => $vente_id,
        ':achat_id' => $achat_id,
    ]);

    return (int)$pdo->lastInsertId();
}

/**
 * Récupère les écritures récentes (utile pour tests)
 */
function caisse_get_recent(PDO $pdo, int $limit = 50): array
{
    caisse_ensure_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM journal_caisse ORDER BY id DESC LIMIT :l');
    $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtenir le solde caisse (RECETTES - DEPENSES non annulées)
 */
function caisse_get_solde(PDO $pdo, ?string $date_debut = null, ?string $date_fin = null): float
{
    caisse_ensure_table($pdo);
    
    $sql = "
        SELECT 
            COALESCE(SUM(CASE WHEN sens = 'RECETTE' THEN montant ELSE -montant END), 0) AS solde
        FROM journal_caisse
        WHERE est_annule = 0
    ";
    
    if ($date_debut) {
        $sql .= " AND date_operation >= :date_debut";
    }
    if ($date_fin) {
        $sql .= " AND date_operation <= :date_fin";
    }
    
    $stmt = $pdo->prepare($sql);
    if ($date_debut) $stmt->bindValue(':date_debut', $date_debut);
    if ($date_fin) $stmt->bindValue(':date_fin', $date_fin);
    
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return (float)($result['solde'] ?? 0);
}

/**
 * Annuler une écriture caisse (soft delete)
 */
function caisse_annuler_ecriture(PDO $pdo, int $id): bool
{
    caisse_ensure_table($pdo);
    
    $stmt = $pdo->prepare("UPDATE journal_caisse SET est_annule = 1 WHERE id = :id");
    return $stmt->execute([':id' => $id]);
}
