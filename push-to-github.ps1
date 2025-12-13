# Script de push automatique vers GitHub
# Usage: .\push-to-github.ps1

Write-Host "============================================" -ForegroundColor Cyan
Write-Host "  PUSH AUTOMATIQUE VERS GITHUB" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

# Se placer dans le répertoire du projet
Set-Location "c:\xampp\htdocs\kms_app"

Write-Host "[1/5] Vérification de l'état Git..." -ForegroundColor Yellow
$status = git status --short
if ($status) {
    Write-Host "Fichiers modifiés détectés:" -ForegroundColor Green
    git status --short
    
    Write-Host "`n[2/5] Ajout des fichiers..." -ForegroundColor Yellow
    git add .
    Write-Host "OK" -ForegroundColor Green
} else {
    Write-Host "Aucune modification détectée" -ForegroundColor Green
}

Write-Host "`n[3/5] Vérification des commits non poussés..." -ForegroundColor Yellow
$commits = git log origin/main..HEAD --oneline 2>$null
if ($commits) {
    Write-Host "Commits à pousser:" -ForegroundColor Green
    git log origin/main..HEAD --oneline
} else {
    Write-Host "Aucun commit à pousser (ou branche distante non encore créée)" -ForegroundColor Yellow
}

Write-Host "`n[4/5] Configuration du Credential Manager..." -ForegroundColor Yellow
git config --global credential.helper manager
Write-Host "OK - Git Credential Manager activé" -ForegroundColor Green

Write-Host "`n[5/5] Push vers GitHub..." -ForegroundColor Yellow
Write-Host "Repository: https://github.com/peghstartupassistance-jpg/kms_app" -ForegroundColor Cyan
Write-Host ""
Write-Host "ATTENTION: Une fenêtre de navigateur va s'ouvrir pour l'authentification GitHub" -ForegroundColor Magenta
Write-Host "Connectez-vous avec votre compte GitHub: peghstartupassistance-jpg" -ForegroundColor Magenta
Write-Host ""

# Tentative de push
$pushResult = git push -u origin main 2>&1

if ($LASTEXITCODE -eq 0) {
    Write-Host "`n============================================" -ForegroundColor Green
    Write-Host "  PUSH REUSSI !" -ForegroundColor Green
    Write-Host "============================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "Vérifiez votre dépôt sur:" -ForegroundColor Cyan
    Write-Host "https://github.com/peghstartupassistance-jpg/kms_app" -ForegroundColor White
    Write-Host ""
    Write-Host "Le déploiement automatique vers Bluehost va démarrer..." -ForegroundColor Cyan
    Write-Host "Surveillez: https://github.com/peghstartupassistance-jpg/kms_app/actions" -ForegroundColor White
    Write-Host ""
    Write-Host "Votre site sera mis à jour sur:" -ForegroundColor Cyan
    Write-Host "https://kennemulti-services.com/kms_app" -ForegroundColor White
    Write-Host ""
} else {
    Write-Host "`n============================================" -ForegroundColor Red
    Write-Host "  ERREUR LORS DU PUSH" -ForegroundColor Red
    Write-Host "============================================" -ForegroundColor Red
    Write-Host ""
    Write-Host $pushResult
    Write-Host ""
    Write-Host "Solutions possibles:" -ForegroundColor Yellow
    Write-Host "1. Vérifiez votre connexion internet" -ForegroundColor White
    Write-Host "2. Authentifiez-vous dans la fenêtre du navigateur qui s'est ouverte" -ForegroundColor White
    Write-Host "3. Si la fenêtre ne s'ouvre pas, créez un Personal Access Token:" -ForegroundColor White
    Write-Host "   https://github.com/settings/tokens" -ForegroundColor Cyan
    Write-Host ""
}

Write-Host "Appuyez sur une touche pour fermer..." -ForegroundColor Gray
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
