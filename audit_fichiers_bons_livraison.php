<?php
/**
 * AUDIT COMPLET - Tous les fichiers utilisant bons_livraison
 * Cherche les références à des colonnes qui n'existent pas
 */

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  AUDIT COMPLET - Fichiers utilisant bons_livraison            ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$fichiers = [
    'livraisons/api_signer_bl.php' => ['colonnes_dangereuses' => ['signature', 'numero_bl']],
    'test_phase1_2.php' => ['colonnes_dangereuses' => ['signature']],
    'coordination/livraisons.php' => ['colonnes_dangereuses' => ['signature', 'numero_bl']],
    'coordination/dashboard_magasinier.php' => ['colonnes_dangereuses' => ['signature', 'numero_bl']],
    'ventes/detail_360.php' => ['colonnes_dangereuses' => ['signature', 'numero_bl']],
    'ventes/generer_bl.php' => ['colonnes_dangereuses' => ['signature', 'numero_bl']],
    'ventes/detail.php' => ['colonnes_dangereuses' => ['signature', 'numero_bl']],
    'magasin/dashboard.php' => ['colonnes_dangereuses' => ['signature', 'numero_bl']],
    'livraisons/detail_navigation.php' => ['colonnes_dangereuses' => ['signature', 'numero_bl']],
    'livraisons/list.php' => ['colonnes_dangereuses' => ['signature', 'numero_bl']],
];

$problemes = [];

foreach ($fichiers as $fichier => $config) {
    $chemin = __DIR__ . '/' . $fichier;
    
    if (!file_exists($chemin)) {
        echo "⚠️  " . $fichier . " - FICHIER NON TROUVÉ\n";
        continue;
    }
    
    $contenu = file_get_contents($chemin);
    
    // Chercher les colonnes dangereuses
    foreach ($config['colonnes_dangereuses'] as $col) {
        // Chercher les patterns dangereux dans les requêtes SQL et le code PHP
        $patterns = [
            // Requêtes SQL
            "SELECT.*\b$col\b",
            "\b$col\b\s*AS\s*\w+",
            // Code PHP
            "\\['$col'\\]",
            "\\[\"$col\"\\]",
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match('/' . $pattern . '/i', $contenu)) {
                // Vérifier si c'est vraiment un problème (colonne existe?)
                $problemes[] = [
                    'fichier' => $fichier,
                    'colonne' => $col,
                    'pattern' => $pattern
                ];
            }
        }
    }
}

if (empty($problemes)) {
    echo "✅ Aucun problème détecté dans les fichiers utilisant bons_livraison\n";
    exit(0);
} else {
    echo "⚠️ Problèmes potentiels détectés:\n\n";
    foreach ($problemes as $p) {
        echo "  " . $p['fichier'] . " - Référence à '{$p['colonne']}'\n";
    }
    exit(0);
}
