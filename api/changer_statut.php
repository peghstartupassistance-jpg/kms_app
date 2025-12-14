<?php
/**
 * API pour changer le statut d'une entité (client, devis, etc.)
 */
require_once __DIR__ . '/../security.php';
exigerConnexion();

header('Content-Type: application/json');

// Lire les données POST
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['entite'], $input['id'], $input['statut'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

$entite = $input['entite'];
$id = (int)$input['id'];
$nouveauStatut = $input['statut'];

global $pdo;

try {
    switch ($entite) {
        case 'client':
            // Vérifier permission
            if (!in_array('CLIENTS_CREER', $_SESSION['permissions'] ?? [], true)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permission refusée']);
                exit;
            }

            // Valider le statut
            $statutsValides = ['PROSPECT', 'CLIENT', 'APPRENANT', 'HOTE'];
            if (!in_array($nouveauStatut, $statutsValides)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Statut invalide']);
                exit;
            }

            // Mettre à jour
            $stmt = $pdo->prepare("UPDATE clients SET statut = :statut WHERE id = :id");
            $stmt->execute([
                'statut' => $nouveauStatut,
                'id' => $id
            ]);

            echo json_encode([
                'success' => true, 
                'message' => 'Statut modifié avec succès'
            ]);
            break;

        case 'devis':
            // Vérifier permission
            if (!in_array('DEVIS_MODIFIER', $_SESSION['permissions'] ?? [], true)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permission refusée']);
                exit;
            }

            // Valider le statut
            $statutsValides = ['EN_ATTENTE', 'ACCEPTE', 'REFUSE', 'ANNULE'];
            if (!in_array($nouveauStatut, $statutsValides)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Statut invalide']);
                exit;
            }

            // Mettre à jour
            $stmt = $pdo->prepare("UPDATE devis SET statut = :statut WHERE id = :id");
            $stmt->execute([
                'statut' => $nouveauStatut,
                'id' => $id
            ]);

            echo json_encode([
                'success' => true, 
                'message' => 'Statut du devis modifié avec succès'
            ]);
            break;

        case 'prospection':
            // Vérifier permission
            if (!in_array('CLIENTS_LIRE', $_SESSION['permissions'] ?? [], true)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permission refusée']);
                exit;
            }

            // Mettre à jour le résultat de prospection
            $stmt = $pdo->prepare("UPDATE prospections_terrain SET resultat = :statut WHERE id = :id");
            $stmt->execute([
                'statut' => $nouveauStatut,
                'id' => $id
            ]);

            echo json_encode([
                'success' => true, 
                'message' => 'Statut de la prospection modifié avec succès'
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Type d\'entité non supporté']);
            exit;
    }

} catch (PDOException $e) {
    error_log("Erreur changement statut: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
