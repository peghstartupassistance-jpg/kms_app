<?php
require_once __DIR__ . '/../db/db.php';

$achats = $pdo->query('SELECT * FROM achats')->fetchAll(PDO::FETCH_ASSOC);
if (empty($achats)) {
    echo "Aucun achat trouvé.\n";
} else {
    echo "Achats présents :\n";
    foreach ($achats as $a) {
        echo "#{$a['id']} - {$a['numero']} - {$a['date_achat']} - Montant TTC: {$a['montant_total_ttc']}\n";
    }
}
