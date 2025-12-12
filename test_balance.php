<?php
require_once __DIR__ . '/db/db.php';
require_once __DIR__ . '/lib/compta.php';

echo "=== TEST compta_get_balance ===\n\n";

$exercice = compta_get_exercice_actif($pdo);

if ($exercice) {
    echo "Exercice actif : " . $exercice['annee'] . " (ID: " . $exercice['id'] . ")\n\n";
    
    $balance = compta_get_balance($pdo, $exercice['id']);
    
    echo "Résultat balance : " . count($balance) . " comptes\n\n";
    
    foreach ($balance as $compte) {
        echo $compte['numero_compte'] . " | " . $compte['libelle'] . " | ";
        echo "Classe: " . $compte['classe'] . " | ";
        echo "Débit: " . $compte['total_debit'] . " | ";
        echo "Crédit: " . $compte['total_credit'] . "\n";
    }
} else {
    echo "❌ Aucun exercice actif trouvé\n";
}

?>
