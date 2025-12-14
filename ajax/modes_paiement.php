<?php
/**
 * API: Charger les modes de paiement disponibles
 * GET /ajax/modes_paiement.php
 * 
 * Response: Array de modes de paiement
 * [
 *   { "id": 1, "libelle": "Espèces" },
 *   { "id": 2, "libelle": "Chèque" },
 *   ...
 * ]
 */

require_once __DIR__ . '/../security.php';

exigerConnexion();
global $pdo;

// Charger modes de paiement
$stmt = $pdo->query("SELECT id, libelle FROM modes_paiement ORDER BY libelle");
$modes = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($modes ?? []);
?>
