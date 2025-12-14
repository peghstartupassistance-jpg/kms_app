<?php
require_once __DIR__ . '/../security.php';
exigerConnexion();
global $pdo;

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <h1>Test Sidebar Toggle</h1>
    <div class="alert alert-info">
        <strong>Instructions:</strong> Cliquez sur le bouton en haut à gauche pour plier/déplier la sidebar.
    </div>
    
    <div class="card">
        <div class="card-header">Debug Info</div>
        <div class="card-body">
            <p><strong>Vérifiez la console JS (F12) pour les erreurs.</strong></p>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                console.log('✅ DOMContentLoaded event fired');
                
                const btn = document.getElementById('toggleSidebarBtn');
                const sidebar = document.querySelector('.kms-sidebar');
                const layout = document.getElementById('layoutRoot');
                
                console.log('Toggle button found:', !!btn);
                console.log('Sidebar found:', !!sidebar);
                console.log('Layout found:', !!layout);
                
                if (btn) {
                    console.log('✅ Button click listener will be attached by footer.php');
                    btn.addEventListener('click', function() {
                        console.log('🔄 Toggle button clicked');
                    });
                }
                
                // Test localStorage
                console.log('LocalStorage test:', localStorage.getItem('kms.sidebar.collapsed'));
            });
            </script>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
