<?php
require_once __DIR__ . '/db/db.php';

echo "=== DIAGNOSTIC COMPLET DU BILAN ===\n\n";

// 1. Vérifier les écritures validées
echo "1️⃣  ÉCRITURES VALIDÉES\n";
$sql = "
    SELECT 
        COUNT(ce.id) as total_ecritures,
        SUM(CASE WHEN ce.debit > 0 THEN ce.debit ELSE 0 END) as total_debit,
        SUM(CASE WHEN ce.credit > 0 THEN ce.credit ELSE 0 END) as total_credit
    FROM compta_ecritures ce
    LEFT JOIN compta_pieces p ON p.id = ce.piece_id
    WHERE p.est_validee = 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "   Écritures : " . $result['total_ecritures'] . "\n";
echo "   Débits : " . $result['total_debit'] . "\n";
echo "   Crédits : " . $result['total_credit'] . "\n\n";

// 2. Vérifier les comptes
echo "2️⃣  COMPTES COMPTABLES\n";
$sql = "SELECT COUNT(*) as total FROM compta_comptes";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "   Total comptes : " . $result['total'] . "\n\n";

// 3. Vérifier la balance
echo "3️⃣  BALANCE (avec pièces validées uniquement)\n";
$sql = "
    SELECT 
        cc.classe,
        COUNT(DISTINCT cc.id) as nb_comptes,
        SUM(COALESCE(ce.debit, 0)) as total_debit,
        SUM(COALESCE(ce.credit, 0)) as total_credit
    FROM compta_comptes cc
    LEFT JOIN compta_ecritures ce ON ce.compte_id = cc.id
    LEFT JOIN compta_pieces cp ON cp.id = ce.piece_id AND cp.est_validee = 1
    GROUP BY cc.classe
    ORDER BY cc.classe
";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$balance = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($balance as $ligne) {
    echo "   Classe " . $ligne['classe'] . " : " . $ligne['nb_comptes'] . " comptes | ";
    echo "Débit: " . number_format($ligne['total_debit'], 2) . " | ";
    echo "Crédit: " . number_format($ligne['total_credit'], 2) . "\n";
}

// 4. Vérifier les exercices
echo "\n4️⃣  EXERCICES\n";
$sql = "SELECT * FROM compta_exercices ORDER BY id";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$exercices = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($exercices as $ex) {
    echo "   " . $ex['annee'] . " (ID: " . $ex['id'] . ") - Actif: " . ($ex['est_actif'] ? "OUI" : "NON") . "\n";
}
?>
