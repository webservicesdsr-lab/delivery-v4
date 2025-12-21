// Kingdom Nexus - Navbar Script (v7 Simple - Local Bites)
document.addEventListener("DOMContentLoaded", () => {
  // ===== Location Chip =====
  const locTextEl = document.getElementById("knxLocChipText");

  function readStoredLocation() {
    const explicit = localStorage.getItem("knx_location");
    if (explicit && explicit.trim()) return explicit.trim();
    try {
      const cached = JSON.parse(sessionStorage.getItem('knx_user_location') || 'null');
      if (cached && cached.hub && cached.hub.name) return cached.hub.name;
    } catch(_){}
    return '';
  }

  function setLocText(txt){
    if (!locTextEl) return;
    locTextEl.textContent = txt && txt.length > 0 ? txt : 'Set location';
  }

  setLocText(readStoredLocation());
  
  // ===== Admin Sidebar =====
  const adminBtn = document.getElementById("knxAdminMenuBtn");

  // Two possible admin UIs:
  // A) Legacy navbar admin sidebar: #knxAdminSidebar + #knxAdminOverlay
  // B) New injected admin sidebar: #knxSidebar (from sidebar-render.php)

  const legacySidebar = document.getElementById("knxAdminSidebar");
  const legacyOverlay = document.getElementById("knxAdminOverlay");
  const legacyClose = document.getElementById("knxAdminClose");

  const injectedSidebar = document.getElementById("knxSidebar"); // new system
  const injectedExpandBtn = document.getElementById("knxExpandMobile"); // optional

  function lockScroll() { document.documentElement.classList.add("knx-drawer-lock"); }
  function unlockScroll() { document.documentElement.classList.remove("knx-drawer-lock"); }

  function openLegacyAdminSidebar() {
    if (!legacySidebar || !legacyOverlay) return;
    legacySidebar.classList.add("active");
    legacyOverlay.classList.add("active");
    lockScroll();
  }

  function closeLegacyAdminSidebar() {
    if (!legacySidebar || !legacyOverlay) return;
    legacySidebar.classList.remove("active");
    legacyOverlay.classList.remove("active");
    unlockScroll();
  }

  // For injected sidebar: we don't use overlay, just ensure it's visible/expanded on mobile
  function openInjectedAdminSidebar() {
    if (!injectedSidebar) return;
    // On mobile, ensure expanded so links are visible
    if (window.innerWidth <= 900) injectedSidebar.classList.add("expanded");
  }

  function closeInjectedAdminSidebar() {
    if (!injectedSidebar) return;
    if (window.innerWidth <= 900) injectedSidebar.classList.remove("expanded");
  }

  function openAdminSidebar() {
    // Prefer legacy overlay system if present (navbar admin sidebar)
    if (legacySidebar) return openLegacyAdminSidebar();
    // Otherwise use injected system (internal pages sidebar)
    if (injectedSidebar) return openInjectedAdminSidebar();
  }

  function closeAdminSidebar() {
    if (legacySidebar) return closeLegacyAdminSidebar();
    if (injectedSidebar) return closeInjectedAdminSidebar();
  }

  if (adminBtn) {
    adminBtn.addEventListener("click", (e) => {
      e.preventDefault();
      openAdminSidebar();
    });
  }

  if (legacyClose) legacyClose.addEventListener("click", closeLegacyAdminSidebar);
  if (legacyOverlay) legacyOverlay.addEventListener("click", closeLegacyAdminSidebar);

  // Close admin UI with ESC key
  document.addEventListener("keydown", (e) => {
    if (e.key !== "Escape") return;
    // Close legacy overlay if open
    if (legacySidebar && legacySidebar.classList.contains("active")) closeLegacyAdminSidebar();
    // Collapse injected sidebar on mobile
    if (injectedSidebar && window.innerWidth <= 900 && injectedSidebar.classList.contains("expanded")) {
      closeInjectedAdminSidebar();
    }
  });

  // ===== Explore Submenu Toggle =====
  const exploreToggle = document.getElementById("knxExploreToggle");
  const exploreSubmenu = document.getElementById("knxExploreSubmenu");

  if (exploreToggle && exploreSubmenu) {
    exploreToggle.addEventListener("click", function(e) {
      e.preventDefault();
      exploreToggle.classList.toggle("active");
      exploreSubmenu.classList.toggle("active");
    });
  }
  
  // ===== Cart Badge Management =====
  function updateCartBadge() {
    const badge = document.getElementById("knxCartBadge");
    if (!badge) return;

    // Get cart from localStorage
    const cart = JSON.parse(localStorage.getItem("knx_cart") || "[]");
    const totalItems = cart.reduce((sum, item) => sum + (item.quantity || 1), 0);

    // Update badge
    if (totalItems > 0) {
      badge.textContent = totalItems > 99 ? "99+" : totalItems;
      badge.style.display = "flex";
      badge.setAttribute("data-count", totalItems);
    } else {
      badge.style.display = "none";
      badge.setAttribute("data-count", "0");
    }
  }

  // Update badge on load
  updateCartBadge();

  // Listen for cart changes from other tabs/windows
  window.addEventListener("storage", (e) => {
    if (e.key === "knx_cart") {
      updateCartBadge();
    }
  });

  // Listen for custom cart update events
  window.addEventListener("knx-cart-updated", updateCartBadge);

  // ===== Public API for other scripts =====
  window.knxNavbar = {
    updateCart: () => {
      updateCartBadge();
    },
    
    openAdminMenu: () => {
      openAdminSidebar();
    },
    
    closeAdminMenu: () => {
      closeAdminSidebar();
    },
    setLocation: (locationLabel) => {
      try { if (locationLabel) localStorage.setItem('knx_location', locationLabel); } catch(_){ }
      setLocText(locationLabel);
    }
  };
});
