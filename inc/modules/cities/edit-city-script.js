/**
 * ==========================================================
 * Kingdom Nexus - Edit City Script (SEALED v3)
 * ----------------------------------------------------------
 * Handles City Info (name & status) updates via REST API.
 * Uses knxToast() for all UX feedback.
 *
 * Notes:
 * - Uses v2 endpoints provided by data attributes.
 * - Sends/reads `city_id` (pure v2).
 * - Keeps cookies/session with credentials: "same-origin".
 * ==========================================================
 */

document.addEventListener("DOMContentLoaded", () => {
  const wrapper = document.querySelector(".knx-edit-city-wrapper");
  if (!wrapper) return;

  const apiGet = wrapper.dataset.apiGet;
  const apiUpdate = wrapper.dataset.apiUpdate;
  const cityId = parseInt(wrapper.dataset.cityId || "0", 10);
  const nonce = wrapper.dataset.nonce || "";

  const nameInput = document.getElementById("cityName");
  const statusSelect = document.getElementById("cityStatus");
  const saveBtn = document.getElementById("saveCity");

  const toast = (msg, type) => {
    if (typeof window.knxToast === "function") return window.knxToast(msg, type);
    console[type === "error" ? "error" : "log"](msg);
    alert(msg);
  };

  /** Load current city data */
  async function loadCity() {
    if (!cityId) {
      toast("Invalid city id", "error");
      return;
    }

    try {
      const res = await fetch(`${apiGet}?city_id=${encodeURIComponent(cityId)}`, {
        method: "GET",
        credentials: "same-origin",
        headers: { "Accept": "application/json" },
      });

      let data = null;
      try {
        data = await res.json();
      } catch {
        data = null;
      }

      if (!res.ok) {
        toast((data && data.error) ? data.error : "Unable to load city", "error");
        return;
      }

      if (data && data.success && data.city) {
        nameInput.value = data.city.name || "";
        statusSelect.value = data.city.status || "active";
        return;
      }

      toast((data && data.error) ? data.error : "Unable to load city", "error");
    } catch {
      toast("Network error while loading city", "error");
    }
  }

  /** Save city updates */
  if (saveBtn) {
    saveBtn.addEventListener("click", async () => {
      const payload = {
        city_id: cityId,
        name: (nameInput.value || "").trim(),
        status: statusSelect.value,
        knx_nonce: nonce,
      };

      if (!payload.name) {
        toast("City name is required", "error");
        return;
      }

      try {
        const res = await fetch(apiUpdate, {
          method: "POST",
          credentials: "same-origin",
          headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
          },
          body: JSON.stringify(payload),
        });

        let data = null;
        try {
          data = await res.json();
        } catch {
          data = null;
        }

        if (!res.ok) {
          toast((data && data.error) ? data.error : "Update failed", "error");
          return;
        }

        if (data && data.success) {
          toast(data.message || "✅ City updated successfully", "success");
        } else {
          toast((data && data.error) ? data.error : "⚠️ Update failed", "error");
        }
      } catch {
        toast("Network error while saving city", "error");
      }
    });
  }

  loadCity();
});
