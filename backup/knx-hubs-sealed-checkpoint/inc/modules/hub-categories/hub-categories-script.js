/**
 * ==========================================================
 * Kingdom Nexus - Hub Categories Script (v1)
 * ----------------------------------------------------------
 * Handles Add + Toggle logic with REST, mirrors Cities.
 * ==========================================================
 */

document.addEventListener("DOMContentLoaded", () => {
  const wrapper = document.querySelector(".knx-cities-wrapper");
  if (!wrapper) return;

  const apiAdd = wrapper.dataset.apiAdd;
  const apiToggle = wrapper.dataset.apiToggle;
  const nonceAdd = wrapper.dataset.nonceAdd;
  const nonceToggle = wrapper.dataset.nonceToggle;

  // Modal logic
  const modal = document.getElementById("knxAddHubCategoryModal");
  const openBtn = document.getElementById("knxAddHubCategoryBtn");
  const closeBtn = document.getElementById("knxCloseHubCategoryModal");
  const form = document.getElementById("knxAddHubCategoryForm");

  if (openBtn) openBtn.addEventListener("click", () => modal.classList.add("active"));
  if (closeBtn) closeBtn.addEventListener("click", () => modal.classList.remove("active"));

  // Add category
  if (form) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const data = new FormData(form);
      data.append("knx_nonce", nonceAdd);

      const btn = form.querySelector("button[type='submit']");
      btn.disabled = true;

      try {
        const res = await fetch(apiAdd, { method: "POST", body: data });
        const out = await res.json();

        if (out.success) {
          knxToast("✅ Category added", "success");
          setTimeout(() => location.reload(), 800);
        } else {
          knxToast(out.error || "⚠️ Error adding category", "error");
          btn.disabled = false;
        }
      } catch {
        knxToast("⚠️ Network error adding category", "error");
        btn.disabled = false;
      }
    });
  }

  // Toggle category
  const toggles = document.querySelectorAll(".knx-toggle-hub-category");

  toggles.forEach((toggle) => {
    toggle.addEventListener("change", async (e) => {
      const row = e.target.closest("tr");
      const id = row.dataset.id;
      const status = e.target.checked ? "active" : "inactive";

      try {
        const res = await fetch(apiToggle, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ id, status, knx_nonce: nonceToggle }),
        });
        const out = await res.json();

        if (out.success) {
          const label = row.querySelector(".status-active, .status-inactive");
          if (label) {
            label.textContent = status === "active" ? "Active" : "Inactive";
            label.className = status === "active" ? "status-active" : "status-inactive";
          }
          knxToast("⚙️ Category status updated", "success");
        } else {
          knxToast(out.error || "❌ Toggle failed", "error");
          e.target.checked = !e.target.checked;
        }
      } catch (err) {
        console.error('Toggle error:', err);
        knxToast("⚠️ Network error toggling category", "error");
        e.target.checked = !e.target.checked;
      }
    });
  });
});
