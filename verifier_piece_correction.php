<?php
require_once __DIR__ . '/db/db.php';
global $pdo;

echo "=== VÉRIFICATION DE LA PIÈCE DE CORRECTION ===\n\n";

// Récupérer la dernière pièce créée
$stmt = $pdo->query("
    SELECT cp.*, cj.libelle as journal_libelle
    FROM compta_pieces cp
    JOIN compta_journaux cj ON cp.journal_id = cj.id
    WHERE cp.reference_type LIKE 'CORRECTION%'
    ORDER BY cp.id DESC
    LIMIT 1
");
$piece = $stmt->fetch();

if (!$piece) {
    echo "Aucune pièce de correction trouvée.\n";
    exit(1);
}

echo "Pièce #{$piece['numero_piece']} (ID: {$piece['id']})\n";
echo "Date: {$piece['date_piece']}\n";
echo "Journal: {$piece['journal_libelle']}\n";
echo "Validée: " . ($piece['est_validee'] ? 'OUI' : 'NON') . "\n";
echo "Type: {$piece['reference_type']}\n";
echo "Observations: {$piece['observations']}\n\n";

// Récupérer les écritures
$stmt = $pdo->prepare("
    SELECT ce.*, cc.numero_compte, cc.libelle as compte_libelle
    FROM compta_ecritures ce
    JOIN compta_comptes cc ON ce.compte_id = cc.id
    WHERE ce.piece_id = ?
    ORDER BY ce.id
");
$stmt->execute([$piece['id']]);
$ecritures = $stmt->fetchAll();

echo "=== ÉCRITURES ===\n";
echo str_pad("COMPTE", 15) . str_pad("LIBELLÉ", 40) . str_pad("DÉBIT", 15) . str_pad("CRÉDIT", 15) . "\n";
echo str_repeat("-", 85) . "\n";

$total_debit = 0;
$total_credit = 0;

foreach ($ecritures as $e) {
    echo str_pad($e['numero_compte'], 15);
    echo str_pad(substr($e['libelle_ecriture'], 0, 38), 40);
    echo str_pad(number_format($e['debit'], 0, ',', ' '), 15);
    echo str_pad(number_format($e['credit'], 0, ',', ' '), 15);
    echo "\n";
    
    $total_debit += $e['debit'];
    $total_credit += $e['credit'];
}

echo str_repeat("-", 85) . "\n";
echo str_pad("TOTAUX", 55);
echo str_pad(number_format($total_debit, 0, ',', ' '), 15);
echo str_pad(number_format($total_credit, 0, ',', ' '), 15);
echo "\n";

if (abs($total_debit - $total_credit) < 0.01) {
    echo "\n✅ Pièce équilibrée\n";
} else {
    echo "\n❌ Pièce non équilibrée (écart: " . number_format($total_debit - $total_credit, 2) . ")\n";
}
