# 📊 Module Marketing - KMS Gestion

## 🎯 Vue d'ensemble

Le **module Marketing** de KMS Gestion implémente l'organisation commerciale complète de **Kenne Multi-Services**, basée sur le document "Organisation du service Marketing".

**Objectif** : Centraliser et automatiser la gestion des 5 canaux commerciaux (Showroom, Terrain, Digital, Hôtel, Formation) avec un système complet de suivi, relances, et coordination avec le magasin.

---

## 📋 Structure du Module

### **1. Canaux Commerciaux**

#### 🏪 **SHOWROOM**
**Fichiers** :
- `showroom/visiteurs_list.php` → Liste visiteurs avec saisie rapide
- `showroom/visiteur_convertir_devis.php` → Conversion rapide visiteur → devis (1 clic)

**Fonctionnalités** :
- ✅ Enregistrement rapide des visiteurs
- ✅ Conversion directe en devis avec création client automatique
- ✅ Suivi des conversions (visiteur → devis → vente)
- ✅ Statistiques temps réel dans dashboard marketing

**Flux typique** :
```
Visiteur entre → Saisie rapide fiche → [Convertir en devis] → Création client auto → Devis généré → Ajout lignes produits → Envoi client
```

---

#### 🗺️ **TERRAIN**
**Fichiers** :
- `terrain/prospections_list.php` → Liste prospections
- `terrain/rendezvous_list.php` → Gestion rendez-vous terrain

**Fonctionnalités** :
- ✅ Prospection terrain avec géolocalisation
- ✅ Planification rendez-vous (statut PLANIFIE/HONORE/ANNULE/REPORTE)
- ✅ Suivi secteurs et tournées
- ✅ Conversion prospection → devis → vente
- ✅ Scoring prospects (à venir)

**Tables associées** :
- `prospections_terrain`
- `rendezvous_terrain`

---

#### 📱 **DIGITAL (Leads)**
**Fichiers** :
- `digital/leads_list.php` → Liste leads avec filtres sources/statut
- `digital/leads_edit.php` → Formulaire création/édition lead
- `digital/leads_conversion.php` → Conversion lead → client/prospect + devis

**Fonctionnalités** :
- ✅ Multi-sources : Facebook, Instagram, WhatsApp, TikTok, Site Web, Google Ads, Email
- ✅ Pipeline complet : NOUVEAU → CONTACTE → QUALIFIE → DEVIS_ENVOYE → CONVERTI → PERDU
- ✅ Scoring prospect (0-100)
- ✅ Suivi campagnes (nom campagne, coût acquisition)
- ✅ Prochaine action planifiée
- ✅ Conversion automatique en client + génération devis

**Statuts Pipeline** :
```
NOUVEAU          → Lead entrant, pas encore contacté
CONTACTE         → Premier contact établi
QUALIFIE         → Besoin identifié, budget confirmé
DEVIS_ENVOYE     → Devis envoyé au prospect
CONVERTI         → Devenu client (vente réalisée)
PERDU            → Abandon définitif
```

**Tables** :
- `leads_digital` → Leads entrants
- `conversions_pipeline` → Historique conversions

---

#### 🏨 **HÔTEL & RÉSIDENCES**
**Fichiers** :
- `hotel/reservations.php` → Gestion réservations
- `hotel/chambres_list.php` → Gestion chambres
- `hotel/visiteurs_list.php` → Visiteurs non-résidents
- `hotel/upsell_list.php` → Services additionnels (petit-déj, activités...)

**Fonctionnalités** :
- ✅ Réservations avec calcul automatique nuits
- ✅ Statuts (EN_ATTENTE/CONFIRMEE/CHECK_IN/CHECK_OUT/ANNULEE)
- ✅ Upsell (services additionnels facturés)
- ✅ Visiteurs non-résidents (visites, événements)
- ✅ CA chambres + CA upsell dans dashboard

**Tables** :
- `chambres`
- `reservations_hotel`
- `upsell_hotel`
- `visiteurs_hotel`

---

#### 🎓 **FORMATION (IFP-KMS)**
**Fichiers** :
- `formation/formations_list.php` → Catalogue formations
- `formation/prospects_list.php` → Prospects formation
- `formation/inscriptions.php` → Gestion inscriptions

**Fonctionnalités** :
- ✅ Catalogue formations avec tarifs
- ✅ Gestion prospects (source, intérêt)
- ✅ Inscriptions avec montant payé/solde dû
- ✅ Suivi paiements fractionnés

**Tables** :
- `formations`
- `prospects_formation`
- `inscriptions_formation`

---

### **2. Coordination Marketing ↔ Magasin**

#### 📦 **Ordres de Préparation**
**Fichiers** :
- `coordination/ordres_preparation.php` → Liste ordres
- `coordination/ordres_preparation_edit.php` → Formulaire demande
- `coordination/ordres_preparation_statut.php` → Changement statut

**Flux** :
```
Commercial crée vente → Demande préparation → EN_ATTENTE
Magasinier prend en charge → EN_PREPARATION
Articles préparés → PRET
Livraison/enlèvement → LIVRE
```

**Types de demande** :
- **NORMALE** : Préparation standard
- **URGENTE** : Priorité haute
- **LIVRAISON** : Avec livraison client
- **ENLEVER** : Client vient chercher

**Table** : `ordres_preparation`

---

#### ⚠️ **Ruptures Signalées**
**Fichiers** :
- `coordination/ruptures.php` → Alertes ruptures stock

**Objectif** : Magasin signale ruptures critiques → Marketing adapte discours/propose alternatives

**Workflow** :
```
Magasin : [Signaler rupture] → SIGNALE
Marketing : [Prendre en charge] → EN_COURS
Solution trouvée (réappro, produit alternatif) → RESOLU
```

**Champs clés** :
- `impact_commercial` : CA potentiel perdu
- `action_proposee` : Alternative suggérée
- `date_resolution_prevue`

**Table** : `ruptures_signalees`

---

#### 🔄 **Retours & Litiges**
**Fichiers** :
- `coordination/litiges.php` → Gestion litiges clients

**Types de problème** :
- PRODUIT_DEFECTUEUX
- ERREUR_LIVRAISON
- PRODUIT_DIFFERENT
- INSATISFACTION_QUALITE
- DELAI_NON_RESPECTE
- AUTRE

**Solutions** :
- REMBOURSEMENT
- REMPLACEMENT
- AVOIR_MAGASIN
- GESTE_COMMERCIAL
- AUCUNE

**Workflow** :
```
Client signale problème → SIGNALE
SAV traite → EN_COURS
Solution appliquée → RESOLU
Client satisfait ? → satisfaction_finale (1-5)
```

**Table** : `retours_litiges`

---

### **3. Dashboard & Reporting**

#### 📊 **Dashboard Marketing**
**Fichier** : `reporting/dashboard_marketing.php`

**Périodes** : Jour / Semaine / Mois

**KPIs par canal** :

**SHOWROOM** :
- Nb visiteurs
- Nb devis / ventes
- CA TTC
- Taux conversion visiteurs → ventes

**TERRAIN** :
- Nb prospections
- Nb rendez-vous (planifiés/honorés)
- Nb devis / ventes
- CA TTC
- Taux conversion

**DIGITAL** :
- Nb leads
- Répartition statuts (Nouveaux/Qualifiés/Convertis)
- Nb devis / ventes
- CA TTC
- Coût total acquisition

**HÔTEL** :
- Nb réservations
- Total nuits vendues
- Nb visiteurs non-résidents
- CA chambres + CA upsell

**FORMATION** :
- Nb prospects
- Nb inscriptions
- CA encaissé
- Solde dû

**KPIs Globaux** :
- CA global tous canaux
- Satisfaction moyenne (1-5)
- Litiges en cours
- Ruptures actives

**Répartition CA** : Graphique répartition par canal

---

#### 🔔 **Relances Devis**
**Fichier** : `reporting/relances_devis.php`

**Objectif** : Suivi proactif des devis envoyés pour maximiser conversions

**Fonctionnalités** :
- ✅ Liste devis en attente (ENVOYE, EN_COURS)
- ✅ Alertes urgentes (≤ 3 jours validité)
- ✅ Historique relances par devis
- ✅ Enregistrement relances (Téléphone/Email/SMS/WhatsApp/Visite)
- ✅ Prochaine action planifiée

**Statistiques** :
- Total devis en attente
- Devis urgents (≤ 3 jours)
- Devis sans relance
- Devis relancés cette semaine

**Table** : `relances_devis`

---

## 📂 Structure Base de Données

### **Nouvelles tables créées** (via `db/extensions_marketing.sql`)

#### **1. leads_digital**
```sql
- id, date_lead, source, statut_pipeline
- nom, prenom, telephone, email
- produit_interet, besoin_detaille
- score_prospect (0-100)
- campagne, cout_acquisition
- prochaine_action, date_prochaine_action
- converti_en_client_id, date_conversion
```

#### **2. ordres_preparation**
```sql
- id, vente_id, numero_ordre
- date_demande, heure_demande
- demandeur_id, preparateur_id
- type_demande (NORMALE/URGENTE/LIVRAISON/ENLEVER)
- statut_preparation (EN_ATTENTE/EN_PREPARATION/PRET/LIVRE)
- date_pret, heure_pret
- date_livraison_souhaitee, date_livraison_effective
- instructions, adresse_livraison
```

#### **3. ruptures_signalees**
```sql
- id, produit_id, date_signalement
- signale_par_id (utilisateur)
- statut_traitement (SIGNALE/EN_COURS/RESOLU)
- impact_commercial (CA potentiel perdu)
- action_proposee, date_resolution_prevue, date_resolution
```

#### **4. retours_litiges**
```sql
- id, vente_id, client_id
- date_retour, type_probleme
- description, statut_traitement
- solution_proposee, solution_appliquee
- montant_rembourse, montant_avoir
- satisfaction_finale (1-5)
- date_resolution
```

#### **5. relances_devis**
```sql
- id, devis_id, date_relance
- type_relance (TELEPHONE/EMAIL/SMS/WHATSAPP/VISITE)
- utilisateur_id, commentaires
- prochaine_action, date_prochaine_action
```

#### **6. conversions_pipeline**
```sql
- id, source_type (SHOWROOM/TERRAIN/DIGITAL)
- source_id, client_id
- date_conversion, canal_vente_id
- devis_id, vente_id
```

#### **7. objectifs_commerciaux**
```sql
- id, annee, mois, canal
- objectif_ca, objectif_nb_ventes
```

#### **8. kpis_quotidiens**
```sql
- id, date, canal
- nb_visiteurs, nb_leads, nb_devis, nb_ventes
- ca_realise
```

---

### **Vues créées**

#### **v_pipeline_commercial**
Vue consolidée du pipeline commercial tous canaux confondus.

#### **v_ventes_livraison_encaissement**
Vue ventes avec statut livraison et encaissement pour rapports consolidés.

---

## 🔧 Installation & Déploiement

### **Étape 1 : Exécuter le script SQL**

Via **phpMyAdmin** :
1. Se connecter à phpMyAdmin
2. Sélectionner la base `kms_gestion`
3. Onglet **Importer**
4. Charger le fichier `db/extensions_marketing.sql`
5. Cliquer **Exécuter**

### **Étape 2 : Vérifier les permissions**

S'assurer que les utilisateurs ont les permissions nécessaires :
```sql
-- Showroom/Terrain/Digital : Besoin CLIENTS_CREER, DEVIS_CREER
-- Magasiniers : Besoin VENTES_LIRE, VENTES_MODIFIER
-- Direction : REPORTING_LIRE
```

### **Étape 3 : Vérifier les canaux de vente**

La table `canaux_vente` doit contenir :
```sql
INSERT INTO canaux_vente (nom, code) VALUES
('Showroom', 'SHOWROOM'),
('Vente terrain', 'TERRAIN'),
('Digital', 'DIGITAL');
```

### **Étape 4 : Test des modules**

#### **Test DIGITAL** :
1. Aller dans **Digital (Leads)**
2. Créer un lead test (source Facebook, statut NOUVEAU)
3. Passer au statut QUALIFIE
4. Convertir en client + créer devis
5. Vérifier dans `conversions_pipeline`

#### **Test SHOWROOM** :
1. Aller dans **Showroom**
2. Enregistrer un visiteur
3. Cliquer **[Convertir en devis]**
4. Vérifier création client + devis

#### **Test Coordination** :
1. Créer une vente
2. Aller dans **Ordres de préparation**
3. Créer demande (type URGENTE)
4. Passer statut EN_PREPARATION → PRET → LIVRE

#### **Test Relances** :
1. Créer un devis (statut ENVOYE)
2. Aller dans **Relances devis**
3. Enregistrer une relance téléphone
4. Vérifier historique

#### **Test Dashboard** :
1. Aller dans **Dashboard Marketing**
2. Vérifier affichage KPIs tous canaux
3. Tester filtres Jour/Semaine/Mois

---

## 📝 Workflows Métiers Clés

### **1. Conversion Lead Digital → Vente**

```
1. Lead arrive (Facebook Ads) → digital/leads_list.php
2. Commercial qualifie → Statut QUALIFIE, score 75/100
3. [Convertir] → digital/leads_conversion.php
4. Création client automatique (type DIGITAL)
5. Création devis (canal DIGITAL)
6. Ajout lignes produits
7. Envoi devis → Statut DEVIS_ENVOYE
8. Relances automatiques → reporting/relances_devis.php
9. Devis accepté → Conversion en vente
10. Entrée dans conversions_pipeline
11. Dashboard mis à jour temps réel
```

---

### **2. Gestion Rupture Stock**

```
1. Magasinier constate rupture → coordination/ruptures.php
2. [Signaler rupture] → SIGNALE
3. Marketing alerté dans dashboard (Ruptures actives)
4. Marketing ouvre fiche rupture
5. Propose produit alternatif → action_proposee
6. Définit date réappro → date_resolution_prevue
7. Passe statut EN_COURS
8. Réappro effectué → RESOLU
9. Rupture disparaît des alertes actives
```

---

### **3. Suivi Devis avec Relances**

```
1. Devis créé + envoyé client → Statut ENVOYE
2. Apparaît dans reporting/relances_devis.php
3. Alerte si validité ≤ 3 jours (ligne rouge)
4. Commercial clique [Relancer]
5. Enregistre relance (type TELEPHONE)
6. Ajoute commentaire : "Client intéressé, hésite couleur"
7. Définit prochaine action : "Rappeler vendredi"
8. Date prochaine action : 2025-01-10
9. Dashboard affiche "Relancés cette semaine" : +1
10. Si converti → Statut ACCEPTE → Création vente
```

---

## 🔐 Permissions Requises

| Module | Permission minimale |
|--------|---------------------|
| **DIGITAL** | `CLIENTS_CREER`, `DEVIS_CREER` |
| **SHOWROOM** | `CLIENTS_CREER`, `DEVIS_CREER` |
| **TERRAIN** | `CLIENTS_CREER`, `DEVIS_CREER` |
| **Coordination (ordres)** | `VENTES_LIRE`, `VENTES_MODIFIER` |
| **Coordination (ruptures)** | `PRODUITS_LIRE` |
| **Coordination (litiges)** | `VENTES_LIRE`, `VENTES_MODIFIER` |
| **Dashboard Marketing** | `REPORTING_LIRE` |
| **Relances devis** | `DEVIS_LIRE` |

---

## 📈 Indicateurs de Performance (KPIs)

### **KPIs Quotidiens** :
- ✅ Nb visiteurs showroom
- ✅ Nb prospections terrain
- ✅ Nb leads digitaux (nouveaux)
- ✅ Nb devis envoyés
- ✅ Nb ventes réalisées
- ✅ CA journalier (tous canaux)
- ✅ Taux d'occupation hôtel
- ✅ Nb inscriptions formation

### **KPIs Hebdomadaires** :
- ✅ Taux conversion showroom (visiteurs → ventes)
- ✅ Taux conversion terrain (prospections → ventes)
- ✅ Taux conversion digital (leads → clients)
- ✅ Répartition CA par canal
- ✅ Nb relances effectuées
- ✅ Litiges résolus vs en cours

### **KPIs Mensuels** :
- ✅ CA global
- ✅ Marge brute
- ✅ Répartition CA par famille produits
- ✅ Satisfaction client moyenne
- ✅ Coût acquisition lead (Digital)
- ✅ ROI campagnes publicitaires

---

## 🚀 Améliorations Futures

### **Phase 2 (à développer)** :
- [ ] Scoring automatique prospects (algorithme ML)
- [ ] Cartographie terrain (géolocalisation sur carte)
- [ ] Notifications push relances (email/SMS automatiques)
- [ ] Tableau de bord Direction (objectifs vs réalisé)
- [ ] Export Excel rapports marketing
- [ ] Intégration CRM (synchronisation contacts)
- [ ] Module satisfaction enrichi (enquêtes NPS)
- [ ] Prévisions ventes (IA prédictive)

### **Phase 3 (évolutions)** :
- [ ] Application mobile commerciaux terrain
- [ ] Chatbot prospects (réponses automatiques)
- [ ] Intégration WhatsApp Business API
- [ ] Signature électronique devis
- [ ] Catalogue produits en ligne (e-commerce)

---

## 📞 Support & Documentation

**Fichiers de référence** :
- `historique.md` → Historique complet projet
- `compta/README_COMPTA.md` → Documentation comptabilité
- `INDEX_COMPTA.md` → Index fichiers comptabilité
- `.github/copilot-instructions.md` → Instructions Copilot

**Contact Technique** :
Toute question sur ce module → Voir `historique.md` pour contexte complet.

---

**Version** : 1.0  
**Date** : Janvier 2025  
**Auteur** : GitHub Copilot + Équipe KMS  
**Licence** : Usage interne KMS uniquement
