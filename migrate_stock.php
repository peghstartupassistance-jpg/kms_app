<?php
// migrate_stock.php
// Attention : exécuter une fois depuis le serveur (via navigateur ou CLI)
// 1) Script qui migre les données de `mouvements_stock` vers `stocks_mouvements`
// 2) Met à jour `produits.stock_actuel` à partir de la somme des `stocks_mouvements`
// 3) Renomme (si souhaité) la table source en backup

require_once __DIR__ . '/db/db.php';

// Mode safe : run=1 pour exécuter la migration, sinon dry-run (preview)
$run = isset($_GET['run']) && $_GET['run'] == '1';

try {
    echo "<h2>MIGRATION mouvements_stock -> stocks_mouvements</h2>";

    // Check existence of tables
    $hasMouvementsStock = (bool)$pdo->query("SHOW TABLES LIKE 'mouvements_stock'")->fetch();
    $hasStocksMouvements = (bool)$pdo->query("SHOW TABLES LIKE 'stocks_mouvements'")->fetch();

    if (!$hasMouvementsStock) {
        echo "<p style='color:orange;'>Table <strong>mouvements_stock</strong> introuvable. Rien à migrer.</p>";
        exit;
    }
    if (!$hasStocksMouvements) {
        throw new RuntimeException('Table stocks_mouvements introuvable. Migration annulée.');
    }

    // Fetch rows to migrate
    $stmt = $pdo->query("SELECT * FROM mouvements_stock ORDER BY id");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = count($rows);
    echo "<p>Enregistrements trouvés dans <em>mouvements_stock</em> : <strong>$count</strong></p>";

    if ($count === 0) {
        echo "<p>Aucun enregistrement à migrer.</p>";
        exit;
    }

    // Show a small preview (first 10)
    echo "<h3>Aperçu (10 premières lignes)</h3>";
    echo "<table border=1 cellpadding=5><tr><th>id</th><th>date_mouvement</th><th>type</th><th>produit_id</th><th>quantite</th><th>source_module</th></tr>";
    $i = 0;
    foreach ($rows as $r) {
        if ($i++ >= 10) break;
        echo "<tr><td>{$r['id']}</td><td>{$r['date_mouvement']}</td><td>{$r['type_mouvement']}</td><td>{$r['produit_id']}</td><td>{$r['quantite']}</td><td>{$r['source_module']}</td></tr>";
    }
    echo "</table>";

    if (!$run) {
        echo "<p style='color:blue;'>Mode preview — la migration n'a pas été exécutée.<br>Si tu veux lancer la migration, ouvre : <code>?run=1</code> (ex : <a href=\"?run=1\">?run=1</a>).</p>";
        exit;
    }

    // Execution path
    $pdo->beginTransaction();

    $insert = $pdo->prepare(
        "INSERT INTO stocks_mouvements (
            produit_id, date_mouvement, type_mouvement, quantite, source_type, source_id, commentaire, utilisateur_id
        ) VALUES (
            :produit_id, :date_mouvement, :type_mouvement, :quantite, :source_type, :source_id, :commentaire, :utilisateur_id
        )"
    );

    $migrated = 0;
    foreach ($rows as $r) {
        $produit_id = (int)$r['produit_id'];
        $date_mouvement = $r['date_creation'] ?? ($r['date_mouvement'] . ' 00:00:00');
        $type = $r['type_mouvement'];
        if ($type === 'CORRECTION') $type = 'AJUSTEMENT';
        $quantite = (int)$r['quantite'];
        $source_type = $r['source_module'] ?? null;
        $source_id = $r['source_id'] ?? null;
        $commentaire = $r['commentaire'] ?? null;
        $utilisateur_id = $r['utilisateur_id'] ? (int)$r['utilisateur_id'] : 1;

        $insert->execute([
            ':produit_id' => $produit_id,
            ':date_mouvement' => $date_mouvement,
            ':type_mouvement' => $type,
            ':quantite' => $quantite,
            ':source_type' => $source_type,
            ':source_id' => $source_id,
            ':commentaire' => $commentaire,
            ':utilisateur_id' => $utilisateur_id,
        ]);
        $migrated++;
    }

    echo "<p>Importés <strong>$migrated</strong> enregistrements.</p>";

    // Recalculate produits.stock_actuel
    $updateStmt = $pdo->prepare(
        "UPDATE produits p
         LEFT JOIN (
            SELECT produit_id, COALESCE(SUM(
                CASE
                    WHEN type_mouvement = 'ENTREE' THEN quantite
                    WHEN type_mouvement = 'SORTIE' THEN -quantite
                    WHEN type_mouvement = 'AJUSTEMENT' THEN quantite
                    ELSE 0
                END
            ),0) AS qte
            FROM stocks_mouvements
            GROUP BY produit_id
         ) s ON p.id = s.produit_id
         SET p.stock_actuel = COALESCE(s.qte, 0)
    "
    );
    $updateStmt->execute();
    $rowsUpdated = $updateStmt->rowCount();
    echo "<p>Mise à jour de <strong>$rowsUpdated</strong> lignes dans <em>produits</em>.</p>";

    // Rename backup
    $backupName = 'mouvements_stock_backup_' . date('Ymd_His');
    $pdo->exec("RENAME TABLE mouvements_stock TO `$backupName`");
    echo "<p>Table renommée en <strong>$backupName</strong>.</p>";

    $pdo->commit();
    echo "<p style='color:green;'><strong>Migration exécutée avec succès.</strong></p>";

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<p style='color:red;'><strong>Erreur :</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<p>Supprime ou protège ce script après usage.</p>";
?>