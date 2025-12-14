<?php
require_once __DIR__ . '/db/db.php';
global $pdo;

echo "=== VÉRIFICATION DES PIÈCES EN ATTENTE ===\n\n";

// Toutes les pièces non validées
$stmt = $pdo->query("
    SELECT id, numero_piece, reference_type, est_validee, date_piece, exercice_id, observations
    FROM compta_pieces 
    WHERE est_validee = 0
    ORDER BY id DESC
");
$pieces = $stmt->fetchAll();

echo "Total pièces NON VALIDÉES: " . count($pieces) . "\n\n";

if (count($pieces) > 0) {
    foreach ($pieces as $p) {
        echo "Pièce #{$p['numero_piece']} (ID: {$p['id']})\n";
        echo "  Type: {$p['reference_type']}\n";
        echo "  Date: {$p['date_piece']}\n";
        echo "  Exercice: {$p['exercice_id']}\n";
        echo "  Validée: " . ($p['est_validee'] ? 'OUI' : 'NON') . "\n";
        echo "  Obs: " . substr($p['observations'], 0, 80) . "...\n\n";
    }
}

// Vérifier le filtre utilisé dans analyse_corrections.php
echo "\n=== TEST DU FILTRE 'CORRECTION' ===\n";
$stmt = $pdo->query("
    SELECT COUNT(*) as nb
    FROM compta_pieces 
    WHERE reference_type = 'CORRECTION' 
    AND est_validee = 0
");
$result = $stmt->fetch();
echo "Pièces avec reference_type = 'CORRECTION': {$result['nb']}\n";

$stmt = $pdo->query("
    SELECT COUNT(*) as nb
    FROM compta_pieces 
    WHERE reference_type = 'CORRECTION_OUVERTURE' 
    AND est_validee = 0
");
$result = $stmt->fetch();
echo "Pièces avec reference_type = 'CORRECTION_OUVERTURE': {$result['nb']}\n";

$stmt = $pdo->query("
    SELECT COUNT(*) as nb
    FROM compta_pieces 
    WHERE reference_type LIKE 'CORRECTION%' 
    AND est_validee = 0
");
$result = $stmt->fetch();
echo "Pièces avec reference_type LIKE 'CORRECTION%': {$result['nb']}\n";
