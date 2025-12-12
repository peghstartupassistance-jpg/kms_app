# tests/http_tests_run.ps1
$baseUrl = 'http://localhost/kms_app'
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

# Add provided PHPSESSID cookie
$phpSess = 'lq6v6f45ldaavbihfgmubhhi5j'
$cookie = New-Object System.Net.Cookie('PHPSESSID', $phpSess, '/', 'localhost')
$session.Cookies.Add($cookie)

function Get-CsrfToken($url) {
    $r = Invoke-WebRequest -Uri $url -WebSession $session -UseBasicParsing -ErrorAction Stop
    $m = [regex]::Match($r.Content, 'name="csrf_token"\s+value="([^"]+)"')
    if ($m.Success) { return $m.Groups[1].Value }
    return $null
}

Write-Host "Base URL: $baseUrl"

# 1) Create product
Write-Host "\n==> 1) Création du produit TEST-PRD-001"
$token = Get-CsrfToken "$baseUrl/produits/edit.php"
if (-not $token) { Write-Error "CSRF token introuvable pour création produit"; exit 1 }

$body = @{
    csrf_token = $token
    id = 0
    code_produit = 'TEST-PRD-001'
    designation = 'Produit test automatisé'
    actif = 'on'
    famille_id = 1
    prix_achat = 1000
    prix_vente = 1500
    stock_initial = 5
    localisation = 'Magasin test'
    caracteristiques = 'auto test'
    description = 'Création via script automatique'
}

$r = Invoke-WebRequest -Uri "$baseUrl/produits/edit.php" -Method Post -Body $body -WebSession $session -MaximumRedirection 0 -ErrorAction SilentlyContinue
Write-Host "HTTP status (create):" $r.StatusCode

Write-Host "\nRequête DB: recherche produit TEST-PRD-001"
$json = (php "tests/check_product_by_code.php" "TEST-PRD-001") 2>&1
Write-Host $json
try { $obj = $json | ConvertFrom-Json } catch { Write-Error "Produit non trouvé ou JSON invalide:"; Write-Host $json; exit 1 }
$prodId = $obj.id
Write-Host "Produit ID trouvé: $prodId, stock_actuel: $($obj.stock_actuel)"

# 3) Ajuster le produit (ajustement_stock = -2)
Write-Host "\n==> 2) Ajustement de stock: -2 sur produit ID $prodId"
$token2 = Get-CsrfToken "$baseUrl/produits/edit.php?id=$prodId"
if (-not $token2) { Write-Error "CSRF token introuvable pour édition produit"; exit 1 }

# To avoid overwriting unknown fields, we'll fetch the current form values and reuse them in body
$page = Invoke-WebRequest -Uri "$baseUrl/produits/edit.php?id=$prodId" -WebSession $session -UseBasicParsing -ErrorAction Stop
# crude extraction of some fields
$code = [regex]::Match($page.Content, 'name="code_produit"[^>]*value="([^"]*)"').Groups[1].Value
$designation = [regex]::Match($page.Content, 'name="designation"[^>]*value="([^"]*)"').Groups[1].Value
$prix_vente = [regex]::Match($page.Content, 'name="prix_vente"[^>]*value="([^"]*)"').Groups[1].Value
$famille = [regex]::Match($page.Content, 'name="famille_id"[^>]*>\s*<option value="([^"]+)" selected').Groups[1].Value
if (-not $famille) { $famille = 1 }

$body2 = @{
    csrf_token = $token2
    id = $prodId
    code_produit = $code
    designation = $designation
    famille_id = $famille
    prix_vente = $prix_vente
    ajustement_stock = -2
}

$r2 = Invoke-WebRequest -Uri "$baseUrl/produits/edit.php?id=$prodId" -Method Post -Body $body2 -WebSession $session -MaximumRedirection 0 -ErrorAction SilentlyContinue
Write-Host "HTTP status (ajustement):" $r2.StatusCode

# 4) Vérifier en base les derniers mouvements pour ce produit
Write-Host "\nRequête DB: mouvements récents pour le produit"
Write-Host "\nMouvements récents pour le produit (DB):"
$mvtJson = (php "tests/check_movements_for_product.php" "$prodId") 2>&1
Write-Host $mvtJson

Write-Host "\nComparaison stock_actuel vs stock théorique (lib/stock.php):"
$cmp = (php "tests/compare_stock_product.php" "$prodId") 2>&1
Write-Host $cmp

# 5) Générer un BL pour une vente existante
Write-Host "\n==> 3) Génération automatique d'un BL (tentative)"
# Find a vente with lines and not fully delivered (via PHP check)
# find a vente via helper script
$venteId = (php "tests/find_vente_with_lines.php") 2>&1
if ($venteId -eq '' -or $venteId -eq '0') { Write-Host 'Aucune vente trouvée pour BL'; exit 0 }
Write-Host "Vente choisie pour BL: $venteId"
$token3 = Get-CsrfToken "$baseUrl/ventes/edit.php?id=$venteId"
if (-not $token3) { $token3 = Get-CsrfToken "$baseUrl/ventes/detail.php?id=$venteId" }
if (-not $token3) { Write-Error "CSRF token introuvable pour BL"; exit 1 }

$body3 = @{ csrf_token = $token3; vente_id = $venteId }
$r3 = Invoke-WebRequest -Uri "$baseUrl/ventes/generer_bl.php" -Method Post -Body $body3 -WebSession $session -MaximumRedirection 0 -ErrorAction SilentlyContinue
Write-Host "HTTP status (generer_bl):" $r3.StatusCode

# Vérifier mouvements liés à la vente
Write-Host "Mouvements liés à la vente (count):"
$cnt = (php "tests/count_movements_for_vente.php" "$venteId") 2>&1
Write-Host $cnt

Write-Host "\nScript HTTP terminé."
