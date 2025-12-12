<?php
// coordination/ordres_preparation_statut.php - Changer statut ordre préparation
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_MODIFIER');

global $pdo;

$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

if (!$id || !$action) {
    $_SESSION['flash_error'] = "Paramètres manquants";
    header('Location: ' . url_for('coordination/ordres_preparation.php'));
    exit;
}

// Charger l'ordre
$stmt = $pdo->prepare("SELECT * FROM ordres_preparation WHERE id = ?");
$stmt->execute([$id]);
$ordre = $stmt->fetch();

if (!$ordre) {
    $_SESSION['flash_error'] = "Ordre introuvable";
    header('Location: ' . url_for('coordination/ordres_preparation.php'));
    exit;
}

try {
    if ($action === 'suivant') {
        // Passer au statut suivant
        $nouveauStatut = match($ordre['statut']) {
            'EN_ATTENTE' => 'EN_PREPARATION',
            'EN_PREPARATION' => 'PRET',
            'PRET' => 'LIVRE',
            default => null
        };
        
        if ($nouveauStatut) {
            $updates = ["statut = ?"];
            $params = [$nouveauStatut];
            
            // Horodatage spécifique
            if ($nouveauStatut === 'EN_PREPARATION') {
                $updates[] = "magasinier_id = ?";
                $params[] = $_SESSION['user_id'];
            } elseif ($nouveauStatut === 'PRET') {
                $updates[] = "date_preparation_effectuee = NOW()";
            } elseif ($nouveauStatut === 'LIVRE') {
                $updates[] = "date_preparation_effectuee = NOW()";
            }
            
            $params[] = $id;
            
            $sql = "UPDATE ordres_preparation SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $_SESSION['flash_success'] = "Statut changé en : $nouveauStatut";
        } else {
            $_SESSION['flash_warning'] = "Impossible de passer au statut suivant";
        }
    }
    
    header('Location: ' . url_for('coordination/ordres_preparation_edit.php?id=' . $id));
    exit;
    
} catch (Exception $e) {
    $_SESSION['flash_error'] = "Erreur : " . $e->getMessage();
    header('Location: ' . url_for('coordination/ordres_preparation_edit.php?id=' . $id));
    exit;
}
