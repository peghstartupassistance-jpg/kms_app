<?php
/**
 * Phase 1.2.2 - Update all caisse_journal reads to journal_caisse
 * 
 * This script demonstrates the necessary updates.
 * These changes should be made in the codebase to complete the unification.
 */

$files_to_update = [
    // Dashboard/index
    'index.php' => [
        'old_column' => 'date_ecriture',
        'new_column' => 'date_operation',
        'old_sens_values' => ['ENTREE', 'SORTIE'],
        'new_sens_values' => ['RECETTE', 'DEPENSE'],
    ],
    
    // Ventes detail
    'ventes/print.php' => [
        'issue' => 'References journal_caisse columns that don\'t exist in caisse_journal',
        'needs_update' => ['vente_id', 'est_annule', 'sens=RECETTE']
    ],
    
    'ventes/detail_360.php' => [
        'issue' => 'Reads from caisse_journal instead of journal_caisse',
        'update_from' => 'caisse_journal cj',
        'update_to' => 'journal_caisse cj'
    ],
    
    // Trésorerie
    'caisse/journal.php' => [
        'status' => 'OK (already uses journal_caisse)'
    ],
    
    // Coordination
    'coordination/verification_synchronisation.php' => [
        'issue' => 'Uses caisse_journal instead of journal_caisse',
        'update_from' => 'caisse_journal',
        'update_to' => 'journal_caisse'
    ],
    
    // Helper
    'lib/navigation_helpers.php' => [
        'issue' => 'Uses caisse_journal for CA calculations',
        'update_from' => 'caisse_journal',
        'update_to' => 'journal_caisse',
        'also_update_sens' => ['ENTREE => RECETTE', 'SORTIE => DEPENSE']
    ],
    
    // Integration scripts
    'integrer_hotel_formation_caisse.php' => [
        'issue' => 'Uses caisse_journal for INSERT/DELETE',
        'update_from' => 'caisse_journal',
        'update_to' => 'journal_caisse'
    ],
];

// Manual mapping of schema changes:
$schema_mapping = [
    'date_ecriture' => 'date_operation',
    'sens=ENTREE' => 'sens=RECETTE',
    'sens=SORTIE' => 'sens=DEPENSE',
];

?>
<html>
<head>
    <title>Phase 1.2.2 - Caisse Schema Update Reference</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .file { background: #f0f0f0; padding: 10px; margin: 10px 0; border-left: 4px solid #0066cc; }
        .status { padding: 5px 10px; border-radius: 3px; font-weight: bold; }
        .ok { background: #d4edda; color: #155724; }
        .warning { background: #fff3cd; color: #856404; }
        .error { background: #f8d7da; color: #721c24; }
        code { background: #f5f5f5; padding: 2px 5px; }
    </style>
</head>
<body>
    <h1>Phase 1.2.2 - Caisse Schema Unification Reference</h1>
    
    <h2>Files to Update</h2>
    
    <?php foreach ($files_to_update as $file => $info): ?>
    <div class="file">
        <h3><?= htmlspecialchars($file) ?></h3>
        
        <?php if (isset($info['status'])): ?>
            <p><span class="status ok"><?= htmlspecialchars($info['status']) ?></span></p>
        <?php else: ?>
            <?php if (isset($info['issue'])): ?>
                <p><strong>Issue:</strong> <?= htmlspecialchars($info['issue']) ?></p>
            <?php endif; ?>
            
            <?php if (isset($info['update_from'])): ?>
                <p>
                    <strong>Update:</strong><br/>
                    <code>FROM <?= htmlspecialchars($info['update_from']) ?></code><br/>
                    to<br/>
                    <code>FROM <?= htmlspecialchars($info['update_to']) ?></code>
                </p>
            <?php endif; ?>
            
            <?php if (isset($info['also_update_sens'])): ?>
                <p><strong>Also update column sens values:</strong></p>
                <ul>
                    <?php foreach ($info['also_update_sens'] as $mapping): ?>
                        <li><code><?= htmlspecialchars($mapping) ?></code></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <?php if (isset($info['needs_update'])): ?>
                <p><strong>Affected columns/values:</strong></p>
                <ul>
                    <?php foreach ($info['needs_update'] as $col): ?>
                        <li><code><?= htmlspecialchars($col) ?></code></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <p><span class="status warning">ACTION REQUIRED</span></p>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    
    <h2>Schema Mapping Summary</h2>
    <table border="1" cellpadding="10" style="border-collapse: collapse; width: 100%;">
        <tr style="background: #f5f5f5;">
            <th>Old (caisse_journal)</th>
            <th>New (journal_caisse)</th>
        </tr>
        <tr>
            <td><code>date_ecriture</code></td>
            <td><code>date_operation</code></td>
        </tr>
        <tr>
            <td><code>sens = 'ENTREE'</code></td>
            <td><code>sens = 'RECETTE'</code></td>
        </tr>
        <tr>
            <td><code>sens = 'SORTIE'</code></td>
            <td><code>sens = 'DEPENSE'</code></td>
        </tr>
        <tr>
            <td><code>N/A</code></td>
            <td><code>vente_id</code> (for FK linking)</td>
        </tr>
        <tr>
            <td><code>N/A</code></td>
            <td><code>est_annule</code> (soft delete)</td>
        </tr>
    </table>
    
    <h2>Next Steps</h2>
    <ol>
        <li>Run migration script: <code>admin/migrate_phase_1_2.php</code></li>
        <li>Update each file listed above according to the specifications</li>
        <li>Run health check to verify consistency: <code>admin/health.php</code></li>
        <li>Test end-to-end flows: vente creation → encaissement → dashboard CA</li>
    </ol>
</body>
</html>
