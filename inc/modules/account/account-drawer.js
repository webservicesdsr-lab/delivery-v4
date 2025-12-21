document.addEventListener("DOMContentLoaded", () => {
  const drawer  = document.getElementById("knxAccountDrawer");
  const overlay = document.getElementById("knxAccountOverlay");
  const openBtn = document.getElementById("knxAccountToggle");
  const closeBtn = document.getElementById("knxAccountClose");

  if (!drawer || !overlay) return;

  function openDrawer() {
    drawer.classList.add("is-open");
    overlay.classList.add("is-open");
    drawer.setAttribute("aria-hidden", "false");
    overlay.setAttribute("aria-hidden", "false");
    if (openBtn) openBtn.setAttribute("aria-expanded", "true");
    document.documentElement.classList.add("knx-drawer-lock");
  }

  function closeDrawer() {
    drawer.classList.remove("is-open");
    overlay.classList.remove("is-open");
    drawer.setAttribute("aria-hidden", "true");
    overlay.setAttribute("aria-hidden", "true");
    if (openBtn) openBtn.setAttribute("aria-expanded", "false");
    document.documentElement.classList.remove("knx-drawer-lock");
  }

  if (openBtn) {
    openBtn.addEventListener("click", (e) => {
      e.preventDefault();
      openDrawer();
    });
  }

  if (closeBtn) closeBtn.addEventListener("click", closeDrawer);
  overlay.addEventListener("click", closeDrawer);

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeDrawer();
  });
});
