<?php
$sql_file = 'c:\xampp\htdocs\kms_app\db\compta_schema_clean.sql';
$sql_content = file_get_contents($sql_file);
echo "Contenu du fichier (" . strlen($sql_content) . " bytes)\n";

// Diviser par points-virgules
$statements = explode(';', $sql_content);
echo "Nombre de segments : " . count($statements) . "\n";

$queries = array_filter($statements, function($stmt) {
    $stmt = trim($stmt);
    $is_empty = empty($stmt);
    $is_comment = substr($stmt, 0, 2) === '--';
    echo "  Segment : " . substr($stmt, 0, 40) . "... Empty=$is_empty, Comment=$is_comment\n";
    return !$is_empty && substr($stmt, 0, 2) !== '--';
});

echo "\nAprès filtrage : " . count($queries) . " requêtes\n";
foreach ($queries as $q) {
    echo "  ✓ " . substr(trim($q), 0, 50) . "\n";
}
?>
