# 📋 PHASE 1.2 - SIGNATURE BL ÉLECTRONIQUE

**Durée estimée:** 2-3 jours  
**Priorité:** Haute (correctif métier)  
**Dépendances:** Phase 1.1 complétée ✅

---

## 🎯 Objectif

Eliminer le besoin de signature papier sur les bons de livraison en implémentant:
- Signature électronique sur l'écran détail BL (modal Bootstrap)
- Enregistrement en base64 dans la BD
- Affichage de la signature dans l'impression PDF
- Statut "SIGNE" automatique

---

## 📊 Problème Résolu

### AVANT (Pain Point)
```
Magasinier remet BL papier → Client signe → Retour scan → Stockage
Problème:
  ❌ BL peut perdre signature
  ❌ Scan qualité faible
  ❌ Pas de chaîne de récusation
  ❌ Clients oublient signer
  ❌ Impossible imprimer avec signature
```

### APRÈS (Solution)
```
Magasinier ouvre détail BL → Clic "Obtenir signature" → Client signe sur écran → Enregistré
Avantages:
  ✅ Signature numérique haute définition
  ✅ Impossible de perdre
  ✅ Affichée immédiatement dans impression
  ✅ Horodatage automatique
  ✅ Audit trail complet
```

---

## 🏗️ Architecture

### Base de Données

**Nouvelle colonne dans `bons_livraison`:**
```sql
ALTER TABLE bons_livraison ADD COLUMN signature LONGBLOB DEFAULT NULL AFTER date_bl;
ALTER TABLE bons_livraison ADD COLUMN signature_date DATETIME DEFAULT NULL AFTER signature;
ALTER TABLE bons_livraison ADD COLUMN signature_client_nom VARCHAR(255) DEFAULT NULL AFTER signature_date;
```

**Champs:**
- `signature` - Base64 encoded PNG image (jusqu'à 1MB)
- `signature_date` - Timestamp de signature
- `signature_client_nom` - Nom du signataire (optionnel, saisi par client)

---

## 🔧 Composants à Créer

### 1. Frontend - Modal Signature
**Fichier:** `livraisons/modal_signature.php` (NEW)

```php
<!-- Modal Bootstrap pour capture signature -->
<div class="modal fade" id="modalSignature">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5>📝 Signature Client - BL #<?= $bl['numero'] ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Canvas pour dessin signature -->
                <canvas id="signaturePad" style="border: 2px solid #007bff; cursor: crosshair; ..."></canvas>
                
                <!-- Champ nom client -->
                <input type="text" id="signatureClientNom" placeholder="Nom du signataire" class="form-control mt-3">
                
                <!-- Boutons -->
                <div class="d-flex gap-2 mt-3">
                    <button type="button" id="btnClearSignature" class="btn btn-warning">
                        <i class="bi bi-arrow-counterclockwise"></i> Effacer
                    </button>
                    <button type="button" id="btnConfirmSignature" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Confirmer signature
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
```

**Librairie JavaScript:** SignaturePad.js (CDN)
```html
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.js"></script>
```

### 2. JavaScript - Capture Signature
**Fichier:** `assets/js/signature-handler.js` (NEW)

```javascript
// Initialiser SignaturePad
const canvas = document.getElementById('signaturePad');
const signaturePad = new SignaturePad(canvas);

// Bouton Effacer
document.getElementById('btnClearSignature').addEventListener('click', () => {
    signaturePad.clear();
});

// Bouton Confirmer
document.getElementById('btnConfirmSignature').addEventListener('click', async () => {
    if (signaturePad.isEmpty()) {
        alert('Veuillez signer avant de confirmer');
        return;
    }
    
    const clientNom = document.getElementById('signatureClientNom').value;
    const signatureData = signaturePad.toDataURL('image/png');
    
    // POST vers API
    const response = await fetch('<?= url_for("livraisons/api_signer_bl.php") ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            bl_id: <?= $id ?>,
            signature: signatureData,
            client_nom: clientNom
        })
    });
    
    if (response.ok) {
        alert('✅ Signature enregistrée!');
        location.reload();
    } else {
        alert('❌ Erreur: ' + await response.text());
    }
});
```

### 3. API Endpoint
**Fichier:** `livraisons/api_signer_bl.php` (NEW)

```php
<?php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');

header('Content-Type: application/json');
global $pdo;

$data = json_decode(file_get_contents('php://input'), true);

// Validation
if (!$data['bl_id'] || !$data['signature']) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètres manquants']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Sauvegarder signature en BD
    $stmt = $pdo->prepare("
        UPDATE bons_livraison 
        SET signature = ?, signature_date = NOW(), signature_client_nom = ?
        WHERE id = ?
    ");
    
    // Décoder et nettoyer le data:image URL
    $signatureBase64 = str_replace('data:image/png;base64,', '', $data['signature']);
    
    $stmt->execute([
        base64_decode($signatureBase64),
        $data['client_nom'],
        $data['bl_id']
    ]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
```

### 4. Intégration dans Detail BL
**Fichier:** `livraisons/detail.php` (MODIFIER)

```php
<!-- Ajouter bouton dans le header -->
<?php if ($bl['statut'] !== 'ANNULE' && !$bl['signature']): ?>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalSignature">
        <i class="bi bi-pen"></i> Obtenir signature
    </button>
<?php endif; ?>

<!-- Afficher signature si existe -->
<?php if ($bl['signature']): ?>
    <div class="card mt-3">
        <div class="card-header">
            <i class="bi bi-check-circle-fill"></i> Signature Client
        </div>
        <div class="card-body">
            <img src="data:image/png;base64,<?= base64_encode($bl['signature']) ?>" 
                 style="max-width: 300px; border: 1px solid #ddd; padding: 10px;">
            <p class="mt-2 text-muted">
                <small>Signé par: <?= htmlspecialchars($bl['signature_client_nom'] ?? 'N/A') ?><br>
                Date: <?= date('d/m/Y H:i', strtotime($bl['signature_date'])) ?></small>
            </p>
        </div>
    </div>
<?php endif; ?>
```

### 5. Impression PDF
**Fichier:** `livraisons/print.php` (MODIFIER)

Ajouter la signature en bas du PDF TCPDF:
```php
// En bas du document avant fermeture
if ($bl['signature']) {
    $pdf->Image('@' . $bl['signature'], 10, 250, 80);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->Text(10, 320, "Signature: " . $bl['signature_client_nom']);
}
$pdf->Output();
```

---

## 📋 Checklist Implémentation

- [ ] 1. Migrer BD (3 colonnes)
- [ ] 2. Inclure SignaturePad.js (CDN)
- [ ] 3. Créer modal_signature.php
- [ ] 4. Créer signature-handler.js
- [ ] 5. Créer api_signer_bl.php
- [ ] 6. Modifier livraisons/detail.php (bouton + affichage)
- [ ] 7. Modifier livraisons/print.php (signature dans PDF)
- [ ] 8. Tester signature → enregistrement → affichage
- [ ] 9. Tester impression avec signature
- [ ] 10. Générer rapport Phase 1.2

---

## 🧪 Plan de Test

### Test 1: Signature Basique
```
1. Ouvrir détail BL #X
2. Cliquer "Obtenir signature"
3. Dessiner signature sur canvas
4. Cliquer "Confirmer"
5. Vérifier: 
   ✓ Signature affichée en détail
   ✓ BD met à jour signature_date
   ✓ Bouton disparaît après signature
```

### Test 2: Impression
```
1. Signer BL
2. Cliquer "Imprimer"
3. Vérifier: Signature visible en bas PDF
4. Vérifier: Nom client + date en bas
```

### Test 3: Édition
```
1. Signer BL
2. Recharger page
3. Vérifier: Signature persiste
4. Cliquer bouton signature → Désactivé (déjà signé)
```

---

## 🔌 Technologies

| Composant | Tech | Raison |
|-----------|------|--------|
| Capture signature | SignaturePad.js | Lightweight, production-ready |
| Stockage | LONGBLOB base64 | Base64 image dans BD |
| API | JSON POST | Cohérent avec Phase 1.1 |
| Modal | Bootstrap 5 | Déjà utilisé (cohérence) |

---

## 📈 Impact Métier

**Magasinier:**
- ✅ Plus rapide (signature électronique vs papier)
- ✅ BL immédiatement complet avec signature
- ✅ Impression contient signature (plus de photocopie)

**Client:**
- ✅ Signe directement sur tablette/écran
- ✅ Plus simple (1 clic pour signer)

**Comptabilité:**
- ✅ Signature archivée numériquement
- ✅ Meilleur respect OHADA (traçabilité)

---

## ⏱️ Timeline

| Jour | Tâche | Status |
|------|-------|--------|
| 1 | BD migration + modal | ⏳ À faire |
| 1 | JavaScript handler | ⏳ À faire |
| 2 | API endpoint | ⏳ À faire |
| 2 | Intégration detail.php | ⏳ À faire |
| 3 | Print.php + tests | ⏳ À faire |

**Cible:** 17 Décembre 2025 (3 jours)

---

## 🎓 Notes Techniques

### Pourquoi Base64 et non fichier?
- ✅ Plus simple (pas de dossier /uploads)
- ✅ Sécurisé (pas d'accès direct fichier)
- ✅ Portable (export BD facile)
- ❌ Légèrement plus lent (rarement issue)

### Taille Max Signature
- Typique: 50-200 KB
- LONGBLOB: jusqu'à 4GB (plus que suffisant)

### Sécurité
- Signature stockée comme BLOB brut (pas d'interprétation)
- Pas de XSS (image base64, pas HTML)
- CSRF protection via security.php

---

**Prêt à commencer?** → Répondre "oui" pour débuter implémentation Phase 1.2
