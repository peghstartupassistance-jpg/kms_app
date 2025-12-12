<?php
// Ajoute le mapping ACHAT/ACHAT_STOCK et synchronise les écritures pour tous les achats non synchronisés
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../lib/compta.php';

function insert_compte_if_missing(PDO $pdo, $numero, $libelle, $classe, $type, $nature) {
    $stmt = $pdo->prepare("SELECT id FROM compta_comptes WHERE numero_compte = ? LIMIT 1");
    $stmt->execute([$numero]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return (int)$row['id'];
    $stmt = $pdo->prepare("INSERT INTO compta_comptes (numero_compte, libelle, classe, type_compte, nature, est_actif) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->execute([$numero, $libelle, $classe, $type, $nature]);
    return (int)$pdo->lastInsertId();
}

// Ajout compte fournisseur 401 si absent
$compteFournisseurId = insert_compte_if_missing($pdo, '401', 'Fournisseurs', '4', 'PASSIF', 'DETTE');
// Ajout compte achat 607 si absent
$compteAchatId = insert_compte_if_missing($pdo, '607', 'Achats de marchandises', '6', 'CHARGE', 'CHARGE_VARIABLE');
// Récupère le journal Achats
$stmt = $pdo->prepare("SELECT id FROM compta_journaux WHERE code = 'AC' LIMIT 1");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$journalId = $row ? (int)$row['id'] : null;
if (!$journalId) {
    echo "Journal AC introuvable.\n";
    exit(1);
}
// Vérifie si le mapping existe déjà
$stmt = $pdo->prepare("SELECT id FROM compta_mapping_operations WHERE source_type = 'ACHAT' AND code_operation = 'ACHAT_STOCK'");
$stmt->execute();
if (!$stmt->fetch()) {
    $stmt = $pdo->prepare("INSERT INTO compta_mapping_operations (source_type, code_operation, journal_id, compte_debit_id, compte_credit_id, description, actif) VALUES ('ACHAT', 'ACHAT_STOCK', ?, ?, ?, 'Ecritures achat standard', 1)");
    $stmt->execute([$journalId, $compteAchatId, $compteFournisseurId]);
    echo "Mapping ACHAT/ACHAT_STOCK créé.\n";
}
// Synchronise les achats non synchronisés
$stmt = $pdo->query("SELECT id FROM achats WHERE id NOT IN (SELECT reference_id FROM compta_pieces WHERE reference_type = 'ACHAT')");
$achats = $stmt->fetchAll(PDO::FETCH_ASSOC);
$ok = 0; $fail = 0;
foreach ($achats as $a) {
    echo "Synchronisation achat #{$a['id']}... ";
    if (compta_creer_ecritures_achat($pdo, $a['id'])) {
        echo "OK\n";
        $ok++;
    } else {
        echo "ECHEC\n";
        $fail++;
    }
}
echo "Synchronisation terminée : $ok OK, $fail ECHEC(s).\n";
