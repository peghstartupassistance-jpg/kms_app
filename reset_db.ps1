# Script PowerShell pour réinitialiser la base de données
$mysqlPath = "C:\xampp\mysql\bin\mysql.exe"
$sqlFile = "c:\xampp\htdocs\kms_app\kms_gestion (5).sql"

Write-Host "❌ Suppression de la base de données kms_gestion..." -ForegroundColor Yellow
& $mysqlPath -u root -e "DROP DATABASE IF EXISTS kms_gestion"
Write-Host "✅ Supprimée`n" -ForegroundColor Green

Write-Host "📦 Création de la nouvelle base de données..." -ForegroundColor Cyan
& $mysqlPath -u root -e "CREATE DATABASE kms_gestion CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
Write-Host "✅ Créée`n" -ForegroundColor Green

Write-Host "📥 Importation du schéma SQL..." -ForegroundColor Cyan
Get-Content $sqlFile | & $mysqlPath -u root kms_gestion

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Schéma importé`n" -ForegroundColor Green
    
    Write-Host "📊 Vérification des tables..." -ForegroundColor Cyan
    $tableCount = & $mysqlPath -u root -e "SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = 'kms_gestion'" -s -N
    Write-Host "📊 Nombre de tables créées: $tableCount`n" -ForegroundColor Green
    
    Write-Host "✅ Réinitialisation complète terminée !" -ForegroundColor Green
    Write-Host "🚀 L'application est prête à l'emploi." -ForegroundColor Cyan
} else {
    Write-Host "❌ Erreur lors de l'importation du schéma" -ForegroundColor Red
    exit 1
}
