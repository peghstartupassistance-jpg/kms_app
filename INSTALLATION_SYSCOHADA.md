# 🚀 INSTALLATION RAPIDE - MODULE COMPTABILITÉ SYSCOHADA

## ⚡ 4 ÉTAPES SIMPLES

### ÉTAPE 0 : Préparer le Schéma ⚠️ IMPORTANT

**Avant d'importer le plan comptable, mettez à jour le schéma :**

**Via phpMyAdmin** (Recommandé)
1. Ouvrir http://localhost/phpmyadmin
2. Sélectionner la base **kms_gestion**
3. Cliquer sur l'onglet **SQL**
4. Coller le contenu du fichier `db/update_compta_schema_syscohada.sql`
5. Cliquer sur **Exécuter**

✅ Le message "Schéma mis à jour avec succès" doit apparaître

---

### ÉTAPE 1 : Importer le Plan Comptable

**Option A - Via phpMyAdmin** (Recommandé)
1. Rester dans phpMyAdmin / base **kms_gestion**
2. Cliquer sur l'onglet **SQL**
3. Coller le contenu du fichier `db/import_plan_syscohada.sql`
4. Cliquer sur **Exécuter**

**Option B - En ligne de commande**
```bash
cd C:\xampp\htdocs\kms_app
mysql -u root -p kms_gestion < db/update_compta_schema_syscohada.sql
mysql -u root -p kms_gestion < db/import_plan_syscohada.sql
```

✅ **Vérification** : ~100 comptes doivent être importés

---

### ÉTAPE 2 : Accéder à la Saisie Sage

1. Se connecter à KMS Gestion avec un compte **ADMIN**
2. Menu **Comptabilité** → **Saisie (mode Sage)**
3. L'interface de saisie s'affiche

---

### ÉTAPE 3 : Vérifier les Permissions & Exercice

**A. Exécuter le script de diagnostic**
1. Ouvrir http://localhost/kms_app/fix_compta_columns.php
2. Vérifier que tout est ✅ vert
3. Si un exercice manque, il sera créé automatiquement

**B. Créer un exercice (si nécessaire)**
- Menu **Comptabilité** → **Exercices**
- Cliquer **Nouveau**
- Année : 2025, Date ouverture : 2025-01-01

---

### ÉTAPE 4 : Test Rapide

**Saisir une vente simple :**

| Champ | Valeur |
|-------|--------|
| Journal | VE - Journal des ventes |
| Date | Date du jour |
| Libellé | Vente test |

**Lignes :**
- Ligne 1 : Compte **411 - Clients** | Débit : **100 000** | Crédit : **0**
- Ligne 2 : Compte **707 - Ventes de marchandises** | Débit : **0** | Crédit : **100 000**

Cliquer sur **Enregistrer et valider**

✅ Si succès → Module opérationnel !

---

## 📊 COMPTES ESSENTIELS À CONNAÎTRE

### Ventes
- **411** : Clients (débit quand vous vendez)
- **707** : Ventes de marchandises (crédit)
- **443** : TVA facturée (crédit)

### Achats
- **401** : Fournisseurs (crédit quand vous achetez)
- **607** : Achats de marchandises (débit)
- **445** : TVA récupérable (débit)

### Trésorerie
- **571** : Caisse (débit quand encaissement)
- **521** : Banque (débit quand virement reçu)

---

## ⚠️ RÈGLES D'OR

1. **Débit = Crédit** (toujours équilibré)
2. **Client doit = compte 411 AU DÉBIT**
3. **Fournisseur doit = compte 401 AU CRÉDIT**
4. **Encaissement = DÉBIT caisse/banque**
5. **Décaissement = CRÉDIT caisse/banque**

---

## 📚 DOCUMENTATION COMPLÈTE

Voir le fichier `GUIDE_COMPTABILITE_SYSCOHADA.md` pour :
- Tous les exemples de saisie
- Structure complète SYSCOHADA
- Workflow de clôture
- Bonnes pratiques

---

## 🆘 DÉPANNAGE

**Problème** : "Aucun exercice actif"
→ Aller dans Comptabilité → Exercices → Créer exercice 2025 et l'activer

**Problème** : "Comptes non trouvés"
→ Réexécuter le script SQL `db/import_plan_syscohada.sql`

**Problème** : "Pièce non équilibrée"
→ Vérifier que Total Débit = Total Crédit exactement

---

✅ **Module prêt à l'emploi en 5 minutes !**
