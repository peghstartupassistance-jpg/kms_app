<!-- Bootstrap 5 JS Bundle (inclut Popper) -->
<script 
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" 
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" 
    crossorigin="anonymous"
></script>

<!-- Modern Lists JavaScript -->
<script src="<?= ($appBaseUrl !== '' ? $appBaseUrl : '') ?>/assets/js/modern-lists.js"></script>

<!-- Modern Forms JavaScript -->
<script src="<?= ($appBaseUrl !== '' ? $appBaseUrl : '') ?>/assets/js/modern-forms.js"></script>

<!-- Tunnel de conversion (changement dynamique des statuts) -->
<script src="<?= ($appBaseUrl !== '' ? $appBaseUrl : '') ?>/assets/js/tunnel-conversion.js"></script>

<!-- Dropdown fix: auto dropup inside tables when needed -->
<script src="<?= ($appBaseUrl !== '' ? $appBaseUrl : '') ?>/assets/js/dropdown-fix.js"></script>

</body>
<script>
// Sidebar collapsible behavior with persistence
document.addEventListener('DOMContentLoaded', function(){
    const btn = document.getElementById('toggleSidebarBtn');
    const btnV = document.getElementById('toggleSidebarVerticalBtn');
    const sidebar = document.querySelector('.kms-sidebar');
    const layout = document.getElementById('layoutRoot');
    const key = 'kms.sidebar.collapsed';
    const keyV = 'kms.sidebar.verticalCollapsed';
    const collapsibleKey = 'kms.sidebar.collapsibleState';

    function applyState(collapsed){
        if (!sidebar || !layout) return;
        sidebar.classList.toggle('collapsed', collapsed);
        layout.classList.toggle('sidebar-collapsed', collapsed);
    }

    // Load initial state
    const saved = localStorage.getItem(key);
    applyState(saved === 'true');

    // Load vertical state
    const savedV = localStorage.getItem(keyV);
    applyVertical(savedV === 'true');

    if (btn) {
        btn.addEventListener('click', function(){
            const now = !(sidebar && sidebar.classList.contains('collapsed'));
            localStorage.setItem(key, String(now));
            applyState(now);
        });
    }
    if (btnV) {
        btnV.addEventListener('click', function(){
            const nowV = !(sidebar && sidebar.classList.contains('vertical-collapsed'));
            localStorage.setItem(keyV, String(nowV));
            applyVertical(nowV);
        });
    }

    function applyVertical(collapsed){
        if (!sidebar || !layout) return;
        sidebar.classList.toggle('vertical-collapsed', collapsed);
        layout.classList.toggle('vertical-collapsed', collapsed);
    }

    // Collapsible modules per section
    const sectionStates = JSON.parse(localStorage.getItem(collapsibleKey) || '{}');
    const defaultsCollapsed = new Set(['marketing','services']);
    document.querySelectorAll('.kms-sidebar .sidebar-section').forEach(section => {
        const key = section.getAttribute('data-section-key') || 'unknown';
        // Apply saved state
        if (sectionStates[key] === true || (!sectionStates.hasOwnProperty(key) && defaultsCollapsed.has(key))) {
            section.classList.add('collapsed');
        }
        const titleBtn = section.querySelector('.sidebar-section-title.button');
        if (titleBtn) {
            const toggle = () => {
                const willCollapse = !section.classList.contains('collapsed');
                section.classList.toggle('collapsed', willCollapse);
                sectionStates[key] = willCollapse;
                localStorage.setItem(collapsibleKey, JSON.stringify(sectionStates));
            };
            titleBtn.addEventListener('click', toggle);
            titleBtn.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggle();
                }
            });
        }
    });
});
</script>
</html>
