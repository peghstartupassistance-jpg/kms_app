# 🧪 RAPPORT DE TESTS - Module Marketing KMS

**Date**: 11 décembre 2025  
**Module testé**: Module Marketing complet

---

## ✅ TESTS RÉUSSIS (21/31 = 68%)

### 1. **Syntaxe PHP** ✅
Tous les fichiers PHP sont syntaxiquement corrects :
- ✅ `digital/leads_list.php`
- ✅ `digital/leads_edit.php`
- ✅ `digital/leads_conversion.php`
- ✅ `coordination/ruptures.php`
- ✅ `coordination/litiges.php`
- ✅ `coordination/ordres_preparation.php`
- ✅ `coordination/ordres_preparation_edit.php`
- ✅ `coordination/ordres_preparation_statut.php`
- ✅ `reporting/dashboard_marketing.php`
- ✅ `reporting/relances_devis.php`
- ✅ `showroom/visiteur_convertir_devis.php`
- ✅ `marketing/README_MARKETING.md`

### 2. **Tables existantes** ✅
- ✅ `leads_digital` (structure correcte avec `nom_prospect`)
- ✅ `ordres_preparation`
- ✅ `retours_litiges`

### 3. **Canaux de vente** ✅
- ✅ SHOWROOM
- ✅ TERRAIN
- ✅ DIGITAL

### 4. **Requêtes SQL** ✅
- ✅ Dashboard SHOWROOM fonctionnel
- ✅ Ordres préparation accessibles
- ✅ Litiges accessibles

---

## ⚠️ ACTIONS REQUISES (10 tests échoués)

### 🔴 **CRITIQUE : Exécuter le script SQL complémentaire**

**Fichier**: `db/extensions_marketing_complement.sql`

**Tables manquantes** :
1. ❌ `ruptures_signalees` - Alertes ruptures stock
2. ❌ `relances_devis` - Système relances devis
3. ❌ `conversions_pipeline` - Suivi conversions
4. ❌ `objectifs_commerciaux` - Objectifs mensuels/annuels
5. ❌ `kpis_quotidiens` - KPIs quotidiens automatiques

**Vues manquantes** :
6. ❌ `v_pipeline_commercial` - Vue consolidée pipeline
7. ❌ `v_ventes_livraison_encaissement` - Vue ventes/encaissements

---

## 📋 PROCÉDURE D'INSTALLATION COMPLÈTE

### **Étape 1 : Exécuter le SQL complémentaire**

#### **Via phpMyAdmin** (recommandé) :
```
1. Ouvrir phpMyAdmin → http://localhost/phpmyadmin
2. Sélectionner la base "kms_gestion"
3. Onglet "Importer"
4. Choisir le fichier: db/extensions_marketing_complement.sql
5. Cliquer "Exécuter"
```

#### **Via ligne de commande** :
```powershell
cd C:\xampp\htdocs\kms_app
Get-Content db\extensions_marketing_complement.sql | C:\xampp\mysql\bin\mysql.exe -u root -p kms_gestion
```

---

### **Étape 2 : Vérifier l'installation**

Exécuter le script de test :
```powershell
C:\xampp\php\php.exe test_module_marketing.php
```

**Résultat attendu** : Tous les tests passent (31/31 = 100%)

---

### **Étape 3 : Tester les modules dans le navigateur**

#### **A. Module DIGITAL (Leads)**
```
URL: http://localhost/kms_app/digital/leads_list.php
```

**Test** :
1. Cliquer "Nouveau lead"
2. Remplir formulaire :
   - Nom prospect : "Test Lead"
   - Téléphone : "123456789"
   - Source : Facebook
   - Statut : NOUVEAU
3. Cliquer "Enregistrer"
4. Vérifier apparition dans liste

**Test conversion** :
1. Cliquer "Convertir" sur le lead
2. Créer nouveau client OU sélectionner existant
3. Cocher "Créer un devis"
4. Vérifier redirection vers devis

---

#### **B. Dashboard Marketing**
```
URL: http://localhost/kms_app/reporting/dashboard_marketing.php
```

**Test** :
1. Vérifier affichage KPIs tous canaux (Showroom, Terrain, Digital, Hôtel, Formation)
2. Tester filtres : Jour / Semaine / Mois
3. Vérifier graphique répartition CA
4. Vérifier absence d'erreurs PHP

---

#### **C. Coordination - Ordres de préparation**
```
URL: http://localhost/kms_app/coordination/ordres_preparation.php
```

**Test** :
1. Cliquer "Nouvelle demande"
2. Sélectionner une vente existante
3. Type demande : URGENTE
4. Date livraison souhaitée : demain
5. Instructions : "Emballer soigneusement"
6. Cliquer "Créer la demande"
7. Vérifier ordre créé avec numéro OP-20251211-0001

**Test changement statut** :
1. Cliquer flèche verte sur un ordre EN_ATTENTE
2. Vérifier passage à EN_PREPARATION
3. Cliquer à nouveau → PRET
4. Cliquer à nouveau → LIVRE

---

#### **D. Coordination - Ruptures signalées**
```
URL: http://localhost/kms_app/coordination/ruptures.php
```

**Prérequis** : Avoir au moins 1 produit dans la base

**Test** :
1. Cliquer "Signaler une rupture"
2. Sélectionner un produit
3. Impact commercial : "50 clients en attente"
4. Action proposée : "Réappro urgent 100 unités"
5. Date résolution : dans 7 jours
6. Statut : SIGNALE
7. Vérifier apparition dans liste

---

#### **E. Coordination - Litiges**
```
URL: http://localhost/kms_app/coordination/litiges.php
```

**Prérequis** : Avoir au moins 1 vente

**Test** :
1. Cliquer "Nouveau litige"
2. Sélectionner une vente
3. Type problème : PRODUIT_DEFECTUEUX
4. Description : "Écran cassé à réception"
5. Solution proposée : REMPLACEMENT
6. Montant remboursé : 0
7. Statut : SIGNALE
8. Vérifier apparition dans liste

---

#### **F. Système de relances**
```
URL: http://localhost/kms_app/reporting/relances_devis.php
```

**Prérequis** : Avoir au moins 1 devis en statut ENVOYE ou EN_COURS

**Test** :
1. Vérifier affichage devis à relancer
2. Vérifier alertes urgentes (≤ 3 jours validité) en rouge
3. Cliquer "Relancer" sur un devis
4. Type relance : TELEPHONE
5. Commentaires : "Client intéressé, rappeler vendredi"
6. Prochaine action : "Envoyer catalogue complet"
7. Date prochaine action : dans 2 jours
8. Cliquer "Enregistrer"
9. Vérifier compteur "Relancés cette semaine" augmente

---

#### **G. Conversion Showroom**
```
URL: http://localhost/kms_app/showroom/visiteurs_list.php
```

**Test** :
1. Enregistrer un visiteur (formulaire rapide en haut)
2. Vérifier apparition dans liste
3. Cliquer bouton "Devis" sur la ligne du visiteur
4. Onglet "Créer nouveau client" :
   - Nom : rempli automatiquement
   - Téléphone : rempli automatiquement
5. Cliquer "Créer le client et le devis"
6. Vérifier redirection vers édition devis
7. Vérifier client créé automatiquement

---

## 🔧 PROBLÈMES CONNUS & SOLUTIONS

### **Problème 1 : Table 'ruptures_signalees' doesn't exist**
**Solution** : Exécuter `db/extensions_marketing_complement.sql`

### **Problème 2 : Column 'nom' not found in 'leads_digital'**
**Solution** : Utiliser `nom_prospect` dans les requêtes (déjà corrigé dans les fichiers PHP)

### **Problème 3 : Permissions insuffisantes**
**Solution** : Vérifier que l'utilisateur a les permissions :
```sql
-- Vérifier permissions utilisateur
SELECT p.code 
FROM utilisateurs_permissions up
INNER JOIN permissions p ON up.permission_id = p.id
WHERE up.utilisateur_id = 1;

-- Ajouter permissions si manquantes
INSERT INTO utilisateurs_permissions (utilisateur_id, permission_id)
SELECT 1, id FROM permissions 
WHERE code IN ('CLIENTS_CREER', 'DEVIS_CREER', 'VENTES_LIRE', 'REPORTING_LIRE');
```

### **Problème 4 : Canaux de vente manquants**
**Solution** :
```sql
INSERT INTO canaux_vente (nom, code) VALUES 
('Showroom', 'SHOWROOM'),
('Vente terrain', 'TERRAIN'),
('Digital', 'DIGITAL')
ON DUPLICATE KEY UPDATE nom = VALUES(nom);
```

---

## 📊 RÉSULTATS ATTENDUS APRÈS CORRECTION

### **Avant correction** :
- ✅ Tests réussis : 21/31 (68%)
- ❌ Tests échoués : 10
- ⚠️ Tables manquantes : 5
- ⚠️ Vues manquantes : 2

### **Après correction** :
- ✅ Tests réussis : 31/31 (100%)
- ✅ Tables créées : 8
- ✅ Vues créées : 2
- ✅ Fichiers PHP : 12
- ✅ Documentation : README complet

---

## 🎯 CHECKLIST FINALE

### **Installation**
- [ ] Script SQL complémentaire exécuté
- [ ] Toutes les tables créées (vérifier avec `SHOW TABLES;`)
- [ ] Canaux de vente configurés
- [ ] Permissions utilisateurs vérifiées

### **Tests fonctionnels**
- [ ] Module DIGITAL : Créer lead + Convertir
- [ ] Dashboard : Affichage KPIs tous canaux
- [ ] Ordres préparation : Créer + Changer statut
- [ ] Ruptures : Signaler + Traiter
- [ ] Litiges : Créer + Résoudre
- [ ] Relances : Enregistrer relance
- [ ] Showroom : Convertir visiteur → devis

### **Navigation**
- [ ] Sidebar affiche "Digital (Leads)"
- [ ] Sidebar affiche "Ordres de préparation"
- [ ] Sidebar affiche "Dashboard Marketing"
- [ ] Sidebar affiche "Relances devis"

---

## 📞 SUPPORT

**En cas de problème** :
1. Vérifier logs Apache : `C:\xampp\apache\logs\error.log`
2. Vérifier logs PHP dans erreurs MySQL
3. Relancer XAMPP : Apache + MySQL
4. Consulter `marketing/README_MARKETING.md` pour documentation complète

---

**Rapport généré automatiquement le 11 décembre 2025**
