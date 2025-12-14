<?php
require_once __DIR__ . '/security.php';
global $pdo;

header('Content-Type: text/html; charset=utf-8');

echo "<h1>DEBUG CA DASHBOARD</h1>\n";

$today = date('Y-m-d');
echo "<p>Date d'aujourd'hui: <strong>$today</strong></p>\n";

// 1. Vérifier les tables
echo "<h2>1. Tables</h2>\n";
echo "<pre>\n";

try {
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM journal_caisse");
    $count = $stmt->fetch()['cnt'];
    echo "✓ journal_caisse: $count lignes\n";
} catch (Exception $e) {
    echo "✗ journal_caisse: " . $e->getMessage() . "\n";
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM caisse_journal");
    $count = $stmt->fetch()['cnt'];
    echo "✓ caisse_journal: $count lignes\n";
} catch (Exception $e) {
    echo "✗ caisse_journal: n'existe pas\n";
}

echo "</pre>\n";

// 2. Vérifier les données d'aujourd'hui
echo "<h2>2. Données d'aujourd'hui</h2>\n";
echo "<pre>\n";

$stmt = $pdo->prepare("SELECT * FROM journal_caisse WHERE DATE(date_operation) = ?");
$stmt->execute([$today]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Nombre de lignes: " . count($rows) . "\n";
if (count($rows) > 0) {
    echo "Détail:\n";
    foreach ($rows as $row) {
        echo "  ID: {$row['id']}, Sens: {$row['sens']}, Montant: {$row['montant']}, Annulée: {$row['est_annule']}\n";
    }
} else {
    echo "AUCUNE DONNÉE!\n";
}

echo "</pre>\n";

// 3. Calculer le CA
echo "<h2>3. Calcul CA</h2>\n";
echo "<pre>\n";

$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as cnt,
        SUM(CASE WHEN sens IN ('RECETTE', 'ENTREE') THEN montant ELSE 0 END) as total
    FROM journal_caisse
    WHERE DATE(date_operation) = ? AND est_annule = 0
");
$stmt->execute([$today]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Lignes valides: " . $result['cnt'] . "\n";
echo "CA Total: " . ($result['total'] ?? 0) . " F\n";

echo "</pre>\n";

// 4. Vérifier la requête d'index.php
echo "<h2>4. Requête index.php</h2>\n";
echo "<pre>\n";

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
    echo "Résultat: $ca_jour F\n";
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}

echo "</pre>\n";

// 5. Vérifier CURDATE()
echo "<h2>5. Diagnostic CURDATE()</h2>\n";
echo "<pre>\n";

$stmt = $pdo->query("SELECT CURDATE() as d, DATE(NOW()) as n, DATE('2025-12-14') as test");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "CURDATE() = " . $result['d'] . "\n";
echo "DATE(NOW()) = " . $result['n'] . "\n";
echo "Votre date = " . date('Y-m-d') . "\n";

echo "</pre>\n";

?>
