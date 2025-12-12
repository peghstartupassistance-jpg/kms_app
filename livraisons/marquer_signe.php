<?php
// livraisons/marquer_signe.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_CREER'); // ou un droit plus spécifique si tu en crées un

global $pdo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url_for('livraisons/list.php'));
    exit;
}

try {
    verifierCsrf($_POST['csrf_token'] ?? '');

    $blId = isset($_POST['bl_id']) ? (int)$_POST['bl_id'] : 0;
    if ($blId <= 0) {
        throw new RuntimeException("Bon de livraison invalide.");
    }

    $stmt = $pdo->prepare("SELECT * FROM bons_livraison WHERE id = :id");
    $stmt->execute(['id' => $blId]);
    $bl = $stmt->fetch();

    if (!$bl) {
        throw new RuntimeException("Bon de livraison introuvable.");
    }

    if ((int)$bl['signe_client'] === 1) {
        $_SESSION['flash_success'] = "Ce bon de livraison est déjà marqué comme signé.";
    } else {
        $stmt = $pdo->prepare("
            UPDATE bons_livraison
            SET signe_client = 1
            WHERE id = :id
        ");
        $stmt->execute(['id' => $blId]);

        $_SESSION['flash_success'] = "Bon de livraison marqué comme signé.";
    }
} catch (Throwable $e) {
    $_SESSION['flash_error'] = $e->getMessage();
}

header('Location: ' . url_for('livraisons/list.php'));
exit;
