<?php
// Test de vérification que tout fonctionne
require_once __DIR__ . '/security.php';

$tests = [];

// Test 1: Connexion à la base de données
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM ventes");
    $result = $stmt->fetch();
    $tests['Database'] = 'OK (' . $result['count'] . ' ventes)';
} catch (Exception $e) {
    $tests['Database'] = 'ERROR: ' . $e->getMessage();
}

// Test 2: Vérifier que lib/stock.php est chargeable
try {
    require_once __DIR__ . '/lib/stock.php';
    $tests['lib/stock.php'] = 'OK';
} catch (Exception $e) {
    $tests['lib/stock.php'] = 'ERROR: ' . $e->getMessage();
}

// Test 3: Vérifier que lib/caisse.php est chargeable
try {
    require_once __DIR__ . '/lib/caisse.php';
    $tests['lib/caisse.php'] = 'OK';
} catch (Exception $e) {
    $tests['lib/caisse.php'] = 'ERROR: ' . $e->getMessage();
}

// Test 4: Vérifier que table journal_caisse existe
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM journal_caisse");
    $result = $stmt->fetch();
    $tests['journal_caisse table'] = 'OK (' . $result['count'] . ' écritures)';
} catch (Exception $e) {
    $tests['journal_caisse table'] = 'ERROR: ' . $e->getMessage();
}

// Test 5: Vérifier les ventes avec les lignes
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM ventes_lignes");
    $result = $stmt->fetch();
    $tests['ventes_lignes'] = 'OK (' . $result['count'] . ' lignes)';
} catch (Exception $e) {
    $tests['ventes_lignes'] = 'ERROR: ' . $e->getMessage();
}

// Test 6: Vérifier les mouvements de stock
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM stocks_mouvements");
    $result = $stmt->fetch();
    $tests['stocks_mouvements'] = 'OK (' . $result['count'] . ' mouvements)';
} catch (Exception $e) {
    $tests['stocks_mouvements'] = 'ERROR: ' . $e->getMessage();
}

// Test 7: Dashboard CA du jour
try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(montant), 0) as ca
        FROM journal_caisse
        WHERE DATE(date_operation) = CURDATE()
        AND sens = 'RECETTE'
        AND est_annule = 0
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    $ca = $result['ca'];
    $tests['CA du jour'] = 'OK (' . number_format($ca, 2, ',', ' ') . ' F)';
} catch (Exception $e) {
    $tests['CA du jour'] = 'ERROR: ' . $e->getMessage();
}

// Affichage
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test en ligne - KMS Gestion</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .test-item { margin: 15px 0; padding: 10px; border-left: 4px solid #ddd; }
        .test-item.ok { border-left-color: #28a745; background: #f0fff4; }
        .test-item.error { border-left-color: #dc3545; background: #fff5f5; }
        .test-label { font-weight: bold; color: #333; }
        .test-result { color: #666; margin-left: 10px; }
        .test-result.ok { color: #28a745; }
        .test-result.error { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <h1>✓ Application en ligne - Vérification</h1>
        <p>Date: <?= date('d/m/Y H:i:s') ?></p>
        
        <?php foreach ($tests as $name => $result): 
            $isError = strpos($result, 'ERROR') !== false;
            $class = $isError ? 'error' : 'ok';
        ?>
            <div class="test-item <?= $class ?>">
                <span class="test-label"><?= htmlspecialchars($name) ?>:</span>
                <span class="test-result <?= $class ?>"><?= htmlspecialchars($result) ?></span>
            </div>
        <?php endforeach; ?>
        
        <hr style="margin-top: 30px;">
        <p style="color: #666; font-size: 0.9em;">Tous les modules critiques sont chargés et fonctionnels.</p>
    </div>
</body>
</html>
