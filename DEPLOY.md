# Deployment Guide (Bluehost)

This project deploys to a subfolder at: https://kennemulti-services.com/kms_app

Two supported methods:

1) Local deploy from VS Code (simple)
2) GitHub Actions CI deploy (automated)

---

## 1) Local deploy from VS Code

We use the VS Code “SFTP” extension config stored locally in `.vscode/sftp.json` (ignored by Git).

- Server: ftp.kennemulti-services.com (Explicit FTPS, port 21)
- Remote path (FTP chrooted): `/kms_app` → déployé dans `/home2/kdfvxvmy/public_html/kms_app`

Steps:
1. Install extension “SFTP” (by liximomo) in VS Code
2. Open the project folder
3. Right-click the root folder → “SFTP: Upload Folder”
4. Visit https://kennemulti-services.com/kms_app to verify

Notes:
- `.vscode/` is ignored by Git to keep credentials out of the repo.
- You can set `uploadOnSave` to `true` in `.vscode/sftp.json` if you prefer instant deploy.

---

## 2) Deploy via GitHub Actions (CI)

We include `.github/workflows/ftp-deploy.yml` which deploys on pushes to `main` or manually.

Set these repository Secrets in GitHub (Settings → Secrets and variables → Actions):
- `FTP_SERVER` = ftp.kennemulti-services.com
- `FTP_USERNAME` = <FTP login pointant sur /home2/kdfvxvmy/public_html>
- `FTP_PASSWORD` = <FTP password>
- `FTP_REMOTE_DIR` = /kms_app  (la racine FTP est /home2/kdfvxvmy/public_html)

Trigger:
- Push to `main` or run the workflow via “Run workflow”.

Tip:
- If your FTP account’s root is different, adjust `FTP_REMOTE_DIR` accordingly.

---

## Server-side structure


Ensure this directory exists. If not, create `kms_app` under the domain root.

CI trigger note: this line bumps deploy.

## Safety & best practices

- Never commit credentials: `.vscode/` is ignored by Git
- Keep `security.php` requirements on every page, enforce permissions, CSRF, and prepared statements
- Test locally before deploying; use `tests/` scripts where applicable
- Large files not needed online (e.g., local dumps) should be excluded from deploy via ignore lists

---

## Quick checks after deploy

- Open https://kennemulti-services.com/kms_app
- Test login and key pages:
  - Comptabilité: `compta/index.php`, `compta/balance.php`
  - Stock: `stock/etat.php`, `stock/mouvements.php`
  - Ventes: `ventes/list.php`
- If DB connection fails, verify `db/db.php` credentials on the server

Note: CI deploy triggers automatically on pushes to `main`.

Auto-trigger tip: any commit on `main` starts a new deploy.
Target path (Bluehost): `/home2/kdfvxvmy/public_html/kms_app` (CI forced absolute path)
Ensure folder `kms_app` exists under `public_html` before deploy.
CI deploy uses absolute path; local SFTP can stay on `/home2/kdfvxvmy/public_html/kms_app`.

