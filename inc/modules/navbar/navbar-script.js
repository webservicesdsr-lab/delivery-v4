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
  const adminSidebar = document.getElementById("knxAdminSidebar");
  const adminOverlay = document.getElementById("knxAdminOverlay");
  const adminClose = document.getElementById("knxAdminClose");

  function openAdminSidebar() {
    if (adminSidebar && adminOverlay) {
      adminSidebar.classList.add("active");
      adminOverlay.classList.add("active");
      document.body.style.overflow = "hidden";
    }
  }

  function closeAdminSidebar() {
    if (adminSidebar && adminOverlay) {
      adminSidebar.classList.remove("active");
      adminOverlay.classList.remove("active");
      document.body.style.overflow = "";
    }
  }

  if (adminBtn) {
    adminBtn.addEventListener("click", openAdminSidebar);
  }

  if (adminClose) {
    adminClose.addEventListener("click", closeAdminSidebar);
  }

  if (adminOverlay) {
    adminOverlay.addEventListener("click", closeAdminSidebar);
  }

  // Close admin sidebar with ESC key
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && adminSidebar?.classList.contains("active")) {
      closeAdminSidebar();
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
