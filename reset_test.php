<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=kms_gestion;charset=utf8mb4', 'root', '');

// Réinitialiser vente 90 pour re-tester
$pdo->exec("UPDATE ventes SET statut_encaissement = 'ATTENTE_PAIEMENT', journal_caisse_id = NULL WHERE id = 90");

echo "✅ Vente #90 réinitialisée pour test\n";

// Vérifier
$stmt = $pdo->prepare("SELECT id, statut_encaissement, journal_caisse_id FROM ventes WHERE id = 90");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Statut: {$row['statut_encaissement']}\n";
echo "Journal ID: " . ($row['journal_caisse_id'] ?? 'NULL') . "\n";
?>
