/**
 * ==========================================================
 * Kingdom Nexus - Edit City Script (v3.0 Production)
 * ----------------------------------------------------------
 * Handles City Info (name & status) updates via REST API.
 * Uses knxToast() for all UX feedback.
 * ==========================================================
 */

document.addEventListener("DOMContentLoaded", () => {
  const wrapper = document.querySelector(".knx-edit-city-wrapper");
  if (!wrapper) return;

  const apiGet = wrapper.dataset.apiGet;
  const apiUpdate = wrapper.dataset.apiUpdate;
  const cityId = wrapper.dataset.cityId;
  const nonce = wrapper.dataset.nonce;

  const nameInput = document.getElementById("cityName");
  const statusSelect = document.getElementById("cityStatus");
  const saveBtn = document.getElementById("saveCity");

  /** Load current city data */
  async function loadCity() {
    try {
      const res = await fetch(`${apiGet}?id=${cityId}`);
      const data = await res.json();

      if (data.success && data.city) {
        nameInput.value = data.city.name || "";
        statusSelect.value = data.city.status || "active";
      } else {
        knxToast(data.error || "Unable to load city", "error");
      }
    } catch {
      knxToast("Network error while loading city", "error");
    }
  }

  /** Save city updates */
  if (saveBtn) {
    saveBtn.addEventListener("click", async () => {
      const payload = {
        id: parseInt(cityId),
        name: nameInput.value.trim(),
        status: statusSelect.value,
        knx_nonce: nonce,
      };

      try {
        const res = await fetch(apiUpdate, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload),
        });
        const data = await res.json();

        if (data.success) {
          knxToast(data.message || "✅ City updated successfully", "success");
        } else {
          knxToast(data.error || "⚠️ Update failed", "error");
        }
      } catch {
        knxToast("Network error while saving city", "error");
      }
    });
  }

  loadCity();
});
