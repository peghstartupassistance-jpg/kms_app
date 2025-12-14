// KMS Gestion – Dropdown visibility and dropup behavior in tables
(function(){
  document.addEventListener('click', function(e){
    const toggle = e.target.closest('[data-bs-toggle="dropdown"]');
    if (!toggle) return;
    const dropdown = toggle.closest('.dropdown');
    if (!dropdown) return;

    // Remove previous state
    dropdown.classList.remove('dropup');

    // Determine available space below and above
    const rect = toggle.getBoundingClientRect();
    const menu = dropdown.querySelector('.dropdown-menu');
    if (!menu) return;

    // Temporarily make menu visible to measure height (without showing to user)
    const prevDisplay = menu.style.display;
    const prevVis = menu.style.visibility;
    const prevPos = menu.style.position;
    menu.style.visibility = 'hidden';
    menu.style.display = 'block';
    menu.style.position = 'absolute';
    const menuHeight = menu.offsetHeight || 200; // fallback
    menu.style.visibility = prevVis;
    menu.style.display = prevDisplay;
    menu.style.position = prevPos;

    const viewportHeight = window.innerHeight;
    const spaceBelow = viewportHeight - rect.bottom;
    const spaceAbove = rect.top;

    // If not enough space below but enough above, force dropup
    if (spaceBelow < menuHeight && spaceAbove > menuHeight) {
      dropdown.classList.add('dropup');
    }
  }, true);
})();
