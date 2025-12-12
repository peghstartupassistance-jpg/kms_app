<?php
// check_migration.php - vérifie existence de tables mouvements_stock / backups
require_once __DIR__ . '/db/db.php';

echo "<h2>Vérification migration</h2>";

// Liste des tables
$tables = [];
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

echo "<p>Tables présentes (filtrées pour 'mouv' et 'stocks') :</p>";
echo "<ul>";
foreach ($tables as $t) {
    if (stripos($t, 'mouv') !== false || stripos($t, 'stock') !== false) {
        echo "<li>" . htmlspecialchars($t) . "</li>";
    }
}
echo "</ul>";

// Détails si une table backup existe
$backups = preg_grep('/^mouvements_stock_backup_\\d{8}_\\d{6}$/', $tables);
if (!empty($backups)) {
    echo "<h3>Backups trouvés :</h3><ul>";
    foreach ($backups as $b) {
        echo "<li>" . htmlspecialchars($b) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>Aucun backup 'mouvements_stock_backup_YYYYMMDD_HHMMSS' trouvé.</p>";
}

// Compter lignes dans stocks_mouvements
if (in_array('stocks_mouvements', $tables)) {
    $cnt = $pdo->query("SELECT COUNT(*) FROM stocks_mouvements")->fetchColumn();
    echo "<p><strong>stocks_mouvements</strong> contient <strong>$cnt</strong> lignes.</p>";
} else {
    echo "<p>Table <strong>stocks_mouvements</strong> introuvable.</p>";
}

// Compter lignes dans mouvements_stock (si présente)
if (in_array('mouvements_stock', $tables)) {
    $cnt = $pdo->query("SELECT COUNT(*) FROM mouvements_stock")->fetchColumn();
    echo "<p><strong>mouvements_stock</strong> contient <strong>$cnt</strong> lignes.</p>";
} else {
    echo "<p>Table <strong>mouvements_stock</strong> introuvable.</p>";
}

// Conseils
echo "<h3>Étapes suivantes recommandées</h3>";
echo "<ol>";
echo "<li>Si un backup existe (mouvements_stock_backup...), la migration a probablement déjà été lancée — vérifie le contenu du backup avant de relancer.</li>";
echo "<li>Si tu veux que je migre depuis le backup, indique-moi le nom exact de la table backup ou autorise la migration automatique.</li>";
echo "<li>Si la migration est déjà faite et que tu veux réaligner produits.stock_actuel, je peux exécuter un recalcul (update) pour tous les produits à partir de stocks_mouvements.</li>";
echo "</ol>";

?>
