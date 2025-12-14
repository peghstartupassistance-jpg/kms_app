<?php
// devis/edit.php
require_once __DIR__ . '/../security.php';
exigerConnexion();

global $pdo;

// URL de base pour la liste des devis (inclut le répertoire du projet)
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$devisListUrl = $basePath . '/list.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$modeEdition = $id > 0;

if ($modeEdition) {
    exigerPermission('DEVIS_MODIFIER');
} else {
    exigerPermission('DEVIS_CREER');
}

// Canaux
$stmt = $pdo->query("SELECT id, code, libelle FROM canaux_vente ORDER BY code");
$canaux = $stmt->fetchAll();

// Map des produits pour affichage existant
$produitsMap = [];
if ($modeEdition) {
    // Charger seulement les produits utilisés dans ce devis pour affichage
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.id, p.code_produit, p.designation, p.prix_vente
        FROM produits p
        JOIN devis_lignes dl ON dl.produit_id = p.id
        WHERE dl.devis_id = ?
    ");
    $stmt->execute([$id]);
    $produitsExistants = $stmt->fetchAll();
    foreach ($produitsExistants as $p) {
        $produitsMap[(int)$p['id']] = $p;
    }
}

// Champs header
$numero            = '';
$date_devis        = date('Y-m-d');
$client_id         = 0;
$clientNom         = ''; // pour afficher le libellé dans le champ de recherche AJAX
$canal_id          = $canaux[0]['id'] ?? 0;
$statut            = 'EN_ATTENTE';
$date_relance      = '';
$conditions        = '';
$commentaires      = '';
$montant_total_ht  = 0;
$montant_total_ttc = 0;

// Lignes
$lignes = [];

// Chargement en édition
if ($modeEdition && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("SELECT * FROM devis WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $d = $stmt->fetch();
    if (!$d) {
        http_response_code(404);
        echo "Devis introuvable.";
        exit;
    }

    $numero           = $d['numero'];
    $date_devis       = $d['date_devis'];
    $client_id        = (int)$d['client_id'];
    $canal_id         = (int)$d['canal_vente_id'];
    $statut           = $d['statut'];
    $date_relance     = $d['date_relance'] ?? '';
    $conditions       = $d['conditions'] ?? '';
    $commentaires     = $d['commentaires'] ?? '';
    $montant_total_ht = (float)$d['montant_total_ht'];
    $montant_total_ttc= (float)$d['montant_total_ttc'];

    // Récupérer le nom du client pour affichage dans le champ de recherche
    if ($client_id > 0) {
        $stmtCli = $pdo->prepare("SELECT nom FROM clients WHERE id = :id");
        $stmtCli->execute(['id' => $client_id]);
        $cli = $stmtCli->fetch();
        if ($cli) {
            $clientNom = $cli['nom'];
        }
    }

    $stmt = $pdo->prepare("
        SELECT dl.*, p.code_produit, p.designation
        FROM devis_lignes dl
        JOIN produits p ON p.id = dl.produit_id
        WHERE dl.devis_id = :id
        ORDER BY dl.id
    ");
    $stmt->execute(['id' => $id]);
    $lignes = $stmt->fetchAll();
}

// POST
$erreurs = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    verifierCsrf($csrf);

    $id          = (int)($_POST['id'] ?? 0);
    $modeEdition = $id > 0;

    $numero       = trim($_POST['numero'] ?? '');
    $date_devis   = $_POST['date_devis'] ?? date('Y-m-d');
    $client_id    = (int)($_POST['client_id'] ?? 0);
    $canal_id     = (int)($_POST['canal_vente_id'] ?? 0);
    $statut       = $_POST['statut'] ?? 'EN_ATTENTE';
    $date_relance = $_POST['date_relance'] ?? '';
    $conditions   = trim($_POST['conditions'] ?? '');
    $commentaires = trim($_POST['commentaires'] ?? '');

    if ($client_id <= 0)  $erreurs[] = "Veuillez sélectionner un client.";
    if ($canal_id <= 0)   $erreurs[] = "Veuillez sélectionner un canal de vente.";
    if (!in_array($statut, ['EN_ATTENTE','ACCEPTE','REFUSE','ANNULE'], true)) {
        $erreurs[] = "Statut de devis invalide.";
    }

    // Récup nom client pour réaffichage si erreur
    if ($client_id > 0) {
        $stmtCli = $pdo->prepare("SELECT nom FROM clients WHERE id = :id");
        $stmtCli->execute(['id' => $client_id]);
        $cli = $stmtCli->fetch();
        if ($cli) {
            $clientNom = $cli['nom'];
        }
    } else {
        $clientNom = '';
    }

    // Génération numéro si vide
    if ($numero === '') {
        $numero = 'DV-' . date('Ymd-His');
    }

    // Traitement lignes
    $ligne_produit_id = $_POST['ligne_produit_id'] ?? [];
    $ligne_quantite   = $_POST['ligne_quantite'] ?? [];
    $ligne_prix       = $_POST['ligne_prix'] ?? [];
    $ligne_remise     = $_POST['ligne_remise'] ?? [];

    $lignes = [];
    $montant_total_ht = 0.0;

    $count = max(
        count($ligne_produit_id),
        count($ligne_quantite),
        count($ligne_prix),
        count($ligne_remise)
    );

    for ($i = 0; $i < $count; $i++) {
        $pid = isset($ligne_produit_id[$i]) ? (int)$ligne_produit_id[$i] : 0;
        $qte = isset($ligne_quantite[$i]) ? (int)$ligne_quantite[$i] : 0;
        $pu  = isset($ligne_prix[$i]) ? (float)$ligne_prix[$i] : 0;
        $rem = isset($ligne_remise[$i]) ? (float)$ligne_remise[$i] : 0;

        if ($pid <= 0 || $qte <= 0) {
            continue;
        }

        if ($pu <= 0 && isset($produitsMap[$pid])) {
            $pu = (float)$produitsMap[$pid]['prix_vente'];
        }

        $montant_ligne = $qte * $pu - $rem;
        if ($montant_ligne < 0) $montant_ligne = 0;
        $montant_total_ht += $montant_ligne;

        $lignes[] = [
            'produit_id'        => $pid,
            'quantite'          => $qte,
            'prix_unitaire'     => $pu,
            'remise'            => $rem,
            'montant_ligne_ht'  => $montant_ligne,
        ];
    }

    if (empty($lignes)) {
        $erreurs[] = "Le devis doit contenir au moins une ligne produit.";
    }

    // Pour simplifier : HT = TTC (pas de TVA gérée ici)
    $montant_total_ttc = $montant_total_ht;

    if (empty($erreurs)) {
        $utilisateur = utilisateurConnecte();
        $userId      = (int)$utilisateur['id'];

        if ($modeEdition) {
            // UPDATE devis
            $sql = "
                UPDATE devis
                SET numero = :numero,
                    date_devis = :date_devis,
                    client_id = :client_id,
                    canal_vente_id = :canal_vente_id,
                    statut = :statut,
                    date_relance = :date_relance,
                    montant_total_ht = :mtht,
                    montant_total_ttc = :mtttc,
                    remise_global = :remise_global,
                    conditions = :conditions,
                    commentaires = :commentaires
                WHERE id = :id
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'numero'         => $numero,
                'date_devis'     => $date_devis,
                'client_id'      => $client_id,
                'canal_vente_id' => $canal_id,
                'statut'         => $statut,
                'date_relance'   => $date_relance ?: null,
                'mtht'           => $montant_total_ht,
                'mtttc'          => $montant_total_ttc,
                'remise_global'  => 0,
                'conditions'     => $conditions ?: null,
                'commentaires'   => $commentaires ?: null,
                'id'             => $id
            ]);

            // Suppression + réinsertion des lignes
            $stmt = $pdo->prepare("DELETE FROM devis_lignes WHERE devis_id = :id");
            $stmt->execute(['id' => $id]);

        } else {
            // INSERT devis
            $sql = "
                INSERT INTO devis (
                    numero, date_devis, client_id, canal_vente_id,
                    statut, date_relance, utilisateur_id,
                    montant_total_ht, montant_total_ttc, remise_global,
                    conditions, commentaires
                ) VALUES (
                    :numero, :date_devis, :client_id, :canal_vente_id,
                    :statut, :date_relance, :utilisateur_id,
                    :mtht, :mtttc, :remise_global,
                    :conditions, :commentaires
                )
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'numero'         => $numero,
                'date_devis'     => $date_devis,
                'client_id'      => $client_id,
                'canal_vente_id' => $canal_id,
                'statut'         => $statut,
                'date_relance'   => $date_relance ?: null,
                'utilisateur_id' => $userId,
                'mtht'           => $montant_total_ht,
                'mtttc'          => $montant_total_ttc,
                'remise_global'  => 0,
                'conditions'     => $conditions ?: null,
                'commentaires'   => $commentaires ?: null,
            ]);
            $id = (int)$pdo->lastInsertId();
            $modeEdition = true;
        }

        // Insertion des lignes
        $stmt = $pdo->prepare("
            INSERT INTO devis_lignes (
                devis_id, produit_id, quantite,
                prix_unitaire, remise, montant_ligne_ht
            ) VALUES (
                :devis_id, :produit_id, :quantite,
                :prix_unitaire, :remise, :montant_ligne_ht
            )
        ");
        foreach ($lignes as $l) {
            $stmt->execute([
                'devis_id'        => $id,
                'produit_id'      => $l['produit_id'],
                'quantite'        => $l['quantite'],
                'prix_unitaire'   => $l['prix_unitaire'],
                'remise'          => $l['remise'],
                'montant_ligne_ht'=> $l['montant_ligne_ht'],
            ]);
        }

        $_SESSION['flash_success'] = $modeEdition
            ? "Le devis a été mis à jour."
            : "Le devis a été créé.";
        header('Location: ' . $devisListUrl);
        exit;
    }
}

$csrfToken = getCsrfToken();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="form-page-header">
        <h1 class="h4 mb-0">
            <?= $modeEdition ? 'Modifier un devis' : 'Nouveau devis' ?>
        </h1>
        <a href="<?= $devisListUrl ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Retour à la liste
        </a>
    </div>

    <?php if (!empty($erreurs)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                <?php foreach ($erreurs as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="card">
        <div class="card-body">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= (int)$id ?>">

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label small">N° devis</label>
                    <input type="text" name="numero" class="form-control"
                           value="<?= htmlspecialchars($numero) ?>"
                           placeholder="Auto si vide">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Date devis</label>
                    <input type="date" name="date_devis" class="form-control"
                           value="<?= htmlspecialchars($date_devis) ?>">
                </div>

                <!-- Client avec recherche AJAX -->
                <div class="col-md-3 position-relative">
                    <label class="form-label small">Client (BD KMS) *</label>
                    <input type="hidden" name="client_id" id="client_id" value="<?= (int)$client_id ?>">
                    <input type="text"
                           id="client_search"
                           class="form-control"
                           placeholder="Rechercher un client par nom, téléphone ou email..."
                           value="<?= htmlspecialchars($clientNom) ?>">
                    <div id="client_search_results"
                         class="list-group position-absolute w-100 shadow-sm"
                         style="z-index: 1050; max-height: 250px; overflow-y: auto; display:none;"></div>
                    <div class="form-text">
                        Tape quelques lettres puis sélectionne un client dans la liste.
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Canal *</label>
                    <select name="canal_vente_id" class="form-select" required>
                        <?php foreach ($canaux as $cv): ?>
                            <option value="<?= (int)$cv['id'] ?>"
                                <?= $canal_id === (int)$cv['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cv['code']) ?> – <?= htmlspecialchars($cv['libelle']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Statut</label>
                    <select name="statut" class="form-select">
                        <?php foreach (['EN_ATTENTE','ACCEPTE','REFUSE','ANNULE'] as $s): ?>
                            <option value="<?= $s ?>" <?= $statut === $s ? 'selected' : '' ?>>
                                <?= $s ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Date de relance</label>
                    <input type="date" name="date_relance" class="form-control"
                           value="<?= htmlspecialchars($date_relance) ?>">
                </div>
            </div>

            <hr>

            <!-- Lignes de devis -->
            <h2 class="h6 mb-2">Lignes du devis</h2>
            <p class="text-muted small mb-2">
                Ajoutez les produits, quantités et remises éventuelles.
            </p>

            <div class="table-responsive mb-2">
                <table class="table table-sm align-middle" id="table-lignes">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 35%;">Produit</th>
                        <th style="width: 10%;">Qté</th>
                        <th style="width: 15%;">Prix unitaire</th>
                        <th style="width: 15%;">Remise</th>
                        <th style="width: 15%;">Montant HT</th>
                        <th style="width: 10%;"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    if (!empty($lignes)) {
                        foreach ($lignes as $index => $l):
                            $pid = (int)$l['produit_id'] ?? (int)$l['id'];
                            $qte = (int)($l['quantite'] ?? 1);
                            $pu  = (float)($l['prix_unitaire'] ?? 0);
                            $rem = (float)($l['remise'] ?? 0);
                            $mt  = (float)($l['montant_ligne_ht'] ?? ($qte * $pu - $rem));
                    ?>
                        <tr>
                            <td style="position: relative;">
                                <input type="hidden" name="ligne_produit_id[]" class="ligne-produit-id" value="<?= $pid ?>">
                                <input type="text" 
                                       class="form-control form-control-sm ligne-produit-search" 
                                       placeholder="🔍 Rechercher un produit..."
                                       value="<?= isset($produitsMap[$pid]) ? htmlspecialchars($produitsMap[$pid]['code_produit'] . ' – ' . $produitsMap[$pid]['designation']) : '' ?>"
                                       data-prix="<?= isset($produitsMap[$pid]) ? (float)$produitsMap[$pid]['prix_vente'] : 0 ?>"
                                       autocomplete="off">
                                <div class="autocomplete-results"></div>
                            </td>
                            <td>
                                <input type="number" name="ligne_quantite[]" class="form-control form-control-sm ligne-quantite"
                                       value="<?= $qte ?>" min="1">
                            </td>
                            <td>
                                <input type="number" step="0.01" name="ligne_prix[]" class="form-control form-control-sm ligne-prix"
                                       value="<?= $pu ?>">
                            </td>
                            <td>
                                <input type="number" step="0.01" name="ligne_remise[]" class="form-control form-control-sm ligne-remise"
                                       value="<?= $rem ?>">
                            </td>
                            <td class="text-end">
                                <span class="ligne-montant">
                                    <?= number_format($mt, 0, ',', ' ') ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-ligne">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php
                        endforeach;
                    } else {
                        // au moins une ligne vide par défaut
                    ?>
                        <tr>
                            <td style="position: relative;">
                                <input type="hidden" name="ligne_produit_id[]" class="ligne-produit-id" value="">
                                <input type="text" 
                                       class="form-control form-control-sm ligne-produit-search" 
                                       placeholder="🔍 Rechercher un produit..."
                                       data-prix="0"
                                       autocomplete="off">
                                <div class="autocomplete-results"></div>
                            </td>
                            <td>
                                <input type="number" name="ligne_quantite[]" class="form-control form-control-sm ligne-quantite"
                                       value="1" min="1">
                            </td>
                            <td>
                                <input type="number" step="0.01" name="ligne_prix[]" class="form-control form-control-sm ligne-prix"
                                       value="0">
                            </td>
                            <td>
                                <input type="number" step="0.01" name="ligne_remise[]" class="form-control form-control-sm ligne-remise"
                                       value="0">
                            </td>
                            <td class="text-end">
                                <span class="ligne-montant">0</span>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-ligne">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                    <tfoot>
                    <tr>
                        <td colspan="4" class="text-end fw-semibold">Total HT estimé</td>
                        <td class="text-end fw-bold" id="total_ht_cell">
                            <?= number_format($montant_total_ht, 0, ',', ' ') ?>
                        </td>
                        <td></td>
                    </tr>
                    </tfoot>
                </table>
            </div>

            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-ligne">
                <i class="bi bi-plus-circle me-1"></i> Ajouter une ligne
            </button>

            <hr>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small">Conditions</label>
                    <textarea name="conditions" class="form-control" rows="3"><?= htmlspecialchars($conditions) ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Commentaires internes</label>
                    <textarea name="commentaires" class="form-control" rows="3"><?= htmlspecialchars($commentaires) ?></textarea>
                </div>
            </div>
        </div>

        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= $devisListUrl ?>" class="btn btn-outline-secondary">Annuler</a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i>
                <?= $modeEdition ? 'Enregistrer le devis' : 'Créer le devis' ?>
            </button>
        </div>
    </form>
</div>

<!-- Script recherche AJAX client (même logique que satisfaction) -->
<script>
(function() {
    const input      = document.getElementById('client_search');
    const hiddenId   = document.getElementById('client_id');
    const resultsBox = document.getElementById('client_search_results');

    if (!input || !hiddenId || !resultsBox) {
        return;
    }

    let timer = null;

    function clearResults() {
        resultsBox.innerHTML = '';
        resultsBox.style.display = 'none';
    }

    function selectClient(button) {
        hiddenId.value = button.dataset.id;
        input.value    = button.dataset.label;
        clearResults();
    }

    input.addEventListener('input', function() {
        const q = this.value.trim();
        hiddenId.value = '';

        if (timer) {
            clearTimeout(timer);
        }

        if (q.length < 2) {
            clearResults();
            return;
        }

        timer = setTimeout(function() {
            fetch('<?= url_for('ajax/clients_search.php') ?>?q=' + encodeURIComponent(q), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    resultsBox.innerHTML = '';

                    if (!Array.isArray(data) || data.length === 0) {
                        clearResults();
                        return;
                    }

                    data.forEach(function(row) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action py-1';
                        btn.textContent = row.label;
                        btn.dataset.id = row.id;
                        btn.dataset.label = row.label;
                        btn.addEventListener('click', function() {
                            selectClient(btn);
                        });
                        resultsBox.appendChild(btn);
                    });

                    resultsBox.style.display = 'block';
                })
                .catch(function() {
                    clearResults();
                });
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!resultsBox.contains(e.target) && e.target !== input) {
            clearResults();
        }
    });
})();
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('#table-lignes tbody');
    const btnAdd = document.getElementById('btn-add-ligne');
    const totalHtCell = document.getElementById('total_ht_cell');

    if (!table || !btnAdd || !totalHtCell) return;

    function recalculerLignes() {
        let total = 0;
        table.querySelectorAll('tr').forEach(tr => {
            const qteInput = tr.querySelector('.ligne-quantite');
            const prixInput = tr.querySelector('.ligne-prix');
            const remInput  = tr.querySelector('.ligne-remise');
            const montantSpan = tr.querySelector('.ligne-montant');

            const qte = parseFloat(qteInput?.value || '0');
            const pu  = parseFloat(prixInput?.value || '0');
            const rem = parseFloat(remInput?.value || '0');

            let mt = qte * pu - rem;
            if (!isFinite(mt) || mt < 0) mt = 0;
            if (montantSpan) {
                montantSpan.textContent = Math.round(mt).toLocaleString('fr-FR');
            }
            total += mt;
        });

        totalHtCell.textContent = Math.round(total).toLocaleString('fr-FR');
    }

    function attachEvents(tr) {
        tr.querySelectorAll('.ligne-quantite, .ligne-prix, .ligne-remise')
          .forEach(input => input.addEventListener('input', recalculerLignes));

        const btnRemove = tr.querySelector('.btn-remove-ligne');
        if (btnRemove) {
            btnRemove.addEventListener('click', () => {
                tr.remove();
                recalculerLignes();
            });
        }
    }

    // Initialiser les lignes existantes
    table.querySelectorAll('tr').forEach(tr => {
        attachEvents(tr);
        initProductSearch(tr.querySelector('.ligne-produit-search'), recalculerLignes);
    });

    btnAdd.addEventListener('click', () => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
<td style="position: relative;">
    <input type="hidden" name="ligne_produit_id[]" class="ligne-produit-id" value="">
    <input type="text" 
           class="form-control form-control-sm ligne-produit-search" 
           placeholder="🔍 Rechercher un produit..."
           data-prix="0"
           autocomplete="off">
    <div class="autocomplete-results"></div>
</td>
<td>
    <input type="number" name="ligne_quantite[]" class="form-control form-control-sm ligne-quantite" value="1" min="1">
</td>
<td>
    <input type="number" step="0.01" name="ligne_prix[]" class="form-control form-control-sm ligne-prix" value="0">
</td>
<td>
    <input type="number" step="0.01" name="ligne_remise[]" class="form-control form-control-sm ligne-remise" value="0">
</td>
<td class="text-end">
    <span class="ligne-montant">0</span>
</td>
<td class="text-end">
    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-ligne">
        <i class="bi bi-trash"></i>
    </button>
</td>
        `;
        table.appendChild(tr);
        attachEvents(tr);
        initProductSearch(tr.querySelector('.ligne-produit-search'), recalculerLignes);
        recalculerLignes();
    });

    recalculerLignes();
});

// Autocomplete produits avec AJAX
function initProductSearch(input, onChange) {
    if (!input) return;
    
    let currentTimeout = null;
    const resultsDiv = input.nextElementSibling;
    const hiddenInput = input.previousElementSibling;
    
    input.addEventListener('input', function() {
        if (hiddenInput) hiddenInput.value = '';
        clearTimeout(currentTimeout);
        const term = this.value.trim();
        
        if (term.length < 2) {
            if (resultsDiv) {
                resultsDiv.innerHTML = '';
                resultsDiv.style.display = 'none';
            }
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
        if (!resultsDiv) return;

        if (!products || products.length === 0) {
            resultsDiv.innerHTML = '<div class="autocomplete-item text-muted">Aucun produit trouvé</div>';
            resultsDiv.style.display = 'block';
            return;
        }
        
        resultsDiv.innerHTML = products.map(p => `
            <div class="autocomplete-item" 
                 data-id="${p.id}" 
                 data-prix="${p.prix_vente}"
                 data-label="${p.label}" 
                 data-stock="${p.stock_actuel ?? ''}">
                ${p.label}
                <span class="float-end text-primary">${Number(p.prix_vente).toLocaleString('fr-FR', {minimumFractionDigits: 0})} FCFA</span>
                <small class="d-block text-muted">Stock: ${p.stock_actuel ?? 'N/A'}</small>
            </div>
        `).join('');
        resultsDiv.style.display = 'block';
        
        resultsDiv.querySelectorAll('.autocomplete-item[data-id]').forEach(item => {
            item.addEventListener('click', function() {
                const id = this.dataset.id;
                const prix = parseFloat(this.dataset.prix);
                const label = this.dataset.label;
                
                if (hiddenInput) hiddenInput.value = id;
                input.value = label;
                input.dataset.prix = prix;
                resultsDiv.style.display = 'none';
                
                const tr = input.closest('tr');
                const prixInput = tr?.querySelector('.ligne-prix');
                if (prixInput && parseFloat(prixInput.value || '0') === 0) {
                    prixInput.value = prix;
                }
                
                const remiseInput = tr?.querySelector('.ligne-remise');
                if (remiseInput && !remiseInput.value) {
                    remiseInput.value = 0;
                }

                if (typeof onChange === 'function') {
                    onChange();
                }
            });
        });
    }
    
    document.addEventListener('click', function(e) {
        if (!resultsDiv) return;
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

<?php include __DIR__ . '/../partials/footer.php'; ?>
