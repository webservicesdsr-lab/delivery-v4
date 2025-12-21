/**
 * ==========================================================
 * Kingdom Nexus - Cities Script (v3.0 Production)
 * ----------------------------------------------------------
 * Handles Add + Toggle logic with REST.
 * Uses knxToast() for feedback.
 * ==========================================================
 */

document.addEventListener("DOMContentLoaded", () => {
  const wrapper = document.querySelector(".knx-cities-wrapper");
  if (!wrapper) return;

  const apiAdd = wrapper.dataset.apiAdd;
  const apiToggle = wrapper.dataset.apiToggle;
  const nonceAdd = wrapper.dataset.nonceAdd;
  const nonceToggle = wrapper.dataset.nonceToggle;

  /** -----------------------------
   * Modal Logic
   * ----------------------------- */
  const modal = document.getElementById("knxAddCityModal");
  const openBtn = document.getElementById("knxAddCityBtn");
  const closeBtn = document.getElementById("knxCloseModal");
  const form = document.getElementById("knxAddCityForm");

  if (openBtn) openBtn.addEventListener("click", () => modal.classList.add("active"));
  if (closeBtn) closeBtn.addEventListener("click", () => modal.classList.remove("active"));

  /** -----------------------------
   * Add City
   * ----------------------------- */
  if (form) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const data = new FormData(form);
      data.append("knx_nonce", nonceAdd);

      const btn = form.querySelector("button[type='submit']");
      btn.disabled = true;

      try {
        const res = await fetch(apiAdd, {
          method: "POST",
          headers: { "X-KNX-CSRF": window.KNX?.csrf || "" },
          body: data
        });
        const out = await res.json();

        if (out.success) {
          knxToast("✅ City added successfully", "success");
          setTimeout(() => location.reload(), 800);
        } else {
          knxToast(out.error || "⚠️ Error adding city", "error");
          btn.disabled = false;
        }
      } catch {
        knxToast("⚠️ Network error adding city", "error");
        btn.disabled = false;
      }
    });
  }

  /** -----------------------------
   * Toggle City Active/Inactive
   * ----------------------------- */
  const toggles = document.querySelectorAll(".knx-toggle-city");
  
  toggles.forEach((toggle) => {
    toggle.addEventListener("change", async (e) => {
      const row = e.target.closest("tr");
      const id = row.dataset.id;
      const status = e.target.checked ? "active" : "inactive";

      try {
        const res = await fetch(apiToggle, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-KNX-CSRF": window.KNX?.csrf || ""
          },
          body: JSON.stringify({
            id,
            status,
            knx_nonce: nonceToggle,
          }),
        });
        const out = await res.json();

        if (out.success) {
          const label = row.querySelector(".status-active, .status-inactive");
          if (label) {
            label.textContent = status === "active" ? "Active" : "Inactive";
            label.className = status === "active" ? "status-active" : "status-inactive";
          }
          knxToast("⚙️ City status updated", "success");
        } else {
          knxToast(out.error || "❌ Toggle failed", "error");
          e.target.checked = !e.target.checked;
        }
      } catch (err) {
        console.error('Toggle error:', err);
        knxToast("⚠️ Network error toggling city", "error");
        e.target.checked = !e.target.checked;
      }
    });
  });
});
