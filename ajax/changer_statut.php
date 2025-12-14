<?php
// ajax/changer_statut.php - Modification rapide des statuts pour tunnel de conversion
require_once __DIR__ . '/../security.php';
exigerConnexion();

header('Content-Type: application/json');
global $pdo;

try {
    // Vérifier CSRF
    $headers = getallheaders();
    $csrfToken = $headers['X-CSRF-Token'] ?? '';
    if (!verifierCsrf($csrfToken)) {
        throw new Exception("Token CSRF invalide");
    }

    // Récupérer les données JSON
    $data = json_decode(file_get_contents('php://input'), true);
    $entite = $data['entite'] ?? '';
    $id = (int)($data['id'] ?? 0);
    $nouveauStatut = $data['nouveau_statut'] ?? '';

    if ($id <= 0) {
        throw new Exception("ID invalide");
    }

    // Traiter selon l'entité
    switch ($entite) {
        case 'client':
            exigerPermission('CLIENTS_MODIFIER');
            
            $statutsValides = ['PROSPECT', 'CLIENT', 'APPRENANT', 'HOTE'];
            if (!in_array($nouveauStatut, $statutsValides, true)) {
                throw new Exception("Statut client invalide");
            }
            
            $stmt = $pdo->prepare("UPDATE clients SET statut = ? WHERE id = ?");
            $stmt->execute([$nouveauStatut, $id]);
            
            // Récupérer le nom pour le message
            $stmt = $pdo->prepare("SELECT nom FROM clients WHERE id = ?");
            $stmt->execute([$id]);
            $nom = $stmt->fetchColumn();
            
            $message = "Client $nom passé en statut : " . str_replace('_', ' ', $nouveauStatut);
            break;

        case 'devis':
            exigerPermission('DEVIS_MODIFIER');
            
            $statutsValides = ['EN_ATTENTE', 'ACCEPTE', 'REFUSE', 'ANNULE'];
            if (!in_array($nouveauStatut, $statutsValides, true)) {
                throw new Exception("Statut devis invalide");
            }
            
            $stmt = $pdo->prepare("UPDATE devis SET statut = ? WHERE id = ?");
            $stmt->execute([$nouveauStatut, $id]);
            
            // Récupérer le numéro pour le message
            $stmt = $pdo->prepare("SELECT numero FROM devis WHERE id = ?");
            $stmt->execute([$id]);
            $numero = $stmt->fetchColumn();
            
            $message = "Devis $numero passé en statut : " . str_replace('_', ' ', $nouveauStatut);
            break;

        case 'prospection':
            exigerPermission('TERRAIN_MODIFIER');
            
            // Pour les prospections, on met à jour le champ "resultat"
            $resultatsValides = [
                'Intéressé - à recontacter',
                'Devis demandé',
                'À rappeler plus tard',
                'Non intéressé',
                'Converti en client',
                'Perdu'
            ];
            
            if (!in_array($nouveauStatut, $resultatsValides, true)) {
                throw new Exception("Résultat de prospection invalide");
            }
            
            $stmt = $pdo->prepare("UPDATE prospections_terrain SET resultat = ? WHERE id = ?");
            $stmt->execute([$nouveauStatut, $id]);
            
            // Récupérer le nom du prospect
            $stmt = $pdo->prepare("SELECT prospect_nom FROM prospections_terrain WHERE id = ?");
            $stmt->execute([$id]);
            $nom = $stmt->fetchColumn();
            
            $message = "Prospection $nom mise à jour : $nouveauStatut";
            break;

        case 'prospect_formation':
            exigerPermission('FORMATION_MODIFIER');
            
            $statutsValides = [
                'Nouveau contact',
                'En cours',
                'Devis envoyé',
                'Inscrit',
                'Perdu',
                'Reporté'
            ];
            
            if (!in_array($nouveauStatut, $statutsValides, true)) {
                throw new Exception("Statut prospect formation invalide");
            }
            
            $stmt = $pdo->prepare("UPDATE prospects_formation SET statut_actuel = ? WHERE id = ?");
            $stmt->execute([$nouveauStatut, $id]);
            
            // Récupérer le nom
            $stmt = $pdo->prepare("SELECT nom_prospect FROM prospects_formation WHERE id = ?");
            $stmt->execute([$id]);
            $nom = $stmt->fetchColumn();
            
            $message = "Prospect formation $nom : $nouveauStatut";
            break;

        default:
            throw new Exception("Type d'entité non reconnu");
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'nouveau_statut' => $nouveauStatut
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
