<?php
// lib/caisse.php
/**
 * Petite librairie de journal de caisse minimaliste.
 * La table `caisse_journal` est créée si elle n'existe pas.
 *
 * champs:
 *  - id INT PK
 *  - date_ecriture DATETIME
 *  - sens ENUM('ENTREE','SORTIE')
 *  - montant DECIMAL(15,2)
 *  - source_type VARCHAR(50)
 *  - source_id INT NULL
 *  - commentaire TEXT
 *  - utilisateur_id INT NULL
 */

function caisse_ensure_table(PDO $pdo): void
{
    $sql = "CREATE TABLE IF NOT EXISTS caisse_journal (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date_ecriture DATETIME NOT NULL,
        sens ENUM('ENTREE','SORTIE') NOT NULL,
        montant DECIMAL(15,2) NOT NULL,
        source_type VARCHAR(50) DEFAULT NULL,
        source_id INT DEFAULT NULL,
        commentaire TEXT DEFAULT NULL,
        utilisateur_id INT DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);
}

/**
 * Enregistre une écriture dans la caisse.
 * sens: 'ENTREE' pour encaissement, 'SORTIE' pour décaissement.
 */
function caisse_enregistrer_ecriture(PDO $pdo, string $sens, float $montant, string $source_type = null, ?int $source_id = null, ?string $commentaire = null, ?int $utilisateur_id = null, ?string $date = null): int
{
    if (!in_array($sens, ['ENTREE', 'SORTIE'], true)) {
        throw new InvalidArgumentException('Sens invalide pour écriture caisse');
    }

    caisse_ensure_table($pdo);

    $sql = "INSERT INTO caisse_journal (date_ecriture, sens, montant, source_type, source_id, commentaire, utilisateur_id)
            VALUES (:date_ecriture, :sens, :montant, :source_type, :source_id, :commentaire, :utilisateur_id)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':date_ecriture' => $date ?? date('Y-m-d H:i:s'),
        ':sens' => $sens,
        ':montant' => $montant,
        ':source_type' => $source_type,
        ':source_id' => $source_id,
        ':commentaire' => $commentaire,
        ':utilisateur_id' => $utilisateur_id,
    ]);

    return (int)$pdo->lastInsertId();
}

/**
 * Récupère les écritures récentes (utile pour tests)
 */
function caisse_get_recent(PDO $pdo, int $limit = 50): array
{
    $stmt = $pdo->prepare('SELECT * FROM caisse_journal ORDER BY id DESC LIMIT :l');
    $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
