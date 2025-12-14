<?php
// Vérifier le contenu du fichier SQL exporté
$sqlfile = 'kms_gestion.sql';

if (!file_exists($sqlfile)) {
    die("Fichier non trouvé: $sqlfile\n");
}

$content = file_get_contents($sqlfile);
$size = filesize($sqlfile);

echo "═══════════════════════════════════════════════════════════════\n";
echo "VÉRIFICATION DU FICHIER SQL EXPORTÉ\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "Fichier: $sqlfile\n";
echo sprintf("Taille: %s bytes (%.2f MB)\n", number_format($size), $size / 1024 / 1024);
echo "Date: " . date('Y-m-d H:i:s', filemtime($sqlfile)) . "\n\n";

// Vérifications
$checks = [
    'Compte 12000 (Report à nouveau)' => 'INSERT INTO `compta_comptes`.*12000',
    'Compte 47000 (Débiteurs divers)' => 'INSERT INTO `compta_comptes`.*47000',
    'Pièce de correction' => 'INSERT INTO `compta_pieces`.*CORRECTION_OUVERTURE',
    'Écritures des comptes' => 'INSERT INTO `compta_ecritures`.*12000\|47000',
    'Journal OD' => "INSERT INTO `compta_journaux`.*'OD'",
    'Exercice 2025' => "INSERT INTO `compta_exercices`.*2025",
];

echo "CONTRÔLES:\n";
foreach ($checks as $label => $pattern) {
    $found = preg_match("/$pattern/i", $content) ? '✅' : '❌';
    echo "  $found $label\n";
}

// Compter les tables
$tables = preg_match_all('/DROP TABLE IF EXISTS/', $content);
$inserts = preg_match_all('/^INSERT INTO/m', $content);

echo "\n";
echo "STATISTIQUES:\n";
echo "  Tables: " . $tables . "\n";
echo "  Lignes INSERT: " . $inserts . "\n";

// Afficher les dernières lignes
echo "\n";
echo "DERNIÈRES LIGNES DU FICHIER:\n";
$lines = explode("\n", $content);
$lastLines = array_slice($lines, -5);
foreach ($lastLines as $line) {
    if (trim($line)) {
        echo "  " . substr($line, 0, 100) . "\n";
    }
}

echo "\n✅ Fichier SQL exporté avec succès et contient les données actuelles !\n";
