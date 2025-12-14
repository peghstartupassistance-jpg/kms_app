<?php
/**
 * Modal Signature BL - Template Bootstrap 5
 * À inclure dans livraisons/detail.php
 */
?>

<!-- Modal Signature Électronique BL -->
<div class="modal fade" id="modalSignatureBL" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <!-- Header -->
            <div class="modal-header bg-primary text-white">
                <div>
                    <h5 class="modal-title mb-1">
                        <i class="bi bi-pen-fill"></i> Signature Client
                    </h5>
                    <small>BL #<?= htmlspecialchars($bl['numero']) ?> – <?= htmlspecialchars($bl['client_nom']) ?></small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <!-- Body -->
            <div class="modal-body p-4">
                <!-- Instructions -->
                <div class="alert alert-info mb-4">
                    <i class="bi bi-info-circle"></i>
                    <strong>Veuillez signer dans la zone ci-dessous</strong><br>
                    <small>Utilisez votre souris, trackpad ou écran tactile pour signer</small>
                </div>

                <!-- Canvas Signature -->
                <div class="border-2 rounded mb-3" style="border: 2px solid #dee2e6; cursor: crosshair; background: white;">
                    <canvas 
                        id="signaturePadCanvas" 
                        style="display: block; width: 100%; height: 250px; touch-action: none; cursor: crosshair;">
                    </canvas>
                </div>

                <!-- Champ Nom du Signataire -->
                <div class="mb-3">
                    <label for="signatureClientNom" class="form-label">
                        <i class="bi bi-person"></i> Nom du signataire
                    </label>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="signatureClientNom" 
                        placeholder="Ex: Jean Dupont"
                        autocomplete="off">
                </div>

                <!-- Messages de statut -->
                <div id="signatureStatus" class="mb-3" style="display: none;">
                    <div id="signatureSuccessMsg" class="alert alert-success" style="display: none;">
                        <i class="bi bi-check-circle-fill"></i> Signature enregistrée avec succès!
                    </div>
                    <div id="signatureErrorMsg" class="alert alert-danger" style="display: none;">
                        <i class="bi bi-exclamation-triangle-fill"></i> 
                        <span id="signatureErrorText"></span>
                    </div>
                    <div id="signatureLoadingMsg" class="alert alert-info" style="display: none;">
                        <i class="bi bi-hourglass-split"></i> Enregistrement en cours...
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer bg-light">
                <button 
                    type="button" 
                    id="btnClearSignature" 
                    class="btn btn-warning"
                    title="Effacer la signature">
                    <i class="bi bi-arrow-counterclockwise"></i> Effacer
                </button>
                <button 
                    type="button" 
                    class="btn btn-secondary" 
                    data-bs-dismiss="modal">
                    Annuler
                </button>
                <button 
                    type="button" 
                    id="btnConfirmSignature" 
                    class="btn btn-success"
                    title="Confirmer et enregistrer la signature">
                    <i class="bi bi-check-circle-fill"></i> Confirmer signature
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts SignaturePad -->
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.js"></script>
<script>
// Configuration globale pour la signature
const signatureConfig = {
    blId: <?= (int)$bl['id'] ?>,
    apiUrl: '<?= url_for('livraisons/api_signer_bl.php') ?>',
    detailUrl: '<?= url_for('livraisons/detail.php?id=' . (int)$bl['id']) ?>',
    csrfToken: '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>'
};
</script>
