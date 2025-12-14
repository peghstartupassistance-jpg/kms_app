<?php
require_once __DIR__ . '/security.php';
exigerConnexion();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Dropdown Bootstrap</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1>Test Dropdown Bootstrap</h1>
        
        <div class="alert alert-info">
            <strong>Test 1:</strong> Dropdown simple
        </div>
        
        <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" type="button" id="dropdownTest1" data-bs-toggle="dropdown" aria-expanded="false">
                Test Simple
            </button>
            <ul class="dropdown-menu" aria-labelledby="dropdownTest1">
                <li><a class="dropdown-item" href="#">Action 1</a></li>
                <li><a class="dropdown-item" href="#">Action 2</a></li>
                <li><a class="dropdown-item" href="#">Action 3</a></li>
            </ul>
        </div>

        <div class="alert alert-info mt-4">
            <strong>Test 2:</strong> Dropdown avec statuts
        </div>

        <div class="dropdown">
            <button class="btn btn-sm btn-outline-warning dropdown-toggle" type="button" id="dropdownTest2" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-plus me-1"></i>
                Prospect
            </button>
            <ul class="dropdown-menu" aria-labelledby="dropdownTest2">
                <li>
                    <a class="dropdown-item active" href="#">
                        <i class="bi bi-person-plus me-2 text-warning"></i>
                        Prospect
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#">
                        <i class="bi bi-person-check me-2 text-success"></i>
                        Client
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#">
                        <i class="bi bi-mortarboard me-2 text-info"></i>
                        Apprenant
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#">
                        <i class="bi bi-house-door me-2 text-primary"></i>
                        Hôte
                    </a>
                </li>
            </ul>
        </div>

        <div class="alert alert-warning mt-4">
            <strong>Console Debug:</strong> Ouvrir F12 et regarder les messages
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('=== TEST BOOTSTRAP DROPDOWN ===');
            console.log('Bootstrap loaded:', typeof bootstrap !== 'undefined');
            console.log('Bootstrap version:', typeof bootstrap !== 'undefined' ? bootstrap : 'NOT FOUND');
            
            const dropdowns = document.querySelectorAll('[data-bs-toggle="dropdown"]');
            console.log('Dropdowns trouvés:', dropdowns.length);
            
            dropdowns.forEach((btn, index) => {
                console.log(`Dropdown ${index + 1}:`, btn);
                
                btn.addEventListener('click', function(e) {
                    console.log('Click sur dropdown', index + 1, e);
                });
                
                btn.addEventListener('show.bs.dropdown', function(e) {
                    console.log('Dropdown ouvert', index + 1);
                });
            });
        });
    </script>
</body>
</html>
