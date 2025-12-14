<?php
// coordination/ordres_preparation_statut.php - Changer statut ordre préparation
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_MODIFIER');

global $pdo;

// CSRF protection + POST uniquement
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_error'] = "Méthode non autorisée (POST requis)";
    header('Location: ' . url_for('coordination/ordres_preparation.php'));
    exit;
}

verifierCsrf($_POST['csrf_token'] ?? '');

$id = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';

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
            // ✅ VALIDATIONS MÉTIER - surtout avant LIVRE
            if ($nouveauStatut === 'LIVRE') {
                // Vérifier qu'un bon de livraison (BL) existe pour cette vente
                $vente_id = $ordre['vente_id'] ?? null;
                if ($vente_id) {
                    $stmtBL = $pdo->prepare("SELECT COUNT(*) as cnt FROM bons_livraison WHERE vente_id = ? AND statut NOT IN ('ANNULE', 'BROUILLON')");
                    $stmtBL->execute([$vente_id]);
                    $bl_count = $stmtBL->fetch()['cnt'] ?? 0;
                    
                    if ($bl_count === 0) {
                        throw new Exception("Un bon de livraison validé est requis avant de marquer l'ordre comme LIVRE");
                    }
                }
                // Vérifier que l'ordre a au moins été préparé
                if ($ordre['date_preparation_effectuee'] === null) {
                    throw new Exception("L'ordre doit avoir été préparé avant d'être marqué LIVRE");
                }
            }
            
            $updates = ["statut = ?"];
            $params = [$nouveauStatut];
            
            // Horodatage spécifique
            if ($nouveauStatut === 'EN_PREPARATION') {
                $updates[] = "magasinier_id = ?";
                $params[] = $_SESSION['user_id'] ?? 1;
            } elseif ($nouveauStatut === 'PRET') {
                $updates[] = "date_preparation_effectuee = NOW()";
            } elseif ($nouveauStatut === 'LIVRE') {
                $updates[] = "date_livraison_effective = NOW()";
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
