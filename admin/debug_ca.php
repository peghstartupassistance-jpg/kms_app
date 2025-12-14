<?php
/**
 * Diagnostic complet de la trésorerie
 */

require_once __DIR__ . '/../security.php';
global $pdo;

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNOSTIC COMPLET TRÉSORERIE ===\n\n";

// 1. Tables existantes
echo "1. TABLES EXISTANTES:\n";
$tables = ['journal_caisse', 'caisse_journal'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM $table");
        $count = $stmt->fetch()['cnt'];
        echo "   ✓ $table: $count lignes\n";
    } catch (Exception $e) {
        echo "   ✗ $table: inexistante\n";
    }
}

echo "\n2. STRUCTURE journal_caisse:\n";
try {
    $stmt = $pdo->query('SHOW COLUMNS FROM journal_caisse');
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($cols as $col) {
        echo "   - $col\n";
    }
} catch (Exception $e) {
    echo "   ✗ Erreur: " . $e->getMessage() . "\n";
}

echo "\n3. DONNÉES AUJOURD'HUI:\n";
$today = date('Y-m-d');
echo "   Date: $today\n";

// Vérifier journal_caisse
try {
    $stmt = $pdo->prepare('SELECT * FROM journal_caisse WHERE DATE(date_operation) = ?');
    $stmt->execute([$today]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   journal_caisse: " . count($data) . " lignes\n";
    foreach ($data as $row) {
        echo "      id=" . $row['id'] . ", sens=" . $row['sens'] . ", montant=" . $row['montant'] . "\n";
    }
} catch (Exception $e) {
    echo "   journal_caisse: Erreur\n";
}

// Vérifier caisse_journal
try {
    $stmt = $pdo->prepare('SELECT * FROM caisse_journal WHERE DATE(date_ecriture) = ?');
    $stmt->execute([$today]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   caisse_journal: " . count($data) . " lignes\n";
    foreach ($data as $row) {
        echo "      id=" . $row['id'] . ", sens=" . $row['sens'] . ", montant=" . $row['montant'] . "\n";
    }
} catch (Exception $e) {
    echo "   caisse_journal: n'existe pas\n";
}

echo "\n4. CALCULS CA:\n";

// Calcul avec journal_caisse
try {
    $stmt = $pdo->prepare('
        SELECT 
            COUNT(*) as cnt,
            SUM(CASE WHEN sens IN ("RECETTE", "ENTREE") THEN montant ELSE 0 END) as total_recettes,
            SUM(CASE WHEN sens IN ("DEPENSE", "SORTIE") THEN montant ELSE 0 END) as total_depenses,
            SUM(montant) as total
        FROM journal_caisse 
        WHERE DATE(date_operation) = ? AND est_annule = 0
    ');
    $stmt->execute([$today]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "   journal_caisse:\n";
    echo "      Lignes: " . $result['cnt'] . "\n";
    echo "      Recettes: " . $result['total_recettes'] . "\n";
    echo "      Dépenses: " . $result['total_depenses'] . "\n";
    echo "      Total: " . $result['total'] . "\n";
} catch (Exception $e) {
    echo "   journal_caisse: Erreur - " . $e->getMessage() . "\n";
}

// Calcul avec caisse_journal
try {
    $stmt = $pdo->prepare('
        SELECT 
            COUNT(*) as cnt,
            SUM(CASE WHEN sens = "ENTREE" THEN montant ELSE 0 END) as total_entree,
            SUM(CASE WHEN sens = "SORTIE" THEN montant ELSE 0 END) as total_sortie,
            SUM(montant) as total
        FROM caisse_journal 
        WHERE DATE(date_ecriture) = ?
    ');
    $stmt->execute([$today]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "   caisse_journal:\n";
    echo "      Lignes: " . $result['cnt'] . "\n";
    echo "      Entrées: " . $result['total_entree'] . "\n";
    echo "      Sorties: " . $result['total_sortie'] . "\n";
    echo "      Total: " . $result['total'] . "\n";
} catch (Exception $e) {
    echo "   caisse_journal: n'existe pas ou erreur\n";
}

echo "\n5. REQUÊTE UTILISÉE DANS index.php:\n";

$ca_jour = 0;
try {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN sens IN ('RECETTE', 'ENTREE') THEN montant ELSE 0 END) as ca_total
        FROM journal_caisse 
        WHERE DATE(date_operation) = CURDATE() AND est_annule = 0
    ");
    $stmt->execute();
    $ca_data = $stmt->fetch();
    $ca_jour = (float)($ca_data['ca_total'] ?? 0);
    
    echo "   Résultat index.php: " . $ca_jour . " F\n";
} catch (Exception $e) {
    echo "   Erreur: " . $e->getMessage() . "\n";
}

echo "\n";
?>
