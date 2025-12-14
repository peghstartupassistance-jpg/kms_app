/**
 * Signature Handler - Phase 1.2
 * Gère la capture et l'enregistrement des signatures BL
 */

let signaturePad;
const canvas = document.getElementById('signaturePadCanvas');

/**
 * Initialiser SignaturePad au chargement du modal
 */
function initializeSignaturePad() {
    if (signaturePad) return; // Déjà initialisé
    
    // Dimensionner le canvas
    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width;
    canvas.height = rect.height;
    
    // Créer instance SignaturePad
    signaturePad = new SignaturePad(canvas, {
        minWidth: 2,
        maxWidth: 3,
        throttle: 16,
        minDistance: 5,
        penColor: '#000000',
        backgroundColor: 'rgba(255,255,255,0)',
        onBegin: function() {
            console.log('✏️ Signature commencée');
        },
        onEnd: function() {
            console.log('✏️ Signature en cours...');
        }
    });
    
    console.log('✅ SignaturePad initialisé');
}

/**
 * Modal ouvert - initialiser le canvas
 */
document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('modalSignatureBL');
    
    if (modalEl) {
        modalEl.addEventListener('show.bs.modal', function() {
            console.log('🔓 Modal signature ouvert');
            setTimeout(() => {
                initializeSignaturePad();
            }, 100);
        });
    }
});

/**
 * Bouton Effacer Signature
 */
document.getElementById('btnClearSignature').addEventListener('click', function() {
    if (signaturePad) {
        signaturePad.clear();
        console.log('🗑️ Signature effacée');
    }
});

/**
 * Bouton Confirmer Signature
 */
document.getElementById('btnConfirmSignature').addEventListener('click', async function() {
    // Vérifications
    if (!signaturePad) {
        showSignatureError('SignaturePad non initialisé');
        return;
    }
    
    if (signaturePad.isEmpty()) {
        showSignatureError('Veuillez signer avant de confirmer');
        return;
    }
    
    const clientNom = document.getElementById('signatureClientNom').value.trim();
    if (!clientNom) {
        showSignatureError('Veuillez entrer le nom du signataire');
        return;
    }
    
    // Optionnel: on peut garder l'image côté client mais API n'enregistre pas d'image
    // const signatureData = signaturePad.toDataURL('image/png');
    console.log('📤 Envoi confirmation de signature à l\'API...');
    showSignatureLoading();
    
    try {
        const response = await fetch(signatureConfig.apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': signatureConfig.csrfToken || ''
            },
            body: JSON.stringify({
                bl_id: signatureConfig.blId,
                client_nom: clientNom,
                note: ''
            })
        });
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            console.log('✅ Signature confirmée!');
            showSignatureSuccess();
            
            // Redirection après 1.5 secondes
            setTimeout(() => {
                window.location.href = signatureConfig.detailUrl;
            }, 1500);
        } else {
            const errorMsg = result.error || 'Erreur inconnue';
            console.error('❌ Erreur API:', errorMsg);
            showSignatureError(errorMsg);
        }
    } catch (error) {
        console.error('❌ Erreur réseau:', error.message);
        showSignatureError('Erreur de connexion: ' + error.message);
    }
});

/**
 * Afficher message d'erreur
 */
function showSignatureError(message) {
    const statusDiv = document.getElementById('signatureStatus');
    const errorDiv = document.getElementById('signatureErrorMsg');
    const errorText = document.getElementById('signatureErrorText');
    const loadingDiv = document.getElementById('signatureLoadingMsg');
    const successDiv = document.getElementById('signatureSuccessMsg');
    
    errorText.textContent = message;
    
    statusDiv.style.display = 'block';
    errorDiv.style.display = 'block';
    loadingDiv.style.display = 'none';
    successDiv.style.display = 'none';
    
    console.error('⚠️ Erreur signature:', message);
}

/**
 * Afficher message de loading
 */
function showSignatureLoading() {
    const statusDiv = document.getElementById('signatureStatus');
    const loadingDiv = document.getElementById('signatureLoadingMsg');
    const errorDiv = document.getElementById('signatureErrorMsg');
    const successDiv = document.getElementById('signatureSuccessMsg');
    
    statusDiv.style.display = 'block';
    loadingDiv.style.display = 'block';
    errorDiv.style.display = 'none';
    successDiv.style.display = 'none';
}

/**
 * Afficher message de succès
 */
function showSignatureSuccess() {
    const statusDiv = document.getElementById('signatureStatus');
    const successDiv = document.getElementById('signatureSuccessMsg');
    const errorDiv = document.getElementById('signatureErrorMsg');
    const loadingDiv = document.getElementById('signatureLoadingMsg');
    
    statusDiv.style.display = 'block';
    successDiv.style.display = 'block';
    loadingDiv.style.display = 'none';
    errorDiv.style.display = 'none';
    
    console.log('✅ Signature confirmée avec succès!');
}

console.log('✅ Signature handler chargé');
