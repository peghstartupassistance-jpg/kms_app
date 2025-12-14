<?php
// ventes/edit.php
require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../lib/stock.php';
require_once __DIR__ . '/../lib/caisse.php';

exigerConnexion();
exigerPermission('VENTES_CREER');

global $pdo;

$TAUX_TVA = 0.1925; // Ajuste si besoin

$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

// Canaux de vente
$stmt = $pdo->query("SELECT id, code, libelle FROM canaux_vente ORDER BY code");
$canaux = $stmt->fetchAll();

// Produits - Charger seulement ceux utilisés en mode édition
$produitsById = [];
if ($isEdit) {
    // Charger seulement les produits utilisés dans cette vente pour affichage
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.id, p.code_produit, p.designation, p.prix_vente
        FROM produits p
        JOIN ventes_lignes vl ON vl.produit_id = p.id
        WHERE vl.vente_id = ?
    ");
    $stmt->execute([$id]);
    $produitsExistants = $stmt->fetchAll();
    foreach ($produitsExistants as $p) {
        $produitsById[(int)$p['id']] = $p;
    }
}

// Valeurs par défaut
$data = [
    'date_vente'        => date('Y-m-d'),
    'client_id'         => '',
    'canal_vente_id'    => '',
    'statut'            => 'EN_ATTENTE_LIVRAISON',
    'commentaires'      => '',
];
$clientLabel = '';

$lignes = []; // lignes de vente

if ($isEdit) {
    // Charger la vente
    $stmt = $pdo->prepare("SELECT * FROM ventes WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $vente = $stmt->fetch();

    if (!$vente) {
        $_SESSION['flash_error'] = "Vente introuvable.";
        header('Location: ' . url_for('ventes/list.php'));
        exit;
    }

    $data = [
        'date_vente'      => $vente['date_vente'],
        'client_id'       => $vente['client_id'],
        'canal_vente_id'  => $vente['canal_vente_id'],
        'statut'          => $vente['statut'],
        'commentaires'    => $vente['commentaires'] ?? '',
    ];

    if ((int)$vente['client_id'] > 0) {
        $stmtCli = $pdo->prepare("SELECT nom, telephone, email FROM clients WHERE id = :id");
        $stmtCli->execute(['id' => $vente['client_id']]);
        if ($cli = $stmtCli->fetch()) {
            $parts = array_filter([$cli['nom'] ?? '', $cli['telephone'] ?? '', $cli['email'] ?? '']);
            $clientLabel = implode(' • ', $parts);
        }
    }

    // Charger les lignes
    $stmt = $pdo->prepare("
        SELECT vl.*, p.code_produit, p.designation
        FROM ventes_lignes vl
        JOIN produits p ON p.id = vl.produit_id
        WHERE vl.vente_id = :id
        ORDER BY vl.id
    ");
    $stmt->execute(['id' => $id]);
    $lignes = $stmt->fetchAll();
} else {
    // Par défaut, on prépare 3 lignes vides pour initialiser le formulaire
    $lignes = [
        ['produit_id' => '', 'quantite' => '', 'prix_unitaire' => '', 'remise' => ''],
        ['produit_id' => '', 'quantite' => '', 'prix_unitaire' => '', 'remise' => ''],
        ['produit_id' => '', 'quantite' => '', 'prix_unitaire' => '', 'remise' => ''],
    ];
}

$errors = [];

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf($_POST['csrf_token'] ?? '');

        $data['date_vente']     = trim($_POST['date_vente'] ?? '');
        $data['client_id']      = (int)($_POST['client_id'] ?? 0);
        $data['canal_vente_id'] = (int)($_POST['canal_vente_id'] ?? 0);
        $data['statut']         = $_POST['statut'] ?? 'EN_ATTENTE_LIVRAISON';
        $data['commentaires']   = trim($_POST['commentaires'] ?? '');
        $clientLabel            = trim($_POST['client_label'] ?? '');

        $produitIds = $_POST['produit_id'] ?? [];
        $qtes       = $_POST['quantite'] ?? [];
        $prixUnit   = $_POST['prix_unitaire'] ?? [];
        $remises    = $_POST['remise'] ?? [];

        // Reconstruction des lignes
        $lignes = [];
        $totalHT = 0.0;

        foreach ($produitIds as $idx => $prodIdRaw) {
            $prodId = (int)$prodIdRaw;
            $q      = (float)str_replace(',', '.', $qtes[$idx] ?? '0');
            $pu     = (float)str_replace(',', '.', $prixUnit[$idx] ?? '0');
            $rem    = (float)str_replace(',', '.', $remises[$idx] ?? '0');

            if ($prodId <= 0 || $q <= 0 || $pu <= 0) {
                continue; // on ignore les lignes incomplètes
            }

            $montantBrut = $pu * $q;
            $montantLigneHT = max(0, $montantBrut - $rem);

            $lignes[] = [
                'produit_id'    => $prodId,
                'quantite'      => $q,
                'prix_unitaire' => $pu,
                'remise'        => $rem,
                'montant_ligne_ht' => $montantLigneHT,
            ];

            $totalHT += $montantLigneHT;
        }

        // Validations
        if ($data['date_vente'] === '') {
            $errors[] = "La date de vente est obligatoire.";
        }
        if ($data['client_id'] <= 0) {
            $errors[] = "Veuillez sélectionner un client valide.";
        }
        if ($data['canal_vente_id'] <= 0) {
            $errors[] = "Le canal de vente est obligatoire.";
        }
        if (!in_array($data['statut'], ['EN_ATTENTE_LIVRAISON','PARTIELLEMENT_LIVREE','LIVREE','ANNULEE'], true)) {
            $errors[] = "Statut de vente invalide.";
        }
        if (empty($lignes)) {
            $errors[] = "La vente doit contenir au moins une ligne produit valide.";
        }

        $totalTTC = $totalHT * (1 + $TAUX_TVA);

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $utilisateur = utilisateurConnecte();
                $utilisateurId = $utilisateur['id'] ?? null;

                if ($isEdit) {
                    // UPDATE vente
                    $stmt = $pdo->prepare("
                        UPDATE ventes
                        SET date_vente = :date_vente,
                            client_id = :client_id,
                            canal_vente_id = :canal_vente_id,
                            statut = :statut,
                            montant_total_ht = :mtht,
                            montant_total_ttc = :mttc,
                            commentaires = :commentaires
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        'date_vente'     => $data['date_vente'],
                        'client_id'      => $data['client_id'],
                        'canal_vente_id' => $data['canal_vente_id'],
                        'statut'         => $data['statut'],
                        'mtht'           => $totalHT,
                        'mttc'           => $totalTTC,
                        'commentaires'   => $data['commentaires'] !== '' ? $data['commentaires'] : null,
                        'id'             => $id,
                    ]);

                    // Effacer les lignes existantes
                    $stmt = $pdo->prepare("DELETE FROM ventes_lignes WHERE vente_id = :id");
                    $stmt->execute(['id' => $id]);

                    // Réinsérer les lignes
                    $stmtL = $pdo->prepare("
                        INSERT INTO ventes_lignes
                        (vente_id, produit_id, quantite, prix_unitaire, remise, montant_ligne_ht)
                        VALUES
                        (:vente_id, :produit_id, :quantite, :prix_unitaire, :remise, :montant_ligne_ht)
                    ");
                    foreach ($lignes as $lg) {
                        $stmtL->execute([
                            'vente_id'        => $id,
                            'produit_id'      => $lg['produit_id'],
                            'quantite'        => $lg['quantite'],
                            'prix_unitaire'   => $lg['prix_unitaire'],
                            'remise'          => $lg['remise'],
                            'montant_ligne_ht'=> $lg['montant_ligne_ht'],
                        ]);
                    }


                    // 🔗 Synchronisation stock (sorties liées à cette vente)
                    stock_synchroniser_vente($pdo, $id);

                    // PAS de nouvelle écriture caisse/compta en édition pour éviter les doublons

                    $_SESSION['flash_success'] = "Vente mise à jour avec succès.";

                } else {
                    // Génération numéro de vente
                    $numero = 'V-' . date('Ymd-His');

                    // INSERT vente
                    $stmt = $pdo->prepare("
                        INSERT INTO ventes
                        (numero, date_vente, client_id, canal_vente_id, devis_id,
                         statut, montant_total_ht, montant_total_ttc, utilisateur_id, commentaires)
                        VALUES
                        (:numero, :date_vente, :client_id, :canal_vente_id, :devis_id,
                         :statut, :mtht, :mttc, :utilisateur_id, :commentaires)
                    ");
                    $stmt->execute([
                        'numero'         => $numero,
                        'date_vente'     => $data['date_vente'],
                        'client_id'      => $data['client_id'],
                        'canal_vente_id' => $data['canal_vente_id'],
                        'devis_id'       => null, // vente directe sans devis
                        'statut'         => $data['statut'],
                        'mtht'           => $totalHT,
                        'mttc'           => $totalTTC,
                        'utilisateur_id' => $utilisateurId,
                        'commentaires'   => $data['commentaires'] !== '' ? $data['commentaires'] : null,
                    ]);

                    $venteId = (int)$pdo->lastInsertId();

                    // INSERT lignes
                    $stmtL = $pdo->prepare("
                        INSERT INTO ventes_lignes
                        (vente_id, produit_id, quantite, prix_unitaire, remise, montant_ligne_ht)
                        VALUES
                        (:vente_id, :produit_id, :quantite, :prix_unitaire, :remise, :montant_ligne_ht)
                    ");
                    foreach ($lignes as $lg) {
                        $stmtL->execute([
                            'vente_id'        => $venteId,
                            'produit_id'      => $lg['produit_id'],
                            'quantite'        => $lg['quantite'],
                            'prix_unitaire'   => $lg['prix_unitaire'],
                            'remise'          => $lg['remise'],
                            'montant_ligne_ht'=> $lg['montant_ligne_ht'],
                        ]);
                    }


                    // 🔗 Synchronisation stock (sorties liées à cette vente)
                    stock_synchroniser_vente($pdo, $venteId);

                    // Encaissement géré via le modal dédié (api_encaisser.php) pour éviter les doubles écritures.

                    // Génération automatique des écritures comptables si statut LIVREE
                    if ($data['statut'] === 'LIVREE') {
                        require_once __DIR__ . '/../lib/compta.php';
                        try {
                            compta_creer_ecritures_vente($pdo, $venteId);
                        } catch (Throwable $e) {
                            error_log('Erreur génération écritures comptables vente: ' . $e->getMessage());
                        }
                    }

                    $_SESSION['flash_success'] = "Vente créée avec succès.";
                }

                $pdo->commit();
                header('Location: ' . url_for('ventes/list.php'));
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = "Erreur lors de l'enregistrement de la vente : " . $e->getMessage();
            }
        }
}

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="form-page-header">
        <h1 class="h4 mb-0">
            <?= $isEdit ? 'Modifier une vente' : 'Nouvelle vente directe' ?>
        </h1>
        <div class="d-flex gap-2">
            <?php if ($isEdit): ?>
                <!-- Navigation rapide vers modules liés -->
                <a href="<?= url_for('coordination/ordres_preparation_edit.php?vente_id=' . $id) ?>" 
                   class="btn btn-sm btn-outline-info"
                   title="Créer un ordre de préparation">
                    <i class="bi bi-box-seam"></i> Ordre préparation
                </a>
                <a href="<?= url_for('livraisons/create.php?vente_id=' . $id) ?>" 
                   class="btn btn-sm btn-outline-success"
                   title="Créer un bon de livraison">
                    <i class="bi bi-truck"></i> Créer livraison
                </a>
                <a href="<?= url_for('coordination/dashboard.php?vente_id=' . $id) ?>" 
                   class="btn btn-sm btn-outline-primary"
                   title="Voir dans coordination">
                    <i class="bi bi-diagram-3"></i> Coordination
                </a>
                
                <!-- Bouton Encaisser si vente > 0 et pas déjà encaissée -->
                <?php
                $statut_encaissement = $vente['statut_encaissement'] ?? 'ATTENTE_PAIEMENT';
                $montant_total = (float)($vente['montant_total_ttc'] ?? 0);
                if ($montant_total > 0 && $statut_encaissement === 'ATTENTE_PAIEMENT'):
                ?>
                    <button type="button" 
                            class="btn btn-sm btn-warning"
                            data-bs-toggle="modal" 
                            data-bs-target="#modalEncaissement"
                            data-vente-id="<?= $id ?>"
                            data-montant="<?= $montant_total ?>"
                            title="Enregistrer le paiement">
                        <i class="bi bi-cash-coin"></i> Encaisser
                    </button>
                <?php elseif ($statut_encaissement === 'ENCAISSE'): ?>
                    <span class="badge bg-success">✓ Encaissée</span>
                <?php endif; ?>
            <?php endif; ?>
            <a href="<?= url_for('ventes/list.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Retour à la liste
            </a>
            <a href="<?= url_for('caisse/reconciliation_jour.php?date=' . ($data['date_vente'] ?? date('Y-m-d'))) ?>" class="btn btn-outline-primary btn-sm" title="Réconciliation caisse">
                <i class="bi bi-clipboard2-check"></i> Réconciliation jour
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger py-2">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card form-card">
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small">Date de vente</label>
                        <input type="date" name="date_vente" class="form-control"
                               value="<?= htmlspecialchars($data['date_vente']) ?>" required>
                    </div>
                    <div class="col-md-4 position-relative">
                        <label class="form-label small">Client *</label>
                        <input type="hidden" name="client_id" id="client_id" value="<?= (int)$data['client_id'] ?>">
                        <input type="text"
                               name="client_label"
                               id="client_search"
                               class="form-control"
                               placeholder="Rechercher un client par nom, téléphone ou email..."
                               value="<?= htmlspecialchars($clientLabel) ?>"
                               autocomplete="off">
                        <div id="client_search_results"
                             class="list-group position-absolute w-100 shadow-sm autocomplete-results"
                             style="z-index: 1050; max-height: 250px; overflow-y: auto; display:none;"></div>
                        <div class="form-text">Tapez 2+ caractères puis sélectionnez un client (⇅ + Entrée possible).</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Canal de vente</label>
                        <select name="canal_vente_id" class="form-select" required>
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($canaux as $cv): ?>
                                <option value="<?= (int)$cv['id'] ?>"
                                    <?= (int)$data['canal_vente_id'] === (int)$cv['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cv['code']) ?> – <?= htmlspecialchars($cv['libelle']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Statut</label>
                        <select name="statut" class="form-select" required>
                            <?php foreach (['EN_ATTENTE_LIVRAISON','PARTIELLEMENT_LIVREE','LIVREE','ANNULEE'] as $s): ?>
                                <option value="<?= $s ?>" <?= $data['statut'] === $s ? 'selected' : '' ?>>
                                    <?= $s ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label small">Commentaires (facultatif)</label>
                        <textarea name="commentaires" class="form-control" rows="2"
                                  placeholder="Infos complémentaires sur la vente, conditions spécifiques..."><?= htmlspecialchars($data['commentaires']) ?></textarea>
                    </div>
                </div>

                <hr class="my-4">

                <h5 class="h6 mb-3">Lignes de vente</h5>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th style="width:40%">Produit</th>
                            <th style="width:10%">Qté</th>
                            <th style="width:20%">Prix unitaire</th>
                            <th style="width:15%">Remise (ligne)</th>
                            <th style="width:15%">Montant HT (indicatif)</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        // On affiche au moins 3 lignes, ou les lignes existantes + 1
                        $nbLignesAffichees = max(count($lignes), 3);
                        for ($i = 0; $i < $nbLignesAffichees; $i++):
                            $lg = $lignes[$i] ?? [
                                'produit_id' => '',
                                'quantite'   => '',
                                'prix_unitaire' => '',
                                'remise'     => '',
                                'montant_ligne_ht' => '',
                            ];
                            $prodId = (int)($lg['produit_id'] ?? 0);
                            $montantIndicatif = $lg['montant_ligne_ht'] ?? '';
                        ?>
                            <tr>
                                <td style="position: relative;">
                                    <input type="hidden" name="produit_id[]" class="ligne-produit-id" value="<?= $prodId ?>">
                                    <input type="text" 
                                           class="form-control ligne-produit-search" 
                                           placeholder="🔍 Rechercher un produit..."
                                           value="<?= isset($produitsById[$prodId]) ? htmlspecialchars($produitsById[$prodId]['code_produit'] . ' – ' . $produitsById[$prodId]['designation']) : '' ?>"
                                           data-prix="<?= isset($produitsById[$prodId]) ? (float)$produitsById[$prodId]['prix_vente'] : 0 ?>"
                                           autocomplete="off">
                                    <div class="autocomplete-results"></div>
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="quantite[]" class="form-control"
                                           value="<?= htmlspecialchars((string)$lg['quantite']) ?>">
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="prix_unitaire[]" class="form-control"
                                           value="<?= htmlspecialchars((string)$lg['prix_unitaire']) ?>">
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="remise[]" class="form-control"
                                           value="<?= htmlspecialchars((string)$lg['remise']) ?>">
                                </td>
                                <td>
                                    <?php if ($montantIndicatif !== ''): ?>
                                        <?= number_format((float)$montantIndicatif, 0, ',', ' ') ?> FCFA
                                    <?php else: ?>
                                        <span class="text-muted small">Calculé à l’enregistrement</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endfor; ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i>
                        <?= $isEdit ? 'Mettre à jour la vente' : 'Enregistrer la vente' ?>
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
// Recherche client en AJAX avec suggestions + navigation clavier
(function() {
    const input = document.getElementById('client_search');
    const hiddenId = document.getElementById('client_id');
    const resultsBox = document.getElementById('client_search_results');
    if (!input || !hiddenId || !resultsBox) return;

    const SEARCH_URL = '<?= url_for('ajax/clients_search.php') ?>';
    let timer = null;
    let items = [];
    let activeIndex = -1;

    function clearResults() {
        resultsBox.innerHTML = '';
        resultsBox.style.display = 'none';
        items = [];
        activeIndex = -1;
    }

    function selectItem(item) {
        hiddenId.value = item.id;
        input.value = item.label;
        clearResults();
    }

    function render(list) {
        resultsBox.innerHTML = '';
        items = list;
        activeIndex = -1;

        if (!list.length) {
            const empty = document.createElement('div');
            empty.className = 'list-group-item text-muted';
            empty.textContent = 'Aucun client trouvé';
            resultsBox.appendChild(empty);
            resultsBox.style.display = 'block';
            return;
        }

        list.forEach((row, idx) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action autocomplete-item';
            btn.textContent = row.label || ('Client #' + row.id);
            btn.addEventListener('click', () => selectItem(row));
            resultsBox.appendChild(btn);
        });

        resultsBox.style.display = 'block';
    }

    function updateActive(delta) {
        if (!items.length) return;
        activeIndex = (activeIndex + delta + items.length) % items.length;
        const buttons = resultsBox.querySelectorAll('.autocomplete-item');
        buttons.forEach((el, idx) => {
            el.classList.toggle('active', idx === activeIndex);
            if (idx === activeIndex) el.scrollIntoView({block: 'nearest'});
        });
    }

    input.addEventListener('input', function() {
        hiddenId.value = '';
        const q = this.value.trim();

        if (timer) clearTimeout(timer);
        if (q.length < 2) {
            clearResults();
            return;
        }

        timer = setTimeout(() => {
            fetch(SEARCH_URL + '?q=' + encodeURIComponent(q), {
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
                .then(res => res.json())
                .then(data => render(Array.isArray(data) ? data : []))
                .catch(() => clearResults());
        }, 250);
    });

    input.addEventListener('keydown', function(e) {
        if (!items.length) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); updateActive(1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); updateActive(-1); }
        else if (e.key === 'Enter') {
            if (activeIndex >= 0) {
                e.preventDefault();
                selectItem(items[activeIndex]);
            }
        } else if (e.key === 'Escape') {
            clearResults();
        }
    });

    document.addEventListener('click', (e) => {
        if (!resultsBox.contains(e.target) && e.target !== input) {
            clearResults();
        }
    });
})();

// Autocomplete produits avec AJAX
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.ligne-produit-search').forEach(input => {
        initProductSearch(input);
    });
});

function initProductSearch(input) {
    if (!input) return;
    
    let currentTimeout = null;
    const resultsDiv = input.nextElementSibling;
    const hiddenInput = input.previousElementSibling;
    
    input.addEventListener('input', function() {
        clearTimeout(currentTimeout);
        const term = this.value.trim();
        
        if (term.length < 2) {
            resultsDiv.innerHTML = '';
            resultsDiv.style.display = 'none';
            return;
        }
        
        currentTimeout = setTimeout(() => {
            fetch(`<?= url_for('api/produits_recherche.php') ?>?q=${encodeURIComponent(term)}`)
                .then(response => response.json())
                .then(data => {
                    showResults(data);
                })
                .catch(err => console.error('Erreur recherche produits:', err));
        }, 300);
    });
    
    function showResults(products) {
        if (!products || products.length === 0) {
            resultsDiv.innerHTML = '<div class="autocomplete-item text-muted">Aucun produit trouvé</div>';
            resultsDiv.style.display = 'block';
            return;
        }
        
        resultsDiv.innerHTML = products.map(p => `
            <div class="autocomplete-item" 
                 data-id="${p.id}" 
                 data-prix="${p.prix_vente}"
                 data-label="${p.label}">
                ${p.label}
                <span class="float-end text-primary">${Number(p.prix_vente).toLocaleString('fr-FR', {minimumFractionDigits: 0})} FCFA</span>
                <small class="d-block text-muted">Stock: ${p.stock_actuel}</small>
            </div>
        `).join('');
        resultsDiv.style.display = 'block';
        
        // Événements sur les résultats
        resultsDiv.querySelectorAll('.autocomplete-item[data-id]').forEach(item => {
            item.addEventListener('click', function() {
                const id = this.dataset.id;
                const prix = parseFloat(this.dataset.prix);
                const label = this.dataset.label;
                
                hiddenInput.value = id;
                input.value = label;
                input.dataset.prix = prix;
                resultsDiv.style.display = 'none';
                
                // Remplir automatiquement le prix
                const tr = input.closest('tr');
                const prixInput = tr.querySelector('input[name="prix_unitaire[]"]');
                if (prixInput && (!prixInput.value || prixInput.value == 0)) {
                    prixInput.value = prix;
                }
            });
        });
    }
    
    // Fermer au clic extérieur
    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !resultsDiv.contains(e.target)) {
            resultsDiv.style.display = 'none';
        }
    });
}
</script>

<style>
.autocomplete-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-top: none;
    max-height: 300px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.autocomplete-item {
    padding: 0.5rem;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
}

.autocomplete-item:hover {
    background-color: #f8f9fa;
}

.autocomplete-item:last-child {
    border-bottom: none;
}
</style>

<!-- Modal Encaissement -->
<div class="modal fade" id="modalEncaissement" tabindex="-1" aria-labelledby="modalEncaissementLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-warning bg-opacity-10">
                <h5 class="modal-title" id="modalEncaissementLabel">
                    <i class="bi bi-cash-coin me-2"></i>Encaisser la vente
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small">Montant à encaisser</label>
                    <div class="input-group">
                        <input type="number" step="0.01" id="encMontant" class="form-control" readonly>
                        <span class="input-group-text">FCFA</span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label small">Mode de paiement *</label>
                    <select id="encModePaiement" class="form-select" required>
                        <option value="">-- Sélectionner --</option>
                        <!-- Chargé en JS -->
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label small">Observations (facultatif)</label>
                    <textarea id="encObservations" class="form-control" rows="2" placeholder="Ex: Chèque client X, Ref: 12345"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-warning btn-sm" id="btnConfirmEncaissement">
                    <i class="bi bi-check2 me-1"></i>Confirmer encaissement
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Encaissement modal
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('modalEncaissement');
    const btnEncaisser = document.querySelector('[data-bs-target="#modalEncaissement"]');
    const encMontant = document.getElementById('encMontant');
    const encModePaiement = document.getElementById('encModePaiement');
    const encObservations = document.getElementById('encObservations');
    const btnConfirm = document.getElementById('btnConfirmEncaissement');
    
    let currentVenteId = null;
    
    // Charger les modes de paiement au démarrage
    loadModesPaiement();
    
    // Au clic sur "Encaisser", pré-remplir le montant
    if (btnEncaisser) {
        btnEncaisser.addEventListener('click', function() {
            currentVenteId = this.dataset.venteId;
            encMontant.value = parseFloat(this.dataset.montant).toFixed(2);
        });
    }
    
    // Clic "Confirmer encaissement"
    btnConfirm.addEventListener('click', function() {
        if (!currentVenteId || !encModePaiement.value) {
            alert('Veuillez sélectionner un mode de paiement');
            return;
        }
        
        const payload = {
            vente_id: currentVenteId,
            montant: parseFloat(encMontant.value),
            mode_paiement_id: parseInt(encModePaiement.value),
            observations: encObservations.value
        };
        
        // Afficher loading
        btnConfirm.disabled = true;
        btnConfirm.innerHTML = '<i class="bi bi-hourglass-split"></i> Traitement...';
        
        fetch('<?= url_for('ventes/api_encaisser.php') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Succès
                alert('✓ Encaissement enregistré!\nVous allez être redirigé.');
                window.location.href = '<?= url_for('ventes/list.php') ?>';
            } else {
                alert('❌ Erreur: ' + (data.message || 'Impossible d\'encaisser'));
                btnConfirm.disabled = false;
                btnConfirm.innerHTML = '<i class="bi bi-check2 me-1"></i>Confirmer encaissement';
            }
        })
        .catch(err => {
            console.error('Erreur:', err);
            alert('❌ Erreur réseau: ' + err.message);
            btnConfirm.disabled = false;
            btnConfirm.innerHTML = '<i class="bi bi-check2 me-1"></i>Confirmer encaissement';
        });
    });
    
    function loadModesPaiement() {
        fetch('<?= url_for('ajax/modes_paiement.php') ?>', {
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        })
        .then(res => res.json())
        .then(modes => {
            encModePaiement.innerHTML = '<option value="">-- Sélectionner --</option>';
            modes.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.id;
                opt.textContent = m.libelle;
                encModePaiement.appendChild(opt);
            });
        })
        .catch(err => console.error('Erreur chargement modes:', err));
    }
});
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>
