<?php
require_once __DIR__ . '/lib/caisse.php';

$pdo = new PDO('mysql:host=127.0.0.1;dbname=kms_gestion;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "🧪 TEST FONCTION caisse_enregistrer_ecriture\n";
echo "═" . str_repeat("═", 50) . "\n\n";

try {
    echo "1️⃣  Appel de la fonction...\n";
    $journalId = caisse_enregistrer_ecriture(
        $pdo,
        'RECETTE',
        665415.00,
        'VENTE',
        90,
        'Encaissement vente V-20251214-143828',
        1,
        date('Y-m-d'),
        'V-20251214-143828'
    );
    
    echo "   ✓ Fonction retourne: $journalId\n";
    echo "   ✓ Type: " . gettype($journalId) . "\n\n";
    
    if ($journalId && $journalId > 0) {
        echo "2️⃣  Vérification en base de données...\n";
        $stmt = $pdo->prepare("SELECT id, vente_id, montant, sens, nature_operation FROM journal_caisse WHERE id = ?");
        $stmt->execute([$journalId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            echo "   ✓ ID: {$row['id']}\n";
            echo "   ✓ Vente ID: {$row['vente_id']}\n";
            echo "   ✓ Montant: {$row['montant']} FCFA\n";
            echo "   ✓ Sens: {$row['sens']}\n";
            echo "   ✓ Nature: {$row['nature_operation']}\n";
            echo "\n✅ FONCTION FONCTIONNE!\n";
        } else {
            echo "   ❌ Aucune entrée trouvée avec ID $journalId\n";
        }
    } else {
        echo "   ❌ ERREUR: caisse_enregistrer_ecriture a retourné: " . var_export($journalId, true) . "\n";
    }
} catch (Throwable $e) {
    echo "❌ ERREUR: {$e->getMessage()}\n";
    echo "Fichier: {$e->getFile()} ligne {$e->getLine()}\n";
}
?>
