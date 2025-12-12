<?php
// utilisateurs/toggle_actif.php - AJAX endpoint pour activer/désactiver un utilisateur
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('UTILISATEURS_GERER');

header('Content-Type: application/json');

global $pdo;

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validation
    if (!isset($input['user_id']) || !isset($input['actif']) || !isset($input['csrf_token'])) {
        throw new Exception('Données manquantes');
    }
    
    verifierCsrf($input['csrf_token']);
    
    $userId = (int)$input['user_id'];
    $actif = (int)$input['actif'];
    
    // Ne pas permettre de désactiver l'admin
    $stmt = $pdo->prepare("SELECT login FROM utilisateurs WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception('Utilisateur introuvable');
    }
    
    if ($user['login'] === 'admin' && $actif == 0) {
        throw new Exception('Impossible de désactiver le compte admin principal');
    }
    
    // Mise à jour
    $stmt = $pdo->prepare("UPDATE utilisateurs SET actif = ? WHERE id = ?");
    $stmt->execute([$actif, $userId]);
    
    echo json_encode([
        'success' => true,
        'message' => $actif ? 'Utilisateur activé avec succès' : 'Utilisateur désactivé avec succès'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
