<?php
/**
 * Dashboard Helpers - Calcul KPIs et statistiques métier
 */

/**
 * Calculer CA du jour consolidé (ventes + hôtel + formation)
 */
function calculateCAJour(PDO $pdo): array {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN source_type = 'vente' THEN montant ELSE 0 END) as ca_ventes,
            SUM(CASE WHEN source_type = 'reservation_hotel' THEN montant ELSE 0 END) as ca_hotel,
            SUM(CASE WHEN source_type = 'inscription_formation' THEN montant ELSE 0 END) as ca_formation,
            SUM(montant) as ca_total
        FROM caisse_journal 
        WHERE DATE(date_ecriture) = CURDATE() AND sens = 'ENTREE'
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'ca_ventes' => (float)($result['ca_ventes'] ?? 0),
        'ca_hotel' => (float)($result['ca_hotel'] ?? 0),
        'ca_formation' => (float)($result['ca_formation'] ?? 0),
        'ca_total' => (float)($result['ca_total'] ?? 0),
    ];
}

/**
 * Calculer CA du mois consolidé
 */
function calculateCAMois(PDO $pdo, int $year = null, int $month = null): array {
    if ($year === null) $year = (int)date('Y');
    if ($month === null) $month = (int)date('m');
    
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN source_type = 'vente' THEN montant ELSE 0 END) as ca_ventes,
            SUM(CASE WHEN source_type = 'reservation_hotel' THEN montant ELSE 0 END) as ca_hotel,
            SUM(CASE WHEN source_type = 'inscription_formation' THEN montant ELSE 0 END) as ca_formation,
            SUM(montant) as ca_total,
            COUNT(DISTINCT DATE(date_ecriture)) as nb_jours_actifs
        FROM caisse_journal 
        WHERE YEAR(date_ecriture) = ? AND MONTH(date_ecriture) = ? AND sens = 'ENTREE'
    ");
    $stmt->execute([$year, $month]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'ca_ventes' => (float)($result['ca_ventes'] ?? 0),
        'ca_hotel' => (float)($result['ca_hotel'] ?? 0),
        'ca_formation' => (float)($result['ca_formation'] ?? 0),
        'ca_total' => (float)($result['ca_total'] ?? 0),
        'nb_jours_actifs' => (int)($result['nb_jours_actifs'] ?? 0),
        'ca_moyen_jour' => $result['nb_jours_actifs'] > 0 
            ? (float)($result['ca_total'] ?? 0) / $result['nb_jours_actifs']
            : 0,
    ];
}

/**
 * Pourcentage BL signés vs total
 */
function calculateBLSignedRate(PDO $pdo): array {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_bl,
            SUM(CASE WHEN signe_client = 1 THEN 1 ELSE 0 END) as bl_signes,
            SUM(CASE WHEN signe_client = 0 THEN 1 ELSE 0 END) as bl_non_signes,
            SUM(CASE WHEN DATE(date_bl) = CURDATE() THEN 1 ELSE 0 END) as bl_today
        FROM bons_livraison
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total = (int)($result['total_bl'] ?? 0);
    $signes = (int)($result['bl_signes'] ?? 0);
    
    return [
        'total_bl' => $total,
        'bl_signes' => $signes,
        'bl_non_signes' => (int)($result['bl_non_signes'] ?? 0),
        'bl_today' => (int)($result['bl_today'] ?? 0),
        'signed_rate' => $total > 0 ? round(($signes / $total) * 100, 1) : 0,
    ];
}

/**
 * Taux encaissement (montant encaissé / montant total ventes)
 */
function calculateEncaissementRate(PDO $pdo): array {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(v.montant_total_ttc) as montant_total_ventes,
            SUM(CASE WHEN v.statut_encaissement = 'ENCAISSE' THEN v.montant_total_ttc ELSE 0 END) as montant_encaisse,
            SUM(CASE WHEN v.statut_encaissement = 'PARTIEL' THEN v.montant_total_ttc ELSE 0 END) as montant_partiel,
            COUNT(*) as total_ventes,
            SUM(CASE WHEN v.statut_encaissement = 'ENCAISSE' THEN 1 ELSE 0 END) as ventes_encaissees
        FROM ventes v
        WHERE DATE(v.date_vente) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total = (float)($result['montant_total_ventes'] ?? 0);
    $encaisse = (float)($result['montant_encaisse'] ?? 0);
    
    return [
        'montant_total' => $total,
        'montant_encaisse' => $encaisse,
        'montant_en_attente' => $total - $encaisse,
        'total_ventes' => (int)($result['total_ventes'] ?? 0),
        'ventes_encaissees' => (int)($result['ventes_encaissees'] ?? 0),
        'encaissement_rate' => $total > 0 ? round(($encaisse / $total) * 100, 1) : 0,
    ];
}

/**
 * Statistiques stock (ruptures, faibles stocks)
 */
function calculateStockStats(PDO $pdo): array {
    // Vérifier si la colonne stock_minimal existe
    $stmt = $pdo->prepare("SHOW COLUMNS FROM produits LIKE 'stock_minimal'");
    $stmt->execute();
    $hasStockMinimal = $stmt->rowCount() > 0;
    
    if ($hasStockMinimal) {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_produits,
                SUM(CASE WHEN stock_actuel <= stock_minimal THEN 1 ELSE 0 END) as produits_rupture,
                SUM(CASE WHEN stock_actuel > stock_minimal AND stock_actuel <= (stock_minimal * 1.5) THEN 1 ELSE 0 END) as produits_faible,
                SUM(stock_actuel) as quantite_total,
                SUM(stock_actuel * prix_vente) as valeur_stock
            FROM produits
            WHERE actif = 1
        ");
    } else {
        // Alternative si stock_minimal n'existe pas: utiliser seuil arbitraire
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_produits,
                SUM(CASE WHEN stock_actuel <= 5 THEN 1 ELSE 0 END) as produits_rupture,
                SUM(CASE WHEN stock_actuel > 5 AND stock_actuel <= 10 THEN 1 ELSE 0 END) as produits_faible,
                SUM(stock_actuel) as quantite_total,
                SUM(stock_actuel * prix_vente) as valeur_stock
            FROM produits
        ");
    }
    
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total = (int)($result['total_produits'] ?? 0);
    $ruptures = (int)($result['produits_rupture'] ?? 0);
    
    return [
        'total_produits' => $total,
        'produits_rupture' => $ruptures,
        'produits_faible_stock' => (int)($result['produits_faible'] ?? 0),
        'quantite_total' => (int)($result['quantite_total'] ?? 0),
        'valeur_stock' => (float)($result['valeur_stock'] ?? 0),
        'rupture_rate' => $total > 0 ? round(($ruptures / $total) * 100, 1) : 0,
    ];
}

/**
 * Alertes critiques (devis expirant, litiges en retard, etc)
 */
function getAlertsCritiques(PDO $pdo): array {
    $alerts = [];
    
    // Devis expirés (> 30 jours)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM devis
        WHERE statut = 'EN_ATTENTE' AND DATE_ADD(date_devis, INTERVAL 30 DAY) < CURDATE()
    ");
    $stmt->execute();
    $devis_expires = (int)$stmt->fetch()['count'];
    if ($devis_expires > 0) {
        $alerts[] = [
            'type' => 'warning',
            'icon' => 'bi-exclamation-triangle',
            'message' => "$devis_expires devis expirant (>30j)",
            'count' => $devis_expires,
        ];
    }
    
    // Litiges en cours (> 7 jours)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM retours_litiges
        WHERE statut_traitement = 'EN_COURS' AND DATE_ADD(date_retour, INTERVAL 7 DAY) < CURDATE()
    ");
    $stmt->execute();
    $litiges_retard = (int)$stmt->fetch()['count'];
    if ($litiges_retard > 0) {
        $alerts[] = [
            'type' => 'danger',
            'icon' => 'bi-exclamation-octagon',
            'message' => "$litiges_retard litiges en retard (>7j)",
            'count' => $litiges_retard,
        ];
    }
    
    // Stock ruptures (utiliser seuil arbitraire si stock_minimal n'existe pas)
    $stmt = $pdo->prepare("SHOW COLUMNS FROM produits LIKE 'stock_minimal'");
    $stmt->execute();
    $hasStockMinimal = $stmt->rowCount() > 0;
    
    if ($hasStockMinimal) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM produits
            WHERE actif = 1 AND stock_actuel <= stock_minimal
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM produits
            WHERE actif = 1 AND stock_actuel <= 5
        ");
    }
    
    $stmt->execute();
    $ruptures = (int)$stmt->fetch()['count'];
    if ($ruptures > 0) {
        $alerts[] = [
            'type' => 'danger',
            'icon' => 'bi-exclamation-circle',
            'message' => "$ruptures produits en rupture stock",
            'count' => $ruptures,
        ];
    }
    
    // Clients sans commande (> 60 jours)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM clients c
        WHERE c.id NOT IN (
            SELECT DISTINCT client_id FROM ventes
            WHERE DATE(date_vente) > DATE_SUB(CURDATE(), INTERVAL 60 DAY)
        )
    ");
    $stmt->execute();
    $clients_inactifs = (int)$stmt->fetch()['count'];
    if ($clients_inactifs > 0) {
        $alerts[] = [
            'type' => 'info',
            'icon' => 'bi-person-dash',
            'message' => "$clients_inactifs clients sans commande (>60j)",
            'count' => $clients_inactifs,
        ];
    }
    
    return $alerts;
}

/**
 * Données Chart.js: CA par jour (derniers 30 jours)
 */
function getChartCAParJour(PDO $pdo): array {
    $stmt = $pdo->prepare("
        SELECT 
            DATE(date_ecriture) as date,
            SUM(CASE WHEN source_type = 'vente' THEN montant ELSE 0 END) as ca_ventes,
            SUM(CASE WHEN source_type = 'reservation_hotel' THEN montant ELSE 0 END) as ca_hotel,
            SUM(CASE WHEN source_type = 'inscription_formation' THEN montant ELSE 0 END) as ca_formation,
            SUM(montant) as ca_total
        FROM caisse_journal
        WHERE DATE(date_ecriture) >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
            AND sens = 'ENTREE'
        GROUP BY DATE(date_ecriture)
        ORDER BY date ASC
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll();
    
    $labels = [];
    $ventes = [];
    $hotel = [];
    $formation = [];
    $total = [];
    
    // Remplir les 30 derniers jours (même les jours sans CA)
    $today = new DateTime();
    for ($i = 29; $i >= 0; $i--) {
        $date = (clone $today)->modify("-$i days");
        $dateStr = $date->format('Y-m-d');
        $labels[] = $date->format('d/m');
        
        // Chercher les données pour cette date
        $found = false;
        foreach ($rows as $row) {
            if ($row['date'] === $dateStr) {
                $ventes[] = (float)$row['ca_ventes'];
                $hotel[] = (float)$row['ca_hotel'];
                $formation[] = (float)$row['ca_formation'];
                $total[] = (float)$row['ca_total'];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $ventes[] = 0;
            $hotel[] = 0;
            $formation[] = 0;
            $total[] = 0;
        }
    }
    
    return [
        'labels' => $labels,
        'datasets' => [
            [
                'label' => 'Ventes',
                'data' => $ventes,
                'borderColor' => 'rgb(75, 192, 192)',
                'backgroundColor' => 'rgba(75, 192, 192, 0.1)',
                'tension' => 0.3,
            ],
            [
                'label' => 'Hôtel',
                'data' => $hotel,
                'borderColor' => 'rgb(255, 159, 64)',
                'backgroundColor' => 'rgba(255, 159, 64, 0.1)',
                'tension' => 0.3,
            ],
            [
                'label' => 'Formation',
                'data' => $formation,
                'borderColor' => 'rgb(153, 102, 255)',
                'backgroundColor' => 'rgba(153, 102, 255, 0.1)',
                'tension' => 0.3,
            ],
        ]
    ];
}

/**
 * Données Chart.js: Statut encaissement (donut chart)
 */
function getChartEncaissementStatut(PDO $pdo): array {
    $stmt = $pdo->prepare("
        SELECT 
            statut_encaissement,
            COUNT(*) as count,
            SUM(montant_total_ttc) as montant
        FROM ventes
        WHERE DATE(date_vente) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY statut_encaissement
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll();
    
    $labels = [];
    $data = [];
    $colors = [
        'ENCAISSE' => 'rgba(75, 192, 192, 0.8)',
        'PARTIEL' => 'rgba(255, 159, 64, 0.8)',
        'ATTENTE_PAIEMENT' => 'rgba(255, 99, 132, 0.8)',
    ];
    
    $colorList = [];
    foreach ($rows as $row) {
        $status = $row['statut_encaissement'];
        $labels[] = ucfirst(str_replace('_', ' ', $status));
        $data[] = (float)$row['montant'];
        $colorList[] = $colors[$status] ?? 'rgba(200, 200, 200, 0.8)';
    }
    
    return [
        'labels' => $labels,
        'datasets' => [
            [
                'data' => $data,
                'backgroundColor' => $colorList,
                'borderColor' => ['#fff', '#fff', '#fff'],
                'borderWidth' => 2,
            ]
        ]
    ];
}
