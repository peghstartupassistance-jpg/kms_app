@echo off
cls
echo ================================================
echo     MODULE MARKETING KMS - TESTS COMPLETS
echo ================================================
echo.
echo [1/3] Verification de la syntaxe PHP...
echo.

C:\xampp\php\php.exe -l digital\leads_list.php > nul 2>&1
if %errorlevel%==0 (echo   [OK] digital/leads_list.php) else (echo   [ERREUR] digital/leads_list.php)

C:\xampp\php\php.exe -l digital\leads_edit.php > nul 2>&1
if %errorlevel%==0 (echo   [OK] digital/leads_edit.php) else (echo   [ERREUR] digital/leads_edit.php)

C:\xampp\php\php.exe -l digital\leads_conversion.php > nul 2>&1
if %errorlevel%==0 (echo   [OK] digital/leads_conversion.php) else (echo   [ERREUR] digital/leads_conversion.php)

C:\xampp\php\php.exe -l coordination\ruptures.php > nul 2>&1
if %errorlevel%==0 (echo   [OK] coordination/ruptures.php) else (echo   [ERREUR] coordination/ruptures.php)

C:\xampp\php\php.exe -l coordination\litiges.php > nul 2>&1
if %errorlevel%==0 (echo   [OK] coordination/litiges.php) else (echo   [ERREUR] coordination/litiges.php)

C:\xampp\php\php.exe -l coordination\ordres_preparation.php > nul 2>&1
if %errorlevel%==0 (echo   [OK] coordination/ordres_preparation.php) else (echo   [ERREUR] coordination/ordres_preparation.php)

C:\xampp\php\php.exe -l reporting\dashboard_marketing.php > nul 2>&1
if %errorlevel%==0 (echo   [OK] reporting/dashboard_marketing.php) else (echo   [ERREUR] reporting/dashboard_marketing.php)

C:\xampp\php\php.exe -l reporting\relances_devis.php > nul 2>&1
if %errorlevel%==0 (echo   [OK] reporting/relances_devis.php) else (echo   [ERREUR] reporting/relances_devis.php)

C:\xampp\php\php.exe -l showroom\visiteur_convertir_devis.php > nul 2>&1
if %errorlevel%==0 (echo   [OK] showroom/visiteur_convertir_devis.php) else (echo   [ERREUR] showroom/visiteur_convertir_devis.php)

echo.
echo [2/3] Verification des fichiers crees...
echo.

if exist "digital\leads_list.php" (echo   [OK] Module DIGITAL complet) else (echo   [ERREUR] Module DIGITAL incomplet)
if exist "coordination\ordres_preparation.php" (echo   [OK] Module Coordination complet) else (echo   [ERREUR] Module Coordination incomplet)
if exist "reporting\dashboard_marketing.php" (echo   [OK] Dashboard Marketing) else (echo   [ERREUR] Dashboard Marketing manquant)
if exist "reporting\relances_devis.php" (echo   [OK] Systeme Relances) else (echo   [ERREUR] Systeme Relances manquant)
if exist "marketing\README_MARKETING.md" (echo   [OK] Documentation) else (echo   [ERREUR] Documentation manquante)
if exist "db\extensions_marketing_complement.sql" (echo   [OK] Script SQL complementaire) else (echo   [ERREUR] Script SQL manquant)

echo.
echo [3/3] Test de la base de donnees...
echo.
C:\xampp\php\php.exe test_module_marketing.php

echo.
echo ================================================
echo Consulter RAPPORT_TESTS_MARKETING.md pour details
echo ================================================
pause
