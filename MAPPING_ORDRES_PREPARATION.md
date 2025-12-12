# MAPPING COLONNES ordres_preparation

## ❌ Colonnes Incorrectes → ✅ Colonnes Réelles

| Incorrect | Correct |
|-----------|---------|
| `date_demande` | `date_ordre` |
| `heure_demande` | *(n'existe pas, utiliser TIME(date_creation))* |
| `demandeur_id` | `commercial_responsable_id` |
| `preparateur_id` | `magasinier_id` |
| `type_demande` | `priorite` (ou `type_commande` selon contexte) |
| `statut_preparation` | `statut` |
| `instructions` | `observations` |

## Structure Réelle
```
id
numero_ordre
date_ordre                     ← au lieu de date_demande
vente_id
devis_id
client_id
type_commande                  ← ENUM('VENTE_SHOWROOM','VENTE_TERRAIN',...)
commercial_responsable_id      ← au lieu de demandeur_id
statut                         ← au lieu de statut_preparation
date_preparation_demandee
priorite                       ← au lieu de type_demande (ENUM: NORMALE, URGENTE, TRES_URGENTE)
observations                   ← au lieu de instructions
signature_resp_marketing
date_signature_marketing
magasinier_id                  ← au lieu de preparateur_id
date_preparation_effectuee
date_creation                  ← peut remplacer heure_demande
```
