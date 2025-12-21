/**
 * ==========================================================
 * Kingdom Nexus - Edit City Rates (v3.1 UX Enhanced)
 * ----------------------------------------------------------
 * Handles Add / Remove / Save of delivery tiers.
 * Improved visual feedback and intuitive placeholders.
 * REST integrated with /get-city-details and /update-city-rates
 * ==========================================================
 */

document.addEventListener("DOMContentLoaded", () => {
  const wrapper = document.querySelector(".knx-edit-city-rates-wrapper");
  if (!wrapper) return;

  const apiGet = wrapper.dataset.apiGet;
  const apiUpdate = wrapper.dataset.apiUpdate;
  const cityId = wrapper.dataset.cityId;
  const nonce = wrapper.dataset.nonce;

  const container = document.getElementById("knxRatesContainer");
  const addBtn = document.getElementById("addRateBtn");
  const saveBtn = document.getElementById("saveRatesBtn");

  /** Render a single rate row */
  function createRateRow(from = "", to = "", price = "") {
    const displayTo = to === null || to === "null" ? "" : to;

    const row = document.createElement("div");
    row.className = "knx-rate-row fade-in";
    row.innerHTML = `
      <div class="knx-input-group">
        <label>From (mi)</label>
        <input type="number" class="from" placeholder="e.g. 0" value="${from}">
      </div>
      <div class="knx-input-group">
        <label>To (mi)</label>
        <input type="number" class="to" placeholder="Leave empty for 'and above'" value="${displayTo}">
      </div>
      <div class="knx-input-group">
        <label>Price ($)</label>
        <input type="number" class="price" step="0.01" placeholder="e.g. 9.99" value="${price}">
      </div>
      <button type="button" class="remove" title="Remove this rate">
        <i class="fas fa-trash-alt"></i>
      </button>
    `;
    row.querySelector(".remove").addEventListener("click", () => {
      row.classList.add("fade-out");
      setTimeout(() => row.remove(), 300);
    });
    container.appendChild(row);
  }

  /** Load existing rates */
  async function loadRates() {
    try {
      const res = await fetch(`${apiGet}?id=${cityId}`);
      const data = await res.json();

      container.innerHTML = "";

      if (data.success && data.rates && data.rates.length) {
        data.rates.forEach((r) => {
          createRateRow(r.from_miles ?? "", r.to_miles ?? "", r.price ?? "");
        });
      } else {
        // Default fallback
        createRateRow(0, 6, 5.99);
        createRateRow(6, 9, 8.99);
        createRateRow(9, 12, 10.99);
        createRateRow(12, 14, 11.99);
        createRateRow(14, 19, 12.99);
        createRateRow(19, "", 15.99); // "and above"
      }
    } catch {
      knxToast("⚠️ Error loading rates", "error");
    }
  }

  /** Add new rate row */
  if (addBtn) {
    addBtn.addEventListener("click", () => createRateRow());
  }

  /** Save all rates */
  if (saveBtn) {
    saveBtn.addEventListener("click", async () => {
      const rows = container.querySelectorAll(".knx-rate-row");
      const rates = Array.from(rows).map((row) => {
        const toValue = row.querySelector(".to").value.trim();
        return {
          from_miles: row.querySelector(".from").value,
          to_miles: toValue === "" ? null : toValue,
          price: row.querySelector(".price").value,
        };
      });

      try {
        const res = await fetch(apiUpdate, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ city_id: cityId, rates, knx_nonce: nonce }),
        });
        const out = await res.json();

        if (out.success) {
          knxToast("✅ Rates saved successfully", "success");
          highlightSaved();
        } else {
          knxToast(out.error || "⚠️ Failed to save rates", "error");
        }
      } catch {
        knxToast("⚠️ Network error saving rates", "error");
      }
    });
  }

  /** Highlight after save */
  function highlightSaved() {
    const rows = container.querySelectorAll(".knx-rate-row");
    rows.forEach((r) => {
      r.classList.add("highlight");
      setTimeout(() => r.classList.remove("highlight"), 600);
    });
  }

  loadRates();
});
