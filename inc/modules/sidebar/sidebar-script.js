/**
 * Kingdom Nexus - Sidebar Script (v6.0 Safe)
 * Responsive expand/collapse with smooth UX.
 */

document.addEventListener("DOMContentLoaded", () => {
  const sidebar = document.getElementById("knxSidebar");
  const expandBtn = document.getElementById("knxExpandMobile");

  if (!sidebar || !expandBtn) return;

  expandBtn.addEventListener("click", () => {
    sidebar.classList.toggle("expanded");
    expandBtn.innerHTML = sidebar.classList.contains("expanded")
      ? '<i class="fas fa-angles-left"></i>'
      : '<i class="fas fa-angles-right"></i>';
  });
});
