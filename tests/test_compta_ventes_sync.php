<?php
// tests/test_compta_ventes_sync.php
// Teste la génération automatique des écritures comptables lors de la validation d'une vente (statut LIVREE)

require_once __DIR__ . '/../lib/compta.php';
require_once __DIR__ . '/../db/db.php'; // $pdo

function get_last_livree_vente(PDO $pdo) {
    $stmt = $pdo->query("SELECT id, numero, date_vente, montant_total_ttc FROM ventes WHERE statut = 'LIVREE' ORDER BY id DESC LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_compta_piece_for_vente(PDO $pdo, $vente_id) {
    $stmt = $pdo->prepare("SELECT * FROM compta_pieces WHERE reference_type = 'VENTE' AND reference_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$vente_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_compta_ecritures_for_piece(PDO $pdo, $piece_id) {
    $stmt = $pdo->prepare("SELECT * FROM compta_ecritures WHERE piece_id = ? ORDER BY ordre_ligne ASC");
    $stmt->execute([$piece_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$vente = get_last_livree_vente($pdo);
if (!$vente) {
    echo "Aucune vente LIVREE trouvée. Veuillez en valider une avant de tester.\n";
    exit(1);
}

$piece = get_compta_piece_for_vente($pdo, $vente['id']);
if (!$piece) {
    echo "Aucune pièce comptable générée pour la vente #{$vente['id']} ({$vente['numero']}).\n";
    exit(2);
}

$ecritures = get_compta_ecritures_for_piece($pdo, $piece['id']);
if (empty($ecritures)) {
    echo "Aucune écriture comptable trouvée pour la pièce #{$piece['id']}.\n";
    exit(3);
}

// Affichage du résultat
echo "Vente LIVREE : #{$vente['id']} ({$vente['numero']})\n";
echo "Pièce comptable générée : #{$piece['id']} ({$piece['numero_piece']})\n";
echo "Date pièce : {$piece['date_piece']}\n";
echo "Montant TTC vente : {$vente['montant_total_ttc']}\n";
echo "Ecritures comptables :\n";
foreach ($ecritures as $e) {
    echo "  - Ligne #{$e['ordre_ligne']}: Compte #{$e['compte_id']} | Débit: {$e['debit']} | Crédit: {$e['credit']} | Libellé: {$e['libelle_ecriture']}\n";
}
echo "\nTest terminé avec succès.\n";
