<?php
// coordination/litiges.php - Gestion des retours et litiges clients
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');

global $pdo;

$utilisateur = utilisateurConnecte();

// Filtres
$statut = $_GET['statut'] ?? '';
$type = $_GET['type'] ?? '';
$dateDebut = $_GET['date_debut'] ?? '';
$dateFin = $_GET['date_fin'] ?? '';

$where = [];
$params = [];

if ($statut !== '') {
    $where[] = "rl.statut_traitement = :statut";
    $params['statut'] = $statut;
}

if ($type !== '') {
    $where[] = "rl.type_probleme = :type";
    $params['type'] = $type;
}

if ($dateDebut !== '') {
    $where[] = "rl.date_retour >= :date_debut";
    $params['date_debut'] = $dateDebut;
}

if ($dateFin !== '') {
    $where[] = "rl.date_retour <= :date_fin";
    $params['date_fin'] = $dateFin;
}

$whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT rl.*,
           c.nom AS client_nom,
           c.telephone AS client_telephone,
           v.numero AS numero_vente,
           p.code_produit,
           p.designation AS produit_designation,
           u.nom_complet AS responsable
    FROM retours_litiges rl
    INNER JOIN clients c ON rl.client_id = c.id
    LEFT JOIN ventes v ON rl.vente_id = v.id
    LEFT JOIN produits p ON rl.produit_id = p.id
    LEFT JOIN utilisateurs u ON rl.responsable_suivi_id = u.id
    $whereSql
    ORDER BY 
        CASE rl.statut_traitement
            WHEN 'EN_COURS' THEN 1
            WHEN 'RESOLU' THEN 2
            WHEN 'REMPLACEMENT_EFFECTUE' THEN 3
            WHEN 'REMBOURSEMENT_EFFECTUE' THEN 4
            WHEN 'ABANDONNE' THEN 5
        END,
        rl.date_retour DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$litiges = $stmt->fetchAll();

// Statistiques
$sqlStats = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut_traitement = 'EN_COURS' THEN 1 ELSE 0 END) as en_cours,
        SUM(CASE WHEN statut_traitement IN ('RESOLU', 'REMPLACEMENT_EFFECTUE', 'REMBOURSEMENT_EFFECTUE') THEN 1 ELSE 0 END) as resolus,
        SUM(montant_rembourse) as total_rembourse
    FROM retours_litiges
    $whereSql
";
$stmtStats = $pdo->prepare($sqlStats);
$stmtStats->execute($params);
$stats = $stmtStats->fetch();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>
<script>
// Helpers CSRF
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
async function postForm(url, data) {
    console.log('postForm called:', url, data);
    const form = new FormData();
    Object.entries(data).forEach(([k,v]) => form.append(k, v));
    form.append('csrf_token', csrfToken);
    console.log('FormData prepared, sending...');
    const res = await fetch(url, { method: 'POST', body: form });
    console.log('Response status:', res.status);
    const text = await res.text();
    console.log('Response text (first 500 chars):', text.substring(0, 500));
    try {
        const json = JSON.parse(text);
        console.log('Response JSON:', json);
        return json;
    } catch (e) {
        console.error('Invalid JSON response:', text);
        throw new Error('Réponse invalide du serveur: ' + text.substring(0, 200));
    }
}
</script>

<div class="container-fluid">
    <h1 class="h4 mb-4">
        <i class="bi bi-arrow-left-right"></i> Retours & Litiges Clients
    </h1>

    <div class="d-flex mb-3 gap-2">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNouveauLitige">
            <i class="bi bi-plus-circle"></i> Nouveau litige
        </button>
    </div>

    <!-- Statistiques -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body p-3">
                    <div class="text-muted small">Total litiges</div>
                    <div class="fs-5 fw-bold"><?= number_format($stats['total']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body p-3">
                    <div class="text-muted small">En cours</div>
                    <div class="fs-5 fw-bold text-warning"><?= number_format($stats['en_cours']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body p-3">
                    <div class="text-muted small">Résolus</div>
                    <div class="fs-5 fw-bold text-success"><?= number_format($stats['resolus']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body p-3">
                    <div class="text-muted small">Total remboursé</div>
                    <div class="fs-5 fw-bold"><?= number_format($stats['total_rembourse'], 0, ',', ' ') ?> FCFA</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small">Statut</label>
                    <select name="statut" class="form-select form-select-sm">
                        <option value="">Tous</option>
                        <option value="EN_COURS" <?= $statut === 'EN_COURS' ? 'selected' : '' ?>>En cours</option>
                        <option value="RESOLU" <?= $statut === 'RESOLU' ? 'selected' : '' ?>>Résolu</option>
                        <option value="REMPLACEMENT_EFFECTUE" <?= $statut === 'REMPLACEMENT_EFFECTUE' ? 'selected' : '' ?>>Remplacement effectué</option>
                        <option value="REMBOURSEMENT_EFFECTUE" <?= $statut === 'REMBOURSEMENT_EFFECTUE' ? 'selected' : '' ?>>Remboursement effectué</option>
                        <option value="ABANDONNE" <?= $statut === 'ABANDONNE' ? 'selected' : '' ?>>Abandonné</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Type</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">Tous</option>
                        <option value="DEFAUT_PRODUIT" <?= $type === 'DEFAUT_PRODUIT' ? 'selected' : '' ?>>Défaut produit</option>
                        <option value="LIVRAISON_NON_CONFORME" <?= $type === 'LIVRAISON_NON_CONFORME' ? 'selected' : '' ?>>Livraison non conforme</option>
                        <option value="RETARD_LIVRAISON" <?= $type === 'RETARD_LIVRAISON' ? 'selected' : '' ?>>Retard livraison</option>
                        <option value="ERREUR_COMMANDE" <?= $type === 'ERREUR_COMMANDE' ? 'selected' : '' ?>>Erreur commande</option>
                        <option value="INSATISFACTION_CLIENT" <?= $type === 'INSATISFACTION_CLIENT' ? 'selected' : '' ?>>Insatisfaction</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Du</label>
                    <input type="date" name="date_debut" class="form-control form-control-sm" value="<?= htmlspecialchars($dateDebut) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Au</label>
                    <input type="date" name="date_fin" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFin) ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="bi bi-search"></i> Filtrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des litiges -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle small">
                    <thead class="table-light">
                        <tr>
                            <th>N° Litige</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Vente</th>
                            <th>Produit</th>
                            <th>Type problème</th>
                            <th>Motif</th>
                            <th>Solution</th>
                            <th>Statut</th>
                            <th>Responsable</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($litiges)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">
                                    <i class="bi bi-check-circle fs-1 text-success d-block mb-2"></i>
                                    Aucun litige enregistré
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($litiges as $litige): ?>
                                <?php
                                $badges = [
                                    'EN_COURS' => 'warning',
                                    'RESOLU' => 'success',
                                    'REMPLACEMENT_EFFECTUE' => 'info',
                                    'REMBOURSEMENT_EFFECTUE' => 'primary',
                                    'ABANDONNE' => 'secondary'
                                ];
                                $badgeClass = $badges[$litige['statut_traitement']] ?? 'secondary';
                                
                                $typeBadges = [
                                    'DEFAUT_PRODUIT' => 'danger',
                                    'LIVRAISON_NON_CONFORME' => 'warning',
                                    'RETARD_LIVRAISON' => 'warning',
                                    'ERREUR_COMMANDE' => 'danger',
                                    'INSATISFACTION_CLIENT' => 'info'
                                ];
                                $typeProbleme = $litige['type_probleme'] ?? 'AUTRE';
                                $typeBadgeClass = $typeBadges[$typeProbleme] ?? 'secondary';
                                ?>
                                <tr>
                                    <td class="fw-semibold">#<?= (int)$litige['id'] ?></td>
                                    <td class="text-nowrap"><?= date('d/m/Y', strtotime($litige['date_retour'])) ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($litige['client_nom']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($litige['client_telephone'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <?php if ($litige['numero_vente']): ?>
                                            <a href="<?= url_for('ventes/detail.php?id=' . $litige['vente_id']) ?>">
                                                <?= htmlspecialchars($litige['numero_vente']) ?>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($litige['code_produit']): ?>
                                            <div><?= htmlspecialchars($litige['code_produit']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($litige['produit_designation']) ?></small>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $typeBadgeClass ?>">
                                            <?= str_replace('_', ' ', $typeProbleme) ?>
                                        </span>
                                    </td>
                                    <td style="max-width: 200px;">
                                        <small><?= htmlspecialchars(substr($litige['motif'] ?? '', 0, 100)) ?><?= strlen($litige['motif'] ?? '') > 100 ? '...' : '' ?></small>
                                    </td>
                                    <td style="max-width: 150px;">
                                        <?php if (!empty($litige['solution'])): ?>
                                            <small><?= htmlspecialchars(substr($litige['solution'], 0, 80)) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $badgeClass ?>">
                                            <?= str_replace('_', ' ', $litige['statut_traitement']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($litige['responsable'] ?? '-') ?></td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-success" onclick="ouvrirMajLitige(<?= (int)$litige['id'] ?>, 'RESOLU')">
                                                <i class="bi bi-check2-circle"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-info" onclick="ouvrirMajLitige(<?= (int)$litige['id'] ?>, 'REMPLACEMENT_EFFECTUE')" title="Produit remplacé">
                                                <i class="bi bi-box-seam"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-primary" onclick="ouvrirMajLitige(<?= (int)$litige['id'] ?>, 'REMBOURSEMENT_EFFECTUE')" title="Remboursement">
                                                <i class="bi bi-cash-coin"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-warning mt-4">
        <i class="bi bi-exclamation-triangle"></i>
        <strong>Important :</strong> Chaque litige doit être traité dans les <strong>48h maximum</strong>. 
        Le suivi de la satisfaction client finale permet de mesurer la qualité de nos solutions et d'améliorer nos processus.
    </div>
</div>

<!-- Modal Nouveau Litige -->
<div class="modal fade" id="modalNouveauLitige" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i> Nouveau litige</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formNouveauLitige">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small">Client <span class="text-danger">*</span></label>
                            <input type="text" id="searchClient" class="form-control" placeholder="Rechercher client..." autocomplete="off">
                            <input type="hidden" name="client_id" id="clientIdHidden">
                            <div class="list-group position-absolute" style="z-index: 1000; max-height: 300px; overflow-y: auto; display: none;" id="suggestClient"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Vente</label>
                            <input type="text" id="searchVente" class="form-control" placeholder="Rechercher vente..." autocomplete="off">
                            <input type="hidden" name="vente_id" id="venteIdHidden">
                            <div class="list-group position-absolute" style="z-index: 1000; max-height: 300px; overflow-y: auto; display: none;" id="suggestVente"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Produit</label>
                            <input type="text" id="searchProduit" class="form-control" placeholder="Rechercher produit..." autocomplete="off">
                            <input type="hidden" name="produit_id" id="produitIdHidden">
                            <div class="list-group position-absolute" style="z-index: 1000; max-height: 300px; overflow-y: auto; display: none;" id="suggestProduit"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Type de problème</label>
                            <select name="type_probleme" class="form-select" required>
                                <option value="DEFAUT_PRODUIT">Défaut produit</option>
                                <option value="LIVRAISON_NON_CONFORME">Livraison non conforme</option>
                                <option value="RETARD_LIVRAISON">Retard livraison</option>
                                <option value="ERREUR_COMMANDE">Erreur commande</option>
                                <option value="INSATISFACTION_CLIENT">Insatisfaction</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Date retour</label>
                            <input type="date" name="date_retour" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Motif détaillé</label>
                            <textarea name="motif_detaille" class="form-control" rows="3" required></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="btnCreateLitige">Créer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Mise à jour Litige -->
<div class="modal fade" id="modalMajLitige" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Mettre à jour le litige</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formMajLitige">
                    <input type="hidden" name="id" id="majLitigeId">
                    <input type="hidden" name="statut" id="majLitigeStatut">
                    <div class="mb-2">
                        <label class="form-label small">Solution apportée</label>
                        <textarea name="solution" class="form-control" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="btnMajLitige">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<script>
// Fonction simple d'autocomplete
function setupAutocomplete(inputId, hiddenId, suggestId, endpoint, onSelect) {
    const input = document.getElementById(inputId);
    const hidden = document.getElementById(hiddenId);
    const suggest = document.getElementById(suggestId);
    let timer;
    
    input.addEventListener('input', function() {
        clearTimeout(timer);
        hidden.value = '';
        const q = this.value.trim();
        if (q.length < 2) {
            suggest.style.display = 'none';
            suggest.innerHTML = '';
            return;
        }
        timer = setTimeout(async () => {
            try {
                const url = new URL(endpoint, window.location.origin);
                url.searchParams.set('q', q);
                const res = await fetch(url.toString());
                const items = await res.json();
                suggest.innerHTML = '';
                items.slice(0, 10).forEach(item => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'list-group-item list-group-item-action';
                    btn.textContent = item.label;
                    btn.addEventListener('click', () => {
                        input.value = item.label;
                        hidden.value = item.id;
                        suggest.style.display = 'none';
                        if (onSelect) onSelect(item);
                    });
                    suggest.appendChild(btn);
                });
                suggest.style.display = items.length > 0 ? 'block' : 'none';
            } catch (e) {
                console.error('Autocomplete error:', e);
            }
        }, 300);
    });
    
    document.addEventListener('click', function(e) {
        if (!suggest.contains(e.target) && e.target !== input) {
            suggest.style.display = 'none';
        }
    });
}

// Setup autocompletes
setupAutocomplete('searchClient', 'clientIdHidden', 'suggestClient', '<?= url_for("coordination/api/clients_search.php") ?>');
setupAutocomplete('searchVente', 'venteIdHidden', 'suggestVente', '<?= url_for("coordination/api/ventes_search.php") ?>', (item) => {
    if (item.client_id && !document.getElementById('clientIdHidden').value) {
        document.getElementById('clientIdHidden').value = item.client_id;
        document.getElementById('searchClient').value = item.client_nom || '';
    }
});
setupAutocomplete('searchProduit', 'produitIdHidden', 'suggestProduit', '<?= url_for("coordination/api/produits_search.php") ?>');

// Bouton créer
document.getElementById('btnCreateLitige').addEventListener('click', async function() {
    const btn = this;
    const form = document.getElementById('formNouveauLitige');
    const fd = new FormData(form);
    const clientId = fd.get('client_id');
    const typeProbleme = fd.get('type_probleme');
    const motif = fd.get('motif_detaille');
    
    if (!clientId || !typeProbleme || !motif) {
        alert('Veuillez renseigner au minimum le client, le type de problème et le motif détaillé.');
        return;
    }
    
    btn.disabled = true;
    try {
        const data = Object.fromEntries(fd.entries());
        console.log('Data to send:', data);
        const res = await postForm('<?= url_for("coordination/api/litiges_create.php") ?>', data);
        console.log('postForm result:', res);
        if (res && res.success) {
            const modalEl = document.getElementById('modalNouveauLitige');
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            form.reset();
            location.reload();
        } else {
            const errorMsg = (res && res.message) ? res.message : 'Erreur lors de la création';
            console.error('API error:', errorMsg, res);
            alert(errorMsg);
        }
    } catch (e) {
        console.error('Exception lors de la création:', e);
        console.error('Stack:', e.stack);
        alert('Impossible de créer le litige. Erreur: ' + e.message);
    } finally {
        btn.disabled = false;
    }
});

function ouvrirMajLitige(id, statut){
    document.getElementById('majLitigeId').value = id;
    document.getElementById('majLitigeStatut').value = statut;
    const modalEl = document.getElementById('modalMajLitige');
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
}

document.getElementById('btnMajLitige').addEventListener('click', async function(){
    const form = document.getElementById('formMajLitige');
    const data = Object.fromEntries(new FormData(form).entries());
    const res = await postForm('<?= url_for("coordination/api/litiges_update.php") ?>', data);
    if (res.success) {
        const modalEl = document.getElementById('modalMajLitige');
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        location.reload();
    } else {
        alert(res.message || 'Erreur lors de la mise à jour');
    }
});


</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>
