<?php
// coordination/verification_synchronisation.php - Vérifier la cohérence ventes ↔ stock ↔ caisse ↔ compta
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');

global $pdo;

// Récupérer toutes les ventes avec leurs montants
$stmt = $pdo->prepare("
    SELECT v.id, v.numero, v.date_vente, v.montant_total_ttc, c.nom as client_nom,
           (SELECT COUNT(*) FROM bons_livraison bl WHERE bl.vente_id = v.id) as nb_livraisons,
           (SELECT SUM(quantite) FROM ventes_lignes vl WHERE vl.vente_id = v.id) as total_quantite_commandee
    FROM ventes v
    JOIN clients c ON c.id = v.client_id
    ORDER BY v.date_vente DESC
    LIMIT 50
");
$stmt->execute();
$ventes = $stmt->fetchAll();

// Classe pour stocker les résultats de vérification
class VerificationVente {
    public $venteId;
    public $venteNumero;
    public $montantVente;
    public $montantLivraisons = 0;
    public $montantEncaissements = 0;
    public $montantRetours = 0;
    public $quantiteCommandee = 0;
    public $quantiteLivree = 0;
    public $quantiteStockSortie = 0;
    public $ecrituresComptables = 0;
    
    public function getCoherence() {
        $problemes = [];
        
        // Vérif 1: Montant livraisons vs Montant vente
        // Ignorer si vente entièrement livrée ET quantités correctes
        $tolerance = 1000; // 1000 FCFA de tolérance (TVA, arrondis)
        $venteEntierementLivree = ($this->quantiteLivree >= $this->quantiteCommandee && $this->quantiteCommandee > 0);
        
        if (!$venteEntierementLivree && abs($this->montantLivraisons - $this->montantVente) > $tolerance) {
            $ecart = $this->montantLivraisons - $this->montantVente;
            $problemes[] = "Montant livraisons (" . number_format($this->montantLivraisons, 0, ',', ' ') . ") ≠ Vente (" . number_format($this->montantVente, 0, ',', ' ') . ") - Écart: " . number_format($ecart, 0, ',', ' ') . " FCFA";
        }
        
        // Vérif 2: Quantités livrées vs Commandées (ne peut pas livrer plus que commandé)
        if ($this->quantiteLivree > $this->quantiteCommandee && $this->quantiteCommandee > 0) {
            $problemes[] = "Quantités livrées (" . $this->quantiteLivree . ") > Commandées (" . $this->quantiteCommandee . ") - Surlivraison impossible !";
        }
        
        // Vérif 3: Stock sorties vs Quantités livrées
        if ($this->quantiteStockSortie != $this->quantiteLivree) {
            $ecart = $this->quantiteStockSortie - $this->quantiteLivree;
            $problemes[] = "Sorties stock (" . $this->quantiteStockSortie . ") ≠ Livraisons (" . $this->quantiteLivree . ") - Écart: " . $ecart;
        }
        
        // Vérif 4: Écritures comptables obligatoires (uniquement si montant vente > 0)
        if ($this->ecrituresComptables == 0 && $this->montantVente > 0) {
            $problemes[] = "Aucune écriture comptable détectée (vente de " . number_format($this->montantVente, 0, ',', ' ') . " FCFA)";
        }
        
        return empty($problemes) ? true : $problemes;
    }
    
    public function getStatus() {
        $ok = $this->getCoherence();
        if ($ok === true) return 'OK';
        return 'ERREUR';
    }
}

// Analyser chaque vente
$analyses = [];
foreach ($ventes as $vente) {
    $verif = new VerificationVente();
    $verif->venteId = $vente['id'];
    $verif->venteNumero = $vente['numero'];
    $verif->montantVente = $vente['montant_total_ttc'];
    $verif->quantiteCommandee = $vente['total_quantite_commandee'] ?? 0;
    
    // Montant livraisons (basé sur les prix réels de la vente)
    $stmt = $pdo->prepare("SELECT SUM(bll.quantite * vl.prix_unitaire) AS total
                           FROM bons_livraison bl
                           JOIN bons_livraison_lignes bll ON bll.bon_livraison_id = bl.id
                           JOIN ventes_lignes vl ON vl.vente_id = bl.vente_id AND vl.produit_id = bll.produit_id
                           WHERE bl.vente_id = ?");
    $stmt->execute([$vente['id']]);
    $livr = $stmt->fetch();
    $verif->montantLivraisons = $livr['total'] ?? 0;
    
    // Quantités livrées
    $stmt = $pdo->prepare("SELECT SUM(bll.quantite) AS total
                           FROM bons_livraison_lignes bll
                           JOIN bons_livraison bl ON bl.id = bll.bon_livraison_id
                           WHERE bl.vente_id = ?");
    $stmt->execute([$vente['id']]);
    $qliv = $stmt->fetch();
    $verif->quantiteLivree = $qliv['total'] ?? 0;
    
    // Montant encaissements (unified to journal_caisse)
    $stmt = $pdo->prepare("SELECT SUM(montant) as total 
                           FROM journal_caisse 
                           WHERE vente_id = ? AND sens = 'RECETTE' AND est_annule = 0");
    $stmt->execute([$vente['id']]);
    $enc = $stmt->fetch();
    $verif->montantEncaissements = $enc['total'] ?? 0;
    
    // Montant retours
    $stmt = $pdo->prepare("SELECT SUM(montant_rembourse + montant_avoir) as total FROM retours_litiges WHERE vente_id = ?");
    $stmt->execute([$vente['id']]);
    $ret = $stmt->fetch();
    $verif->montantRetours = $ret['total'] ?? 0;
    
    // Sorties stock: utiliser la relation canonique source_type/source_id
    $stmt = $pdo->prepare("SELECT SUM(quantite) as total 
                           FROM stocks_mouvements 
                           WHERE source_type = 'VENTE' AND source_id = ? AND type_mouvement = 'SORTIE'");
    $stmt->execute([$vente['id']]);
    $stk = $stmt->fetch();
    $verif->quantiteStockSortie = $stk['total'] ?? 0;
    
    // Écritures comptables liées via compta_pieces (référence VENTE)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count
                           FROM compta_ecritures ce
                           JOIN compta_pieces cp ON cp.id = ce.piece_id
                           WHERE (cp.reference_type = 'VENTE' AND cp.reference_id = ?)");
    $stmt->execute([$vente['id']]);
    $ecr = $stmt->fetch();
    $verif->ecrituresComptables = $ecr['count'] ?? 0;
    
    $analyses[] = $verif;
}

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid p-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-1">Vérification de Synchronisation</h1>
            <p class="text-muted">Cohérence entre ventes, livraisons, stock et trésorerie</p>
        </div>
    </div>

    <!-- Statistiques globales -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="kms-kpi-card">
                <div class="kms-kpi-icon"><i class="bi bi-check-circle text-success"></i></div>
                <div class="kms-kpi-label">Ventes OK</div>
                <div class="kms-kpi-value">
                    <?= count(array_filter($analyses, fn($a) => $a->getStatus() === 'OK')) ?>
                </div>
                <div class="kms-kpi-subtitle">/ <?= count($analyses) ?> ventes</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kms-kpi-card">
                <div class="kms-kpi-icon"><i class="bi bi-exclamation-triangle text-danger"></i></div>
                <div class="kms-kpi-label">Anomalies</div>
                <div class="kms-kpi-value">
                    <?= count(array_filter($analyses, fn($a) => $a->getStatus() !== 'OK')) ?>
                </div>
                <div class="kms-kpi-subtitle">à vérifier</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kms-kpi-card">
                <div class="kms-kpi-icon"><i class="bi bi-cash-coin text-info"></i></div>
                <div class="kms-kpi-label">Total Encaissé</div>
                <div class="kms-kpi-value">
                    <?= number_format(array_sum(array_column($analyses, 'montantEncaissements')), 0, ',', ' ') ?>
                </div>
                <div class="kms-kpi-subtitle">FCFA</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kms-kpi-card">
                <div class="kms-kpi-icon"><i class="bi bi-boxes text-warning"></i></div>
                <div class="kms-kpi-label">Total Commandé</div>
                <div class="kms-kpi-value">
                    <?= array_sum(array_column($analyses, 'quantiteCommandee')) ?>
                </div>
                <div class="kms-kpi-subtitle">articles</div>
            </div>
        </div>
    </div>

    <!-- Tableau détaillé -->
    <div class="card">
        <div class="card-header">
            <strong>Détail des Ventes</strong>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 table-sm">
                <thead>
                    <tr>
                        <th>Vente #</th>
                        <th>Client</th>
                        <th>Montant</th>
                        <th>Livré</th>
                        <th>Encaissé</th>
                        <th>Qté Cmd</th>
                        <th>Qté Liv</th>
                        <th>Stock Out</th>
                        <th>Compta</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analyses as $ana): ?>
                        <?php $problemes = $ana->getCoherence(); $hasError = $problemes !== true; ?>
                        <tr class="<?= $hasError ? 'table-danger' : 'table-success' ?>">
                            <td>
                                <a href="<?= url_for('ventes/detail_360.php?id=' . $ana->venteId) ?>" class="text-decoration-none fw-bold">
                                    <?= htmlspecialchars($ana->venteNumero) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars(substr($ana->venteNumero, 0, 20)) ?></td>
                            <td class="text-end"><?= number_format($ana->montantVente, 0, ',', ' ') ?></td>
                            <td class="text-end"><?= number_format($ana->montantLivraisons, 0, ',', ' ') ?></td>
                            <td class="text-end text-success fw-bold"><?= number_format($ana->montantEncaissements, 0, ',', ' ') ?></td>
                            <td class="text-end"><?= (int)$ana->quantiteCommandee ?></td>
                            <td class="text-end"><?= (int)$ana->quantiteLivree ?></td>
                            <td class="text-end"><?= (int)$ana->quantiteStockSortie ?></td>
                            <td class="text-center">
                                <?php if ($ana->ecrituresComptables > 0): ?>
                                    <span class="badge bg-success"><?= $ana->ecrituresComptables ?></span>
                                <?php else: ?>
                                    <span class="badge bg-danger">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($hasError): ?>
                                    <span class="badge bg-danger">⚠️ ERREUR</span>
                                <?php else: ?>
                                    <span class="badge bg-success">✅ OK</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button class="btn btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#details-<?= $ana->venteId ?>" title="Détails">
                                        <i class="bi bi-chevron-down"></i>
                                    </button>
                                    <?php if ($hasError): ?>
                                    <a href="<?= url_for('coordination/corriger_synchronisation.php?vente_id=' . $ana->venteId) ?>" 
                                       class="btn btn-outline-warning" title="Corriger">
                                        <i class="bi bi-wrench"></i>
                                    </a>
                                    <?php endif; ?>
                                    <a href="<?= url_for('ventes/detail.php?id=' . $ana->venteId) ?>" 
                                       class="btn btn-outline-secondary" title="Voir vente">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <tr class="collapse" id="details-<?= $ana->venteId ?>">
                            <td colspan="11">
                                <?php if ($hasError): ?>
                                    <div class="alert alert-danger mb-2">
                                        <strong>Problèmes détectés :</strong>
                                        <ul class="mb-0 mt-2">
                                            <?php foreach ($problemes as $p): ?>
                                                <li><?= htmlspecialchars($p) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Détails complets de vérification -->
                                <div class="card border-info">
                                    <div class="card-body p-2">
                                        <h6 class="mb-2"><i class="bi bi-bar-chart"></i> Détails de synchronisation</h6>
                                        <div class="row g-2 small">
                                            <div class="col-md-3">
                                                <strong>Montants :</strong><br>
                                                Vente : <?= number_format($ana->montantVente, 0, ',', ' ') ?> FCFA<br>
                                                Livraisons : <?= number_format($ana->montantLivraisons, 0, ',', ' ') ?> FCFA<br>
                                                Encaissements : <?= number_format($ana->montantEncaissements, 0, ',', ' ') ?> FCFA
                                            </div>
                                            <div class="col-md-3">
                                                <strong>Quantités :</strong><br>
                                                Commandée : <?= (int)$ana->quantiteCommandee ?><br>
                                                Livrée : <?= (int)$ana->quantiteLivree ?><br>
                                                Stock sortie : <?= (int)$ana->quantiteStockSortie ?>
                                            </div>
                                            <div class="col-md-3">
                                                <strong>Comptabilité :</strong><br>
                                                Écritures : <?= $ana->ecrituresComptables ?><br>
                                                <?php if ($ana->ecrituresComptables > 0): ?>
                                                    <span class="badge bg-success">OK</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Manquant</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-3">
                                                <strong>État :</strong><br>
                                                <?php 
                                                $venteEntierementLivree = ($ana->quantiteLivree >= $ana->quantiteCommandee && $ana->quantiteCommandee > 0);
                                                $stockCoherent = ($ana->quantiteStockSortie == $ana->quantiteLivree);
                                                ?>
                                                Entièrement livrée : <?= $venteEntierementLivree ? '✅ Oui' : '❌ Non' ?><br>
                                                Stock cohérent : <?= $stockCoherent ? '✅ Oui' : '❌ Non' ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Instructions -->
    <div class="alert alert-info mt-4">
        <strong><i class="bi bi-info-circle"></i> Guide de correction :</strong>
        <ul class="mb-0 mt-2">
            <li><strong>🟢 Status OK :</strong> La vente est complètement synchronisée</li>
            <li><strong>🔴 Status ERREUR :</strong> Il existe une incohérence - Cliquez sur l'icône <i class="bi bi-wrench text-warning"></i> pour corriger</li>
            <li><i class="bi bi-chevron-down"></i> = voir les détails des problèmes</li>
            <li><i class="bi bi-wrench text-warning"></i> = lancer le workflow de correction automatique</li>
            <li><i class="bi bi-eye"></i> = voir la vente complète</li>
        </ul>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
