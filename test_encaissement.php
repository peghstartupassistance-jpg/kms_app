<?php
// Test rapide: Vérifier que la colonne statut_encaissement existe et afficher ventes
$pdo = new PDO('mysql:host=127.0.0.1;dbname=kms_gestion;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "🔍 TEST PHASE 1.1 - ENCAISSEMENT\n";
echo "═" . str_repeat("═", 60) . "\n\n";

// 1. Vérifier colonne existe
echo "1️⃣  Vérification colonnes table ventes...\n";
$stmt = $pdo->query("SHOW COLUMNS FROM ventes");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
$has_statut_enc = in_array('statut_encaissement', $columns);
$has_journal_id = in_array('journal_caisse_id', $columns);

echo "   ✓ statut_encaissement: " . ($has_statut_enc ? "✅ OUI" : "❌ NON") . "\n";
echo "   ✓ journal_caisse_id: " . ($has_journal_id ? "✅ OUI" : "❌ NON") . "\n\n";

// 2. Afficher quelques ventes
echo "2️⃣  Ventes existantes (dernières 5):\n";
$stmt = $pdo->query("
    SELECT id, numero, montant_total_ttc, statut, statut_encaissement
    FROM ventes
    ORDER BY id DESC
    LIMIT 5
");
$ventes = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($ventes as $v) {
    echo "   ID: {$v['id']} | Numéro: {$v['numero']} | Montant: {$v['montant_total_ttc']} FCFA\n";
    echo "      Statut: {$v['statut']} | Encaissement: {$v['statut_encaissement']}\n";
}

echo "\n3️⃣  Modes de paiement disponibles:\n";
$stmt = $pdo->query("SELECT id, libelle FROM modes_paiement LIMIT 5");
$modes = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($modes as $m) {
    echo "   ID: {$m['id']} | {$m['libelle']}\n";
}

echo "\n✅ Setup encaissement OK - Prêt à tester en navigateur\n";
?>
