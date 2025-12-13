# Instructions pour Pousser vers GitHub

## ⚠️ Problème Détecté

Le push vers GitHub échoue car **GitHub nécessite une authentification**.

Depuis août 2021, GitHub n'accepte plus les mots de passe pour les opérations Git. Vous devez utiliser un **Personal Access Token (PAT)** ou une **clé SSH**.

## ✅ Solution Recommandée : Personal Access Token

### Étape 1 : Créer un Personal Access Token

1. **Connectez-vous à GitHub** : https://github.com
2. **Allez dans Settings** :
   - Cliquez sur votre photo de profil (en haut à droite)
   - Sélectionnez **Settings**
3. **Developer settings** :
   - Dans le menu de gauche, tout en bas, cliquez sur **Developer settings**
4. **Personal access tokens** :
   - Cliquez sur **Tokens (classic)**
   - Cliquez sur **Generate new token (classic)**
5. **Configurez le token** :
   - **Note** : `KMS App Token` (pour vous rappeler à quoi il sert)
   - **Expiration** : 90 days (ou No expiration si vous préférez)
   - **Cochez les permissions suivantes** :
     - ✅ `repo` (accès complet aux dépôts)
     - ✅ `workflow` (si vous utilisez GitHub Actions)
6. **Générer** :
   - Cliquez sur **Generate token** en bas
7. **⚠️ IMPORTANT : Copiez le token immédiatement !**
   - Le token ressemble à : `ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`
   - Vous ne pourrez plus le voir après avoir quitté cette page
   - **Sauvegardez-le dans un endroit sûr**

### Étape 2 : Utiliser le Token pour Pousser

Une fois le token créé, ouvrez PowerShell et exécutez :

```powershell
cd c:\xampp\htdocs\kms_app

# Pousser avec le token
# Remplacez YOUR_TOKEN par le token que vous avez copié
git push https://YOUR_TOKEN@github.com/peghstartupassistance-jpg/kms_app.git main
```

**Exemple** (avec un faux token) :
```powershell
git push https://ghp_abc123def456ghi789@github.com/peghstartupassistance-jpg/kms_app.git main
```

### Étape 3 : Sauvegarder les Identifiants (Optionnel)

Pour ne pas avoir à entrer le token à chaque fois :

```powershell
# Configurer Git pour mémoriser vos identifiants
git config --global credential.helper wincred

# Puis pousser normalement (Git vous demandera le token UNE FOIS)
git push origin main
```

Lors du prompt, utilisez :
- **Username** : votre nom d'utilisateur GitHub
- **Password** : collez le Personal Access Token (PAS votre mot de passe GitHub)

## 🔐 Alternative : Utiliser une Clé SSH (Plus Sécurisé)

Si vous préférez SSH :

### 1. Générer une clé SSH

```powershell
ssh-keygen -t ed25519 -C "kms@kenne-multiservices.com"
```

Appuyez sur Entrée 3 fois (emplacement par défaut, pas de passphrase).

### 2. Copier la clé publique

```powershell
Get-Content ~/.ssh/id_ed25519.pub | clip
```

### 3. Ajouter la clé à GitHub

1. Allez sur https://github.com/settings/keys
2. Cliquez sur **New SSH key**
3. Titre : `KMS Development Machine`
4. Collez la clé (Ctrl+V)
5. Cliquez sur **Add SSH key**

### 4. Changer l'URL du remote en SSH

```powershell
git remote set-url origin git@github.com:peghstartupassistance-jpg/kms_app.git
```

### 5. Pousser

```powershell
git push origin main
```

## 📊 État Actuel de Votre Projet

Vous avez **2 commits** prêts à être poussés :

1. **Commit 1** (90e721b) :
   ```
   feat: Modernisation complète des interfaces - list.php et edit.php
   - 24 pages list.php modernisées
   - 13 pages edit.php modernisées
   - Frameworks CSS/JS (modern-lists + modern-forms)
   - Documentation complète
   ```

2. **Commit 2** (e227f02) :
   ```
   feat: Ajout système sécurité 2FA, sessions actives, audit et mise à jour BDD
   - Tables de sécurité (audit_log, blocages_ip, sessions_actives)
   - Système 2FA (TOTP, SMS, recovery codes)
   - Gestion des tentatives de connexion
   - Paramètres de sécurité configurables
   - Mise à jour du schéma de base de données
   ```

**Nombre total de fichiers à pousser :** ~282 fichiers

## ✅ Commande Complète pour Push Immédiat

```powershell
cd c:\xampp\htdocs\kms_app

# Remplacez YOUR_TOKEN par votre Personal Access Token
git push https://YOUR_TOKEN@github.com/peghstartupassistance-jpg/kms_app.git main
```

Après cela, votre code sera en ligne sur :
https://github.com/peghstartupassistance-jpg/kms_app

Et le déploiement automatique vers Bluehost se fera via GitHub Actions.

## 🚀 Déploiement Automatique

Une fois le push réussi :
1. GitHub Actions se déclenchera automatiquement
2. Vos fichiers seront déployés sur : https://kennemulti-services.com/kms_app
3. Surveillez le déploiement sur : https://github.com/peghstartupassistance-jpg/kms_app/actions

---

**Besoin d'aide ?** Si vous rencontrez des problèmes, partagez le message d'erreur exact que vous voyez.
