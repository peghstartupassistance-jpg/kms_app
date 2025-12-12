# KMS Gestion – Copilot AI Agent Instructions

## Project Overview
**KMS Gestion** is an internal commercial management web app for Kenne Multi-Services (KMS), built with PHP 8, MySQL, and Bootstrap 5. It centralizes business, stock, cash, hotel, training, and accounting operations. The codebase is modular, with strict security and business logic conventions.

## Architecture & Key Patterns
- **Backend:** PHP 8 (PDO, prepared statements, no ORM)
- **Frontend:** HTML5, Bootstrap 5, vanilla JS
- **Database:** MySQL/MariaDB (see `kms_gestion.sql` for schema)
- **Authentication:** PHP sessions, `password_hash`, CSRF tokens, role/permission checks
- **Routing:** Use `url_for()` for all internal links/redirects (see examples below)
- **Security:** All pages must require `security.php` and enforce `exigerConnexion()` and `exigerPermission()`

## Core Modules & Data Flows
- **Stock:** All stock changes (sales, purchases, adjustments) must go through `lib/stock.php` (never update `produits.stock_actuel` directly). Each movement is logged in `stocks_mouvements`.
- **Accounting:** Double-entry (OHADA) logic in `lib/compta.php`. Each sale/purchase triggers auto journal entries and piece creation. Validation workflow in `compta/valider_piece.php`.
- **Sales/Deliveries:** Devis → Vente → BL → Stock out → Cash in. See `ventes/`, `devis/`, `livraisons/`.
- **Clients:** Types and statuses are managed in `clients/`, `showroom/`, `terrain/`.
- **Cash:** All cash operations go through `lib/caisse.php` and `caisse/`.

## Security & Permissions
**Pattern for every page:**
```php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('MODULE_ACTION'); // e.g. VENTES_LIRE, PRODUITS_CREER
global $pdo;
```
Roles: `ADMIN`, `SHOWROOM`, `TERRAIN`, `MAGASINIER`, `CAISSIER`, `DIRECTION` (see `security.php`)

## Project-Specific Conventions
- **Always use prepared statements** for DB access (never interpolate variables in SQL)
- **All form POSTs must check CSRF** via `verifierCsrf()`
- **Flash messages:** Set in `$_SESSION` and redirect with `url_for()`
- **Monetary values:** Store as `DECIMAL(15,2)`, display with `number_format($montant, 2, ',', ' ')`
- **Stock/Accounting:** Never update stock or accounting tables directly—always use the provided API functions

## Examples
**Correct URL usage:**
```php
header('Location: ' . url_for('ventes/list.php'));
<a href="<?= url_for('produits/edit.php?id=5') ?>">Edit</a>
```
**Incorrect:**
```php
header('Location: /ventes/list.php');
```

## Developer Workflows
- **Comptabilité:**
  1. Check/activate exercise (`compta/exercices.php`)
  2. Test mappings (`compta/parametrage_mappings.php`)
  3. Create sale/purchase, verify auto entries
  4. Validate piece (`compta/valider_piece.php`)
  5. Check balance (`compta/balance.php`)
- **Debugging:**
  - Use scripts in root (e.g. `debug_balance_ecart.php`, `test_balance.php`)
  - For missing entries, check mapping in `compta_mapping_operations` and function calls in `lib/compta.php`

## Key Files & Directories
- `security.php` – Auth, permissions, CSRF
- `lib/compta.php`, `lib/stock.php`, `lib/caisse.php` – Core business logic APIs
- `compta/README_COMPTA.md` – Accounting module doc
- `kms_gestion.sql` – Full DB schema
- `tests/` – Test scripts

## References
- See `historique.md` for project history
- Each module may have its own README

---
**For unclear or missing conventions, consult the comments in `lib/compta.php` or module README files.**
