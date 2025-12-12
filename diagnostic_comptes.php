<?php
require_once __DIR__ . '/db/db.php';

echo "=== ANALYSE DES COMPTES ===\n\n";

$sql = "SELECT * FROM compta_comptes WHERE numero_compte IN ('100', '500', '512', '411', '401', '530') ORDER BY numero_compte";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$comptes = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($comptes as $c) {
    echo "Compte " . $c['numero_compte'] . " (" . $c['libelle'] . ") - Classe " . $c['classe'] . "\n";
}

echo "\n=== ÉCRITURES PAR COMPTE ===\n\n";

$sql = "
    SELECT 
        cc.numero_compte,
        cc.libelle,
        cc.classe,
        SUM(ce.debit) as total_debit,
        SUM(ce.credit) as total_credit,
        SUM(ce.debit) - SUM(ce.credit) as solde
    FROM compta_comptes cc
    LEFT JOIN compta_ecritures ce ON ce.compte_id = cc.id
    LEFT JOIN compta_pieces cp ON cp.id = ce.piece_id AND cp.est_validee = 1
    WHERE cc.numero_compte IN ('100', '500', '512', '411', '401', '530', '2', '607', '701', '707', '622')
    GROUP BY cc.id
    ORDER BY cc.numero_compte
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($resultats as $r) {
    printf("%-6s %-30s Classe %d | D: %12.2f | C: %12.2f | Solde: %12.2f\n",
        $r['numero_compte'],
        substr($r['libelle'], 0, 30),
        $r['classe'],
        $r['total_debit'] ?? 0,
        $r['total_credit'] ?? 0,
        $r['solde'] ?? 0
    );
}

echo "\n=== PROBLÈME ===\n\n";

// Compte 100 ne doit pas être en classe 1 (Actif), doit être en classe 5 (Passif)
$sql = "SELECT id, classe FROM compta_comptes WHERE numero_compte = '100'";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$compte_100 = $stmt->fetch();

if ($compte_100['classe'] == 1) {
    echo "❌ ERREUR : Compte 100 (Capital) est en Classe 1 (Actif)\n";
    echo "   Il devrait être en Classe 5 (Passif/Capitaux propres)\n\n";
    echo "   ➜ Correction : UPDATE compta_comptes SET classe = 5 WHERE numero_compte = '100'\n";
} else {
    echo "✓ Compte 100 est correctement en Classe " . $compte_100['classe'] . "\n";
}

?>
