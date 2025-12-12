<?php
// tests/sync_compta_ventes_historique.php
// Synchronise la génération des écritures comptables pour toutes les ventes au statut LIVREE sans pièce comptable

require_once __DIR__ . '/../lib/compta.php';
require_once __DIR__ . '/../db/db.php'; // $pdo

function get_unsynced_livree_ventes(PDO $pdo) {
    $stmt = $pdo->query("SELECT v.id, v.numero FROM ventes v WHERE v.statut = 'LIVREE' AND NOT EXISTS (
        SELECT 1 FROM compta_pieces cp WHERE cp.reference_type = 'VENTE' AND cp.reference_id = v.id
    ) ORDER BY v.id ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$ventes = get_unsynced_livree_ventes($pdo);
if (empty($ventes)) {
    echo "Toutes les ventes LIVREE sont déjà synchronisées avec la comptabilité.\n";
    exit(0);
}

$ok = 0; $fail = 0;
foreach ($ventes as $v) {
    echo "Synchronisation vente #{$v['id']} ({$v['numero']})... ";
    try {
        if (compta_creer_ecritures_vente($pdo, $v['id'])) {
            echo "OK\n";
            $ok++;
        } else {
            echo "ECHEC\n";
            $fail++;
        }
    } catch (Throwable $e) {
        echo "ERREUR: " . $e->getMessage() . "\n";
        $fail++;
    }
}
echo "\nSynchronisation terminée : $ok OK, $fail ECHEC(s).\n";
