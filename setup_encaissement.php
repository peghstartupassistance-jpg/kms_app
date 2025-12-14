<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=kms_gestion;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    // Vérifier si la colonne existe déjà
    $stmt = $pdo->query("SHOW COLUMNS FROM ventes LIKE 'statut_encaissement'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ventes ADD COLUMN statut_encaissement VARCHAR(30) DEFAULT 'ATTENTE_PAIEMENT' AFTER statut");
        echo "✓ Colonne statut_encaissement ajoutée.\n";
    } else {
        echo "✓ Colonne statut_encaissement existe déjà.\n";
    }
    
    // Vérifier aussi la colonne journal_caisse_id (lien direct)
    $stmt = $pdo->query("SHOW COLUMNS FROM ventes LIKE 'journal_caisse_id'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ventes ADD COLUMN journal_caisse_id INT(10) UNSIGNED DEFAULT NULL AFTER statut_encaissement");
        echo "✓ Colonne journal_caisse_id ajoutée.\n";
    } else {
        echo "✓ Colonne journal_caisse_id existe déjà.\n";
    }
    
    echo "\n✅ Setup encaissement réussi!\n";
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
?>
