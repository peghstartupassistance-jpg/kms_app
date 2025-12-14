<?php
/**
 * API Endpoint - Signature BL Électronique
 * POST: /livraisons/api_signer_bl.php
 * 
 * Reçoit la signature en base64 et l'enregistre en BD
 */

require_once __DIR__ . '/../security.php';
exigerConnexion();
// Signature d'un BL est une écriture côté ventes/livraisons
exigerPermission('VENTES_ECRIRE');

header('Content-Type: application/json; charset=utf-8');

global $pdo;

// Récupérer JSON du body
$data = json_decode(file_get_contents('php://input'), true);

// Vérifier CSRF (si envoyé via header ou champ token dans body)
// On supporte `X-CSRF-Token` ou `csrf` dans le payload
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($data['csrf'] ?? null);
if (!verifierCsrf($csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'CSRF invalide'
    ]);
    exit;
}

try {
    // Validation des paramètres
    // Schéma actuel: pas de colonne image; on valide la présence de bl_id
    // et éventuellement d'un indicateur/trace de signature
    if (!isset($data['bl_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Paramètre manquant: bl_id'
        ]);
        exit;
    }
    
    $blId = (int)$data['bl_id'];
    // On accepte optionnellement un nom client et une note
    $clientNom = trim((string)($data['client_nom'] ?? ''));
    $note = trim((string)($data['note'] ?? ''));
    
    // Vérifier que le BL existe
    $stmt = $pdo->prepare("SELECT id FROM bons_livraison WHERE id = ?");
    $stmt->execute([$blId]);
    $bl = $stmt->fetch();
    
    if (!$bl) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Bon de livraison non trouvé'
        ]);
        exit;
    }
    
    // Commencer transaction (transaction-aware)
    $startedTxn = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $startedTxn = true;
    }
    
    try {
        // Schéma actuel: `bons_livraison` possède `signe_client` (BOOLEAN) et `observations`.
        // On marque le BL comme signé et on journalise le nom client/notes dans observations (append non destructif).
        // Récupérer observations existantes
        $stmtSel = $pdo->prepare("SELECT observations, signe_client FROM bons_livraison WHERE id = ? FOR UPDATE");
        $stmtSel->execute([$blId]);
        $row = $stmtSel->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception('BL introuvable lors de la mise à jour');
        }

        if ((int)$row['signe_client'] === 1) {
            // Déjà signé: idempotence
            if ($startedTxn) {
                $pdo->commit();
            }
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'bl_id' => $blId,
                'message' => 'BL déjà signé',
                'client_nom' => $clientNom,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            error_log("ℹ️ BL #$blId déjà signé");
            exit;
        }

        $observationsOld = (string)($row['observations'] ?? '');
        $journalLine = '[Signature BL] ' . date('Y-m-d H:i') . (
            $clientNom !== '' ? ' - Client: ' . $clientNom : ''
        ) . ($note !== '' ? ' - Note: ' . $note : '');
        $observationsNew = trim($observationsOld === '' ? $journalLine : ($observationsOld . "\n" . $journalLine));

        // Mise à jour
        $stmtUpd = $pdo->prepare("UPDATE bons_livraison SET signe_client = 1, observations = :obs WHERE id = :id");
        $stmtUpd->bindValue(':obs', $observationsNew, PDO::PARAM_STR);
        $stmtUpd->bindValue(':id', $blId, PDO::PARAM_INT);
        if (!$stmtUpd->execute()) {
            throw new Exception('Erreur UPDATE BL: ' . implode(', ', $stmtUpd->errorInfo()));
        }

        // Committer la transaction si démarrée ici
        if ($startedTxn) {
            $pdo->commit();
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'bl_id' => $blId,
            'client_nom' => $clientNom,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        // Log
        error_log("✅ BL #$blId signé (client: $clientNom)");
        
    } catch (Exception $e) {
        if ($startedTxn && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur: ' . $e->getMessage()
    ]);
    error_log('❌ Erreur API signature: ' . $e->getMessage());
}

exit;
