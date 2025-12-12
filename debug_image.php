<?php
// Script de diagnostic pour vérifier les images
require_once __DIR__ . '/db/db.php';

$id = (int)($_GET['id'] ?? 1);

// Récupérer les infos du produit
$stmt = $pdo->prepare("SELECT id, code_produit, image_path FROM produits WHERE id = :id");
$stmt->execute(['id' => $id]);
$p = $stmt->fetch();

if (!$p) {
    die("Produit non trouvé");
}

echo "<h2>Diagnostic Image - Produit ID: $id</h2>";
echo "<pre>";
echo "Code produit: " . htmlspecialchars($p['code_produit']) . "\n";
echo "Image path en BD: " . htmlspecialchars($p['image_path']) . "\n";
echo "\n";

// Vérifier si le fichier existe
$basePath = __DIR__;
$testPaths = [
    $p['image_path'],
    $basePath . $p['image_path'],
    $basePath . '/assets/img/produits/' . $p['code_produit'] . '.png',
    $basePath . '/assets/img/produits/' . $p['code_produit'] . '.jpg',
    $basePath . '/assets/img/produits/' . $p['code_produit'] . '.jpeg',
];

echo "Chemins testés:\n";
foreach ($testPaths as $path) {
    $exists = file_exists($path) ? '✓ EXISTE' : '✗ N\'existe pas';
    echo "$exists: $path\n";
}

echo "\n";
echo "Fichiers dans assets/img/produits/:\n";
$dir = $basePath . '/assets/img/produits/';
if (is_dir($dir)) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            echo "  - $file\n";
        }
    }
} else {
    echo "  Dossier n'existe pas!\n";
}

echo "</pre>";
?>
