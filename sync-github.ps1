# Script de synchronisation GitHub pour KMS Gestion
# Usage: .\sync-github.ps1 "Message de commit"

param(
    [string]$Message = "Mise à jour automatique"
)

Write-Host "🔄 Synchronisation avec GitHub..." -ForegroundColor Cyan
Write-Host ""

# Étape 1: Récupérer les changements distants
Write-Host "📥 Récupération des changements distants..." -ForegroundColor Yellow
try {
    git fetch origin main
    Write-Host "✅ Fetch terminé" -ForegroundColor Green
} catch {
    Write-Host "❌ Erreur lors du fetch" -ForegroundColor Red
    exit 1
}

# Étape 2: Vérifier l'état local
Write-Host ""
Write-Host "📋 Vérification de l'état local..." -ForegroundColor Yellow
$status = git status --porcelain

if ($status) {
    Write-Host "📝 Fichiers modifiés détectés:" -ForegroundColor Cyan
    git status --short
    
    # Ajouter tous les fichiers
    Write-Host ""
    Write-Host "➕ Ajout des fichiers..." -ForegroundColor Yellow
    git add -A
    
    # Créer le commit
    Write-Host "💾 Création du commit..." -ForegroundColor Yellow
    git commit -m $Message
    Write-Host "✅ Commit créé" -ForegroundColor Green
} else {
    Write-Host "ℹ️  Aucune modification locale" -ForegroundColor Gray
}

# Étape 3: Fusionner les changements distants
Write-Host ""
Write-Host "🔀 Fusion avec la branche distante..." -ForegroundColor Yellow
try {
    git pull origin main --no-rebase
    Write-Host "✅ Pull terminé" -ForegroundColor Green
} catch {
    Write-Host "⚠️  Conflits possibles - vérifiez manuellement" -ForegroundColor Yellow
}

# Étape 4: Pousser vers GitHub
Write-Host ""
Write-Host "📤 Envoi vers GitHub..." -ForegroundColor Yellow
try {
    git push origin main
    Write-Host "✅ Push terminé avec succès!" -ForegroundColor Green
} catch {
    Write-Host "❌ Erreur lors du push" -ForegroundColor Red
    Write-Host "ℹ️  Essayez manuellement: git push origin main" -ForegroundColor Cyan
    exit 1
}

# Afficher le statut final
Write-Host ""
Write-Host "📊 Statut final:" -ForegroundColor Cyan
git log --oneline -n 3
Write-Host ""
Write-Host "🎉 Synchronisation terminée!" -ForegroundColor Green
Write-Host ""
Write-Host "🌐 Vérifiez sur: https://github.com/peghstartupassistance-jpg/kms_app" -ForegroundColor Cyan
Write-Host "🚀 Déploiement auto: https://kennemulti-services.com/kms_app" -ForegroundColor Cyan
