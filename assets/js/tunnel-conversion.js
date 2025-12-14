/**
 * tunnel-conversion.js - Gestion du tunnel de conversion dynamique
 * Permet de changer rapidement les statuts des prospects, clients, devis, prospections
 */

(function() {
    'use strict';

    // Configuration des statuts par entité
    const STATUTS_CONFIG = {
        client: {
            libelle: 'Client',
            statuts: [
                { value: 'PROSPECT', label: 'Prospect', color: 'warning', icon: 'bi-person-plus' },
                { value: 'CLIENT', label: 'Client', color: 'success', icon: 'bi-person-check' },
                { value: 'APPRENANT', label: 'Apprenant', color: 'info', icon: 'bi-mortarboard' },
                { value: 'HOTE', label: 'Hôte', color: 'primary', icon: 'bi-house-door' }
            ]
        },
        devis: {
            libelle: 'Devis',
            statuts: [
                { value: 'EN_ATTENTE', label: 'En attente', color: 'secondary', icon: 'bi-clock' },
                { value: 'ACCEPTE', label: 'Accepté', color: 'success', icon: 'bi-check-circle' },
                { value: 'REFUSE', label: 'Refusé', color: 'danger', icon: 'bi-x-circle' },
                { value: 'ANNULE', label: 'Annulé', color: 'dark', icon: 'bi-slash-circle' }
            ]
        },
        prospection: {
            libelle: 'Prospection',
            statuts: [
                { value: 'Intéressé - à recontacter', label: 'Intéressé', color: 'warning', icon: 'bi-star' },
                { value: 'Devis demandé', label: 'Devis demandé', color: 'info', icon: 'bi-file-earmark-text' },
                { value: 'À rappeler plus tard', label: 'À rappeler', color: 'secondary', icon: 'bi-telephone' },
                { value: 'Non intéressé', label: 'Non intéressé', color: 'muted', icon: 'bi-dash-circle' },
                { value: 'Converti en client', label: 'Converti', color: 'success', icon: 'bi-check2-all' },
                { value: 'Perdu', label: 'Perdu', color: 'danger', icon: 'bi-x-lg' }
            ]
        },
        prospect_formation: {
            libelle: 'Prospect Formation',
            statuts: [
                { value: 'Nouveau contact', label: 'Nouveau', color: 'primary', icon: 'bi-person-plus' },
                { value: 'En cours', label: 'En cours', color: 'warning', icon: 'bi-hourglass-split' },
                { value: 'Devis envoyé', label: 'Devis envoyé', color: 'info', icon: 'bi-envelope-check' },
                { value: 'Inscrit', label: 'Inscrit', color: 'success', icon: 'bi-check-circle-fill' },
                { value: 'Perdu', label: 'Perdu', color: 'danger', icon: 'bi-x-circle' },
                { value: 'Reporté', label: 'Reporté', color: 'secondary', icon: 'bi-calendar-x' }
            ]
        }
    };

    /**
     * Initialise un dropdown de changement de statut
     */
    function initStatutDropdown(element) {
        const entite = element.dataset.entite;
        const id = element.dataset.id;
        const statutActuel = element.dataset.statut;
        const config = STATUTS_CONFIG[entite];

        if (!config) {
            console.error('Type d\'entité non configuré:', entite);
            return;
        }

        // Créer le dropdown
        const dropdown = document.createElement('div');
        dropdown.className = 'dropdown d-inline-block';
        dropdown.innerHTML = `
            <button class="btn btn-sm btn-outline-${getCurrentColor(config, statutActuel)} dropdown-toggle statut-btn" 
                    type="button" 
                    data-bs-toggle="dropdown"
                    title="Changer le statut">
                <i class="${getCurrentIcon(config, statutActuel)} me-1"></i>
                ${getCurrentLabel(config, statutActuel)}
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                ${config.statuts.map(s => `
                    <li>
                        <a class="dropdown-item ${s.value === statutActuel ? 'active' : ''}" 
                           href="#"
                           data-statut="${s.value}">
                            <i class="${s.icon} me-2 text-${s.color}"></i>
                            ${s.label}
                        </a>
                    </li>
                `).join('')}
            </ul>
        `;

        // Remplacer l'élément original
        element.replaceWith(dropdown);

        // Ajouter les événements
        dropdown.querySelectorAll('.dropdown-item').forEach(item => {
            item.addEventListener('click', async function(e) {
                e.preventDefault();
                if (this.classList.contains('active')) return;

                const nouveauStatut = this.dataset.statut;
                await changerStatut(entite, id, nouveauStatut, dropdown.querySelector('.statut-btn'));
            });
        });
    }

    /**
     * Change le statut via AJAX
     */
    async function changerStatut(entite, id, nouveauStatut, bouton) {
        const originalHTML = bouton.innerHTML;
        bouton.disabled = true;
        bouton.innerHTML = '<i class="bi bi-hourglass-split"></i> Modification...';

        try {
            const response = await fetch('/kms_app/ajax/changer_statut.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({ entite, id, nouveau_statut: nouveauStatut })
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Erreur inconnue');
            }

            // Mettre à jour le bouton
            const config = STATUTS_CONFIG[entite];
            const statutInfo = config.statuts.find(s => s.value === nouveauStatut);
            
            bouton.className = `btn btn-sm btn-outline-${statutInfo.color} dropdown-toggle statut-btn`;
            bouton.innerHTML = `<i class="${statutInfo.icon} me-1"></i>${statutInfo.label}`;

            // Mettre à jour tous les items actifs
            const dropdown = bouton.closest('.dropdown');
            dropdown.querySelectorAll('.dropdown-item').forEach(item => {
                item.classList.toggle('active', item.dataset.statut === nouveauStatut);
            });

            // Afficher un toast de succès
            showToast(data.message, 'success');

        } catch (error) {
            console.error('Erreur:', error);
            bouton.innerHTML = originalHTML;
            showToast(error.message || 'Erreur lors du changement de statut', 'danger');
        } finally {
            bouton.disabled = false;
        }
    }

    /**
     * Récupère la couleur Bootstrap du statut actuel
     */
    function getCurrentColor(config, statut) {
        const statutInfo = config.statuts.find(s => s.value === statut);
        return statutInfo ? statutInfo.color : 'secondary';
    }

    /**
     * Récupère l'icône du statut actuel
     */
    function getCurrentIcon(config, statut) {
        const statutInfo = config.statuts.find(s => s.value === statut);
        return statutInfo ? statutInfo.icon : 'bi-question-circle';
    }

    /**
     * Récupère le libellé du statut actuel
     */
    function getCurrentLabel(config, statut) {
        const statutInfo = config.statuts.find(s => s.value === statut);
        return statutInfo ? statutInfo.label : statut;
    }

    /**
     * Affiche un toast de notification
     */
    function showToast(message, type = 'info') {
        // Si Bootstrap Toast est disponible
        const toastContainer = document.getElementById('toast-container') || createToastContainer();
        
        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center text-white bg-${type} border-0`;
        toastEl.setAttribute('role', 'alert');
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        toastContainer.appendChild(toastEl);
        const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
        toast.show();
        
        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    }

    /**
     * Crée le conteneur de toasts s'il n'existe pas
     */
    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
        return container;
    }

    /**
     * Initialisation au chargement du DOM
     */
    document.addEventListener('DOMContentLoaded', function() {
        // Initialiser tous les éléments avec data-statut-change
        document.querySelectorAll('[data-statut-change]').forEach(initStatutDropdown);
    });

    // Exposer les fonctions pour usage externe
    window.TunnelConversion = {
        init: initStatutDropdown,
        changerStatut: changerStatut
    };

})();
