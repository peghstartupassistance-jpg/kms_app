<?php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('COMPTABILITE_LIRE');

require_once __DIR__ . '/../lib/compta.php';

// Récupérer l'exercice actif
$exercice = compta_get_exercice_actif($pdo);

// Récupérer la balance complète
$balance = compta_get_balance($pdo, $exercice['id'] ?? null);

// Organiser par classe
$balance_par_classe = [];
foreach ($balance as $ligne) {
    $classe = (int)$ligne['classe'];
    if (!isset($balance_par_classe[$classe])) {
        $balance_par_classe[$classe] = [];
    }
    $balance_par_classe[$classe][] = $ligne;
}

// Calcul SYSCOHADA OHADA correct
$total_actif = 0;
$total_passif = 0;
$total_charges = 0;
$total_produits = 0;

// CLASSE 1 : CAPITAUX PROPRES (PASSIF)
$capitaux_propres = 0;
if (isset($balance_par_classe[1])) {
    foreach ($balance_par_classe[1] as $compte) {
        $solde = (float)($compte['total_debit'] ?? 0) - (float)($compte['total_credit'] ?? 0);
        // Solde créditeur = Passif (normal pour classe 1)
        $capitaux_propres += abs($solde);
        $total_passif += abs($solde);
    }
}

// CLASSE 2 : IMMOBILISATIONS (ACTIF)
$immobilisations = 0;
if (isset($balance_par_classe[2])) {
    foreach ($balance_par_classe[2] as $compte) {
        $solde = (float)($compte['total_debit'] ?? 0) - (float)($compte['total_credit'] ?? 0);
        // Solde débiteur = Actif (normal pour classe 2)
        if ($solde > 0) {
            $immobilisations += $solde;
            $total_actif += $solde;
        }
    }
}

// CLASSE 3 : STOCKS (ACTIF)
$stocks = 0;
if (isset($balance_par_classe[3])) {
    foreach ($balance_par_classe[3] as $compte) {
        $solde = (float)($compte['total_debit'] ?? 0) - (float)($compte['total_credit'] ?? 0);
        // Solde débiteur = Actif (normal pour classe 3)
        if ($solde > 0) {
            $stocks += $solde;
            $total_actif += $solde;
        }
    }
}

// CLASSE 4 : TIERS (ACTIF si débiteur, PASSIF si créditeur)
$tiers_actif = 0;  // Clients (411), Créances
$tiers_passif = 0; // Fournisseurs (401), Dettes
if (isset($balance_par_classe[4])) {
    foreach ($balance_par_classe[4] as $compte) {
        $solde = (float)($compte['total_debit'] ?? 0) - (float)($compte['total_credit'] ?? 0);
        if ($solde > 0) {
            // Débiteur : 411 Clients, 445 TVA récupérable, etc.
            $tiers_actif += $solde;
            $total_actif += $solde;
        } else if ($solde < 0) {
            // Créditeur : 401 Fournisseurs, 421 Personnel, 431 Sécu, 443 TVA facturée, etc.
            $tiers_passif += abs($solde);
            $total_passif += abs($solde);
        }
    }
}

// CLASSE 5 : TRÉSORERIE (ACTIF si débiteur, PASSIF si créditeur)
$treso_actif = 0;  // 521 Banque, 571 Caisse (solde positif)
$treso_passif = 0; // 56 Crédits de trésorerie, ou soldes négatifs
if (isset($balance_par_classe[5])) {
    foreach ($balance_par_classe[5] as $compte) {
        $solde = (float)($compte['total_debit'] ?? 0) - (float)($compte['total_credit'] ?? 0);
        if ($solde > 0) {
            // Trésorerie positive = Actif
            $treso_actif += $solde;
            $total_actif += $solde;
        } else if ($solde < 0) {
            // Trésorerie négative ou crédits = Passif
            $treso_passif += abs($solde);
            $total_passif += abs($solde);
        }
    }
}

// Classe 6 - Charges
if (isset($balance_par_classe[6])) {
    foreach ($balance_par_classe[6] as $compte) {
        $solde = (float)($compte['total_debit'] ?? 0);
        $total_charges += $solde;
    }
}

// Classe 7 - Produits
if (isset($balance_par_classe[7])) {
    foreach ($balance_par_classe[7] as $compte) {
        $solde = (float)($compte['total_credit'] ?? 0);
        $total_produits += $solde;
    }
}

$resultat_exercice = $total_produits - $total_charges;
$total_passif_resultat = $total_passif + $resultat_exercice;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilan Comptable</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.0/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/custom.css">
    <style>
        .bilan-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .bilan-section {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
        }
        
        .bilan-title {
            font-weight: bold;
            font-size: 1.2em;
            background-color: #e9ecef;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        
        .compte-ligne {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .compte-numero {
            font-weight: bold;
            color: #0d6efd;
            min-width: 60px;
        }
        
        .compte-libelle {
            flex-grow: 1;
            margin: 0 10px;
        }
        
        .compte-montant {
            text-align: right;
            min-width: 120px;
            font-family: monospace;
        }
        
        .sous-total {
            border-top: 2px solid #000;
            border-bottom: 1px dotted #000;
            font-weight: bold;
            padding-top: 10px;
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
        }
        
        .total-classe {
            border-top: 2px solid #000;
            border-bottom: 3px double #000;
            font-weight: bold;
            padding: 8px 0;
            margin: 10px 0;
            display: flex;
            justify-content: space-between;
            background-color: #f8f9fa;
        }
        
        .resultat-exer {
            border: 3px solid #28a745;
            background-color: #f0fdf4;
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../partials/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3">
                <?php require_once __DIR__ . '/../partials/sidebar.php'; ?>
            </div>
            
            <div class="col-md-9">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="mb-1">Bilan Comptable (OHADA)</h2>
                        <p class="text-muted mb-0">Exercice <?= htmlspecialchars($exercice['annee'] ?? 'N/A') ?></p>
                    </div>
                    <div class="btn-group" role="group">
                        <a href="<?= url_for('compta/analyse_corrections.php') ?>" 
                           class="btn btn-outline-warning btn-sm">
                            <i class="bi bi-pencil-square me-1"></i> Corrections OHADA
                        </a>
                        <a href="<?= url_for('compta/export_bilan.php?exercice_id=' . ($exercice['id'] ?? 0)) ?>" 
                           class="btn btn-success btn-sm">
                            <i class="bi bi-file-earmark-excel me-1"></i> Exporter Excel
                        </a>
                    </div>
                </div>
                
                <!-- ACTIF ET PASSIF -->
                <div class="bilan-container">
                    <!-- ACTIF (Colonne gauche) -->
                    <div class="bilan-section">
                        <div class="bilan-title">ACTIF</div>
                        
                        <!-- CLASSE 2 - IMMOBILISATIONS (ACTIF IMMOBILISÉ) -->
                        <div class="sous-total" style="background: #e3f2fd; margin-top: 0;">
                            <span><strong>ACTIF IMMOBILISÉ (Classe 2)</strong></span>
                            <span></span>
                        </div>
                        <?php
                            if (isset($balance_par_classe[2]) && count($balance_par_classe[2]) > 0):
                                foreach ($balance_par_classe[2] as $compte):
                                    $solde = (float)($compte['total_debit'] ?? 0) - (float)($compte['total_credit'] ?? 0);
                                    if ($solde > 0):
                        ?>
                                        <div class="compte-ligne">
                                            <span class="compte-numero"><?= htmlspecialchars($compte['numero_compte']) ?></span>
                                            <span class="compte-libelle"><?= htmlspecialchars($compte['libelle']) ?></span>
                                            <span class="compte-montant"><?= number_format($solde, 2, ',', ' ') ?></span>
                                        </div>
                        <?php
                                    endif;
                                endforeach;
                            else:
                        ?>
                                <div class="compte-ligne" style="font-style: italic; color: #999;">
                                    <span class="compte-libelle">Aucune immobilisation</span>
                                    <span class="compte-montant">0,00</span>
                                </div>
                        <?php endif; ?>
                        <div class="sous-total">
                            <span>Total Immobilisations</span>
                            <span><?= number_format($immobilisations, 2, ',', ' ') ?></span>
                        </div>
                        
                        <!-- CLASSE 3 - STOCKS (ACTIF CIRCULANT) -->
                        <div class="sous-total" style="background: #e8f5e9; margin-top: 15px;">
                            <span><strong>ACTIF CIRCULANT</strong></span>
                            <span></span>
                        </div>
                        <?php
                            if (isset($balance_par_classe[3]) && count($balance_par_classe[3]) > 0):
                                foreach ($balance_par_classe[3] as $compte):
                                    $solde = (float)($compte['total_debit'] ?? 0) - (float)($compte['total_credit'] ?? 0);
                                    if ($solde > 0):
                        ?>
                                        <div class="compte-ligne">
                                            <span class="compte-numero"><?= htmlspecialchars($compte['numero_compte']) ?></span>
                                            <span class="compte-libelle"><?= htmlspecialchars($compte['libelle']) ?></span>
                                            <span class="compte-montant"><?= number_format($solde, 2, ',', ' ') ?></span>
                                        </div>
                        <?php
                                    endif;
                                endforeach;
                            endif;
                        ?>
                        <div class="sous-total">
                            <span>Stocks (Classe 3)</span>
                            <span><?= number_format($stocks, 2, ',', ' ') ?></span>
                        </div>
                        
                        <!-- CLASSE 4 - CRÉANCES (Clients et créances) -->
                        <?php
                            if (isset($balance_par_classe[4])):
                                foreach ($balance_par_classe[4] as $compte):
                                    $solde = (float)($compte['total_debit'] ?? 0) - (float)($compte['total_credit'] ?? 0);
                                    if ($solde > 0):  // Seulement les débiteurs
                        ?>
                                        <div class="compte-ligne">
                                            <span class="compte-numero"><?= htmlspecialchars($compte['numero_compte']) ?></span>
                                            <span class="compte-libelle"><?= htmlspecialchars($compte['libelle']) ?></span>
                                            <span class="compte-montant"><?= number_format($solde, 2, ',', ' ') ?></span>
                                        </div>
                        <?php
                                    endif;
                                endforeach;
                            endif;
                        ?>
                        <div class="sous-total">
                            <span>Créances (Classe 4)</span>
                            <span><?= number_format($tiers_actif, 2, ',', ' ') ?></span>
                        </div>
                        
                        <!-- CLASSE 5 - TRÉSORERIE-ACTIF -->
                        <?php
                            if (isset($balance_par_classe[5])):
                                foreach ($balance_par_classe[5] as $compte):
                                    $solde = (float)($compte['total_debit'] ?? 0) - (float)($compte['total_credit'] ?? 0);
                                    if ($solde > 0):
                        ?>
                                        <div class="compte-ligne">
                                            <span class="compte-numero"><?= htmlspecialchars($compte['numero_compte']) ?></span>
                                            <span class="compte-libelle"><?= htmlspecialchars($compte['libelle']) ?></span>
                                            <span class="compte-montant"><?= number_format($solde, 2, ',', ' ') ?></span>
                                        </div>
                        <?php
                                    endif;
                                endforeach;
                            endif;
                        ?>
                        <div class="sous-total">
                            <span>Trésorerie-Actif (Classe 5)</span>
                            <span><?= number_format($treso_actif, 2, ',', ' ') ?></span>
                        </div>
                        
                        <div class="total-classe">
                            <span>TOTAL ACTIF</span>
                            <span><?= number_format($total_actif, 2, ',', ' ') ?></span>
                        </div>
                    </div>
                    
                    <!-- PASSIF (Colonne droite) -->
                    <div class="bilan-section">
                        <div class="bilan-title">PASSIF + RÉSULTAT</div>
                        
                        <!-- CLASSE 1 - CAPITAUX PROPRES -->
                        <div class="sous-total" style="background: #fff3e0; margin-top: 0;">
                            <span><strong>CAPITAUX PROPRES (Classe 1)</strong></span>
                            <span></span>
                        </div>
                        <?php
                            if (isset($balance_par_classe[1]) && count($balance_par_classe[1]) > 0):
                                foreach ($balance_par_classe[1] as $compte):
                                    $solde = (float)($compte['total_debit'] ?? 0) - (float)($compte['total_credit'] ?? 0);
                        ?>
                                    <div class="compte-ligne">
                                        <span class="compte-numero"><?= htmlspecialchars($compte['numero_compte']) ?></span>
                                        <span class="compte-libelle"><?= htmlspecialchars($compte['libelle']) ?></span>
                                        <span class="compte-montant"><?= number_format(abs($solde), 2, ',', ' ') ?></span>
                                    </div>
                        <?php
                                endforeach;
                            else:
                        ?>
                                <div class="compte-ligne" style="font-style: italic; color: #999;">
                                    <span class="compte-libelle">Aucun capital</span>
                                    <span class="compte-montant">0,00</span>
                                </div>
                        <?php endif; ?>
                        <div class="sous-total">
                            <span>Total Capitaux Propres</span>
                            <span><?= number_format($capitaux_propres, 2, ',', ' ') ?></span>
                        </div>
                        
                        <!-- CLASSE 4 - DETTES (Fournisseurs, Personnel, Organismes sociaux, État) -->
                        <div class="sous-total" style="background: #fce4ec; margin-top: 15px;">
                            <span><strong>DETTES (Classe 4)</strong></span>
                            <span></span>
                        </div>
                        <?php
                            if (isset($balance_par_classe[4])):
                                foreach ($balance_par_classe[4] as $compte):
                                    $solde = (float)($compte['total_debit'] ?? 0) - (float)($compte['total_credit'] ?? 0);
                                    if ($solde < 0):  // Seulement les créditeurs (dettes)
                        ?>
                                        <div class="compte-ligne">
                                            <span class="compte-numero"><?= htmlspecialchars($compte['numero_compte']) ?></span>
                                            <span class="compte-libelle"><?= htmlspecialchars($compte['libelle']) ?></span>
                                            <span class="compte-montant"><?= number_format(abs($solde), 2, ',', ' ') ?></span>
                                        </div>
                        <?php
                                    endif;
                                endforeach;
                            endif;
                        ?>
                        <div class="sous-total">
                            <span>Dettes (Classe 4)</span>
                            <span><?= number_format($tiers_passif, 2, ',', ' ') ?></span>
                        </div>
                        
                        <!-- CLASSE 5 - TRÉSORERIE-PASSIF (Crédits de trésorerie, découverts) -->
                        <?php if ($treso_passif > 0): ?>
                            <div class="sous-total" style="background: #ffebee; margin-top: 15px;">
                                <span><strong>TRÉSORERIE-PASSIF (Classe 5)</strong></span>
                                <span></span>
                            </div>
                            <?php
                                if (isset($balance_par_classe[5])):
                                    foreach ($balance_par_classe[5] as $compte):
                                        $solde = (float)($compte['total_debit'] ?? 0) - (float)($compte['total_credit'] ?? 0);
                                        if ($solde < 0):
                            ?>
                                            <div class="compte-ligne">
                                                <span class="compte-numero"><?= htmlspecialchars($compte['numero_compte']) ?></span>
                                                <span class="compte-libelle"><?= htmlspecialchars($compte['libelle']) ?></span>
                                                <span class="compte-montant"><?= number_format(abs($solde), 2, ',', ' ') ?></span>
                                            </div>
                            <?php
                                        endif;
                                    endforeach;
                                endif;
                            ?>
                            <div class="sous-total">
                                <span>Trésorerie-Passif (Classe 5)</span>
                                <span><?= number_format($treso_passif, 2, ',', ' ') ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Résultat Exercice -->
                        <div class="resultat-exer">
                            <div style="display: flex; justify-content: space-between;">
                                <span><?= $resultat_exercice >= 0 ? 'BÉNÉFICE' : 'PERTE' ?> EXERCICE</span>
                                <span><?= number_format(abs($resultat_exercice), 2, ',', ' ') ?></span>
                            </div>
                        </div>
                        
                        <div class="total-classe">
                            <span>TOTAL PASSIF + RÉSULTAT</span>
                            <span><?= number_format($total_passif_resultat, 2, ',', ' ') ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- COMPTE DE RÉSULTAT -->
                <div class="card mt-4">
                    <div class="card-header bg-primary text-white">
                        <h5>Compte de Résultat</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Charges (Classe 6) -->
                            <div class="col-md-6">
                                <h6 class="mb-3">CHARGES</h6>
                                <?php
                                    $total_charges = 0;
                                    if (isset($balance_par_classe[6])):
                                        foreach ($balance_par_classe[6] as $compte):
                                            $solde = (float)($compte['total_debit'] ?? 0);
                                            $total_charges += $solde;
                                ?>
                                            <div class="compte-ligne">
                                                <span class="compte-numero"><?= htmlspecialchars($compte['numero_compte']) ?></span>
                                                <span class="compte-libelle" style="flex-grow: 1;"><?= htmlspecialchars($compte['libelle']) ?></span>
                                                <span class="compte-montant"><?= number_format($solde, 2, ',', ' ') ?></span>
                                            </div>
                                <?php
                                        endforeach;
                                    endif;
                                ?>
                                <div class="sous-total">
                                    <span>Total Charges</span>
                                    <span><?= number_format($total_charges, 2, ',', ' ') ?></span>
                                </div>
                            </div>
                            
                            <!-- Produits (Classe 7) -->
                            <div class="col-md-6">
                                <h6 class="mb-3">PRODUITS</h6>
                                <?php
                                    $total_produits = 0;
                                    if (isset($balance_par_classe[7])):
                                        foreach ($balance_par_classe[7] as $compte):
                                            $solde = (float)($compte['total_credit'] ?? 0);
                                            $total_produits += $solde;
                                ?>
                                            <div class="compte-ligne">
                                                <span class="compte-numero"><?= htmlspecialchars($compte['numero_compte']) ?></span>
                                                <span class="compte-libelle" style="flex-grow: 1;"><?= htmlspecialchars($compte['libelle']) ?></span>
                                                <span class="compte-montant"><?= number_format($solde, 2, ',', ' ') ?></span>
                                            </div>
                                <?php
                                        endforeach;
                                    endif;
                                ?>
                                <div class="sous-total">
                                    <span>Total Produits</span>
                                    <span><?= number_format($total_produits, 2, ',', ' ') ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="resultat-exer">
                            <div style="display: flex; justify-content: space-between;">
                                <span><?= $total_produits - $total_charges >= 0 ? 'BÉNÉFICE' : 'PERTE' ?> EXERCICE</span>
                                <span><?= number_format(abs($total_produits - $total_charges), 2, ',', ' ') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- VÉRIFICATION D'ÉQUILIBRE -->
                <div class="alert mt-4 <?= abs($total_actif - $total_passif_resultat) < 0.01 ? 'alert-success' : 'alert-danger' ?>" role="alert">
                    <h6>Équilibre Comptable (Bilan)</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <strong>Total Actif :</strong><br/>
                            <?= number_format($total_actif, 2, ',', ' ') ?> €
                        </div>
                        <div class="col-md-4">
                            <strong>Total Passif + Résultat :</strong><br/>
                            <?= number_format($total_passif_resultat, 2, ',', ' ') ?> €
                        </div>
                        <div class="col-md-4">
                            <?php $diff = abs($total_actif - $total_passif_resultat); ?>
                            <strong>Écart :</strong><br/>
                            <?php if ($diff < 0.01): ?>
                                <span class="badge bg-success">✓ Équilibré</span>
                            <?php else: ?>
                                <span class="badge bg-danger">✗ Erreur : <?= number_format($diff, 2, ',', ' ') ?> €</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php require_once __DIR__ . '/../partials/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
