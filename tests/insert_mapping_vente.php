<?php
// Script d'insertion du mapping VENTE/VENTE_PRODUITS pour la comptabilité
require_once __DIR__ . '/../db/db.php';

// Recherche des comptes par nature
function get_compte_id_by_nature(PDO $pdo, $nature) {
    $stmt = $pdo->prepare("SELECT id FROM compta_comptes WHERE nature = ? ORDER BY id ASC LIMIT 1");
    $stmt->execute([$nature]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['id'] : null;
}
function insert_compte(PDO $pdo, $numero, $libelle, $classe, $type, $nature) {
    $stmt = $pdo->prepare("INSERT INTO compta_comptes (numero_compte, libelle, classe, type_compte, nature, est_actif) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->execute([$numero, $libelle, $classe, $type, $nature]);
    return (int)$pdo->lastInsertId();
}
function get_compte_id(PDO $pdo, $numero) {
    $stmt = $pdo->prepare("SELECT id FROM compta_comptes WHERE numero_compte = ? LIMIT 1");
    $stmt->execute([$numero]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['id'] : null;
}

// Ajout compte client 411 si absent
$compteClientId = get_compte_id($pdo, '411');
if (!$compteClientId) {
    $compteClientId = insert_compte($pdo, '411', 'Clients', '4', 'ACTIF', 'CREANCE');
    echo "Compte client 411 créé.\n";
}
// Ajout compte ventes 707 si absent
$compteVentesId = get_compte_id($pdo, '707');
if (!$compteVentesId) {
    $compteVentesId = insert_compte($pdo, '707', 'Ventes de marchandises', '7', 'PRODUIT', 'VENTE');
    echo "Compte ventes 707 créé.\n";
}

// Récupère le journal Ventes
$journalId = null;
$stmt = $pdo->prepare("SELECT id FROM compta_journaux WHERE code = 'VE' LIMIT 1");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) $journalId = (int)$row['id'];
if (!$journalId) {
    echo "Journal VE introuvable.\n";
    exit(1);
}
// Vérifie si le mapping existe déjà
$stmt = $pdo->prepare("SELECT id FROM compta_mapping_operations WHERE source_type = 'VENTE' AND code_operation = 'VENTE_PRODUITS'");
$stmt->execute();
if ($stmt->fetch()) {
    echo "Le mapping VENTE/VENTE_PRODUITS existe déjà.\n";
    exit(0);
}
// Insère le mapping
$stmt = $pdo->prepare("INSERT INTO compta_mapping_operations (source_type, code_operation, journal_id, compte_debit_id, compte_credit_id, description, actif) VALUES ('VENTE', 'VENTE_PRODUITS', ?, ?, ?, 'Ecritures vente standard', 1)");
$stmt->execute([$journalId, $compteClientId, $compteVentesId]);

echo "Mapping VENTE/VENTE_PRODUITS créé avec succès.\n";
