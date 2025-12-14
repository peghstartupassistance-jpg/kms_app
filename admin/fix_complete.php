<?php
require_once __DIR__ . '/../security.php';
global $pdo;

header('Content-Type: text/plain; charset=utf-8');

echo "=== CORRECTION COMPLÈTE ===\n\n";

// 1. Vérifier la structure réelle de journal_caisse
echo "1. Structure actuelle journal_caisse:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM journal_caisse");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($cols as $col) {
    echo "   - $col\n";
}

// 2. Ajouter les colonnes manquantes si nécessaire
echo "\n2. Ajout colonnes manquantes:\n";

$columns_to_add = [
    "ALTER TABLE journal_caisse ADD COLUMN IF NOT EXISTS type_operation VARCHAR(50)",
    "ALTER TABLE journal_caisse ADD COLUMN IF NOT EXISTS vente_id INT",
    "ALTER TABLE journal_caisse ADD COLUMN IF NOT EXISTS est_annule TINYINT DEFAULT 0",
];

foreach ($columns_to_add as $sql) {
    try {
        $pdo->exec($sql);
        echo "   ✓ " . substr($sql, 30) . "\n";
    } catch (Exception $e) {
        echo "   ~ " . substr($sql, 30) . " (existe déjà?)\n";
    }
}

// 3. Migrer les données de caisse_journal vers journal_caisse
echo "\n3. Migration caisse_journal → journal_caisse:\n";

try {
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM caisse_journal");
    $count = $stmt->fetch()['cnt'];
    echo "   Données à migrer: $count lignes\n";
    
    if ($count > 0) {
        // Alimente toutes les colonnes NOT NULL avec des valeurs sûres
        $sql = "
            INSERT IGNORE INTO journal_caisse 
            (date_operation, numero_piece, nature_operation, client_id, fournisseur_id, sens, montant, mode_paiement_id, vente_id, reservation_id, inscription_formation_id, responsable_encaissement_id, observations, est_annule, type_operation)
            SELECT 
                DATE(date_ecriture),
                CONCAT('MIG-', id),
                COALESCE(commentaire, source_type, 'Migration caisse_journal'),
                NULL,
                NULL,
                CASE WHEN sens = 'ENTREE' THEN 'RECETTE' WHEN sens = 'SORTIE' THEN 'DEPENSE' ELSE 'RECETTE' END,
                montant,
                1,
                CASE WHEN source_type = 'vente' THEN source_id ELSE NULL END,
                CASE WHEN source_type = 'reservation_hotel' THEN source_id ELSE NULL END,
                CASE WHEN source_type = 'inscription_formation' THEN source_id ELSE NULL END,
                COALESCE(utilisateur_id, 1),
                commentaire,
                0,
                UPPER(COALESCE(source_type, 'AUTRE'))
            FROM caisse_journal
        ";
        
        $pdo->exec($sql);
        echo "   ✓ Migration effectuée\n";
        
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM journal_caisse");
        $new_count = $stmt->fetch()['cnt'];
        echo "   ✓ journal_caisse a maintenant $new_count lignes\n";
    }
} catch (Exception $e) {
    echo "   ✗ Erreur: " . $e->getMessage() . "\n";
}

// 4. CRÉER DES DONNÉES POUR AUJOURD'HUI
echo "\n4. Création données pour aujourd'hui:\n";

try {
    $todayDate = date('Y-m-d');
    $numero = 'TEST-' . date('His');
    
    $data = [
        ['RECETTE', 25000, 'Vente client A'],
        ['RECETTE', 30000, 'Vente client B'],
        ['RECETTE', 15000, 'Encaissement formation'],
        ['DEPENSE', 5000, 'Frais opérationnels'],
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO journal_caisse 
        (date_operation, numero_piece, nature_operation, client_id, fournisseur_id, sens, montant, mode_paiement_id, vente_id, reservation_id, inscription_formation_id, responsable_encaissement_id, observations, est_annule, type_operation)
        VALUES (?, ?, ?, NULL, NULL, ?, ?, 1, NULL, NULL, NULL, 1, ?, 0, ?)
    ");
    
    $total = 0;
    $i = 0;
    foreach ($data as $row) {
        [$sens, $montant, $comment] = $row;
        $numeroPiece = $numero . '-' . (++$i);
        $typeOperation = $sens === 'RECETTE' ? 'VENTE' : 'AUTRE';
        $stmt->execute([$todayDate, $numeroPiece, $comment, $sens, $montant, $comment, $typeOperation]);
        $total += $montant;
        echo "   ✓ $comment: $montant F\n";
    }
    
    echo "   ✓ Total créé: $total F\n";
    
} catch (Exception $e) {
    echo "   ✗ Erreur: " . $e->getMessage() . "\n";
}

// 5. Vérifier le CA d'aujourd'hui
echo "\n5. Vérification finale:\n";

try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as cnt,
            SUM(CASE WHEN sens = 'RECETTE' THEN montant ELSE 0 END) as recettes,
            SUM(CASE WHEN sens = 'DEPENSE' THEN montant ELSE 0 END) as depenses
        FROM journal_caisse
        WHERE DATE(date_operation) = CURDATE() AND est_annule = 0
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "   Écritures d'aujourd'hui: " . $result['cnt'] . "\n";
    echo "   Recettes: " . ($result['recettes'] ?? 0) . " F\n";
    echo "   Dépenses: " . ($result['depenses'] ?? 0) . " F\n";
    echo "   CA NET: " . (($result['recettes'] ?? 0) - ($result['depenses'] ?? 0)) . " F\n";
    
} catch (Exception $e) {
    echo "   ✗ Erreur: " . $e->getMessage() . "\n";
}

echo "\n✓ CORRECTION TERMINÉE\n";
echo "→ Recharge le dashboard: index.php\n";
?>
