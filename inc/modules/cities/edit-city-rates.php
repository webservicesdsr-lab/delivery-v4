/**
 * ==========================================================
 * Kingdom Nexus - Edit City Rates (v2.0 Production)
 * ----------------------------------------------------------
 * Handles dynamic delivery rates form.
 * Add, remove, and save all rates via REST.
 * ==========================================================
 */

document.addEventListener("DOMContentLoaded", () => {
  const container = document.querySelector("#knxRatesContainer");
  const addBtn = document.getElementById("addRateBtn");
  const saveBtn = document.getElementById("saveRatesBtn");
  if (!container) return;

  const cityId = knx_city.id;
  const nonce = knx_city.nonce;
  const apiGet = `${knx_api.root}knx/v1/get-city-details?id=${cityId}`;
  const apiSave = `${knx_api.root}knx/v1/update-city-rates`;

  /**
   * Render a single rate row
   */
  function createRateRow(from = "", to = "", price = "") {
    const row = document.createElement("div");
    row.className = "knx-rate-row";
    row.innerHTML = `
      <input type="number" class="from" placeholder="From" value="${from}">
      <input type="number" class="to" placeholder="To" value="${to}">
      <input type="number" class="price" placeholder="Price" value="${price}">
      <button type="button" class="remove">Remove</button>
    `;
    row.querySelector(".remove").addEventListener("click", () => row.remove());
    container.appendChild(row);
  }

  /**
   * Load existing rates
   */
  async function loadRates() {
    try {
      const res = await fetch(apiGet);
      const data = await res.json();

      if (data.success && data.rates) {
        container.innerHTML = "";
        data.rates.forEach((r) => createRateRow(r.from_miles, r.to_miles, r.price));
      } else {
        createRateRow();
      }
    } catch {
      knxToast("⚠️ Error loading rates", "error");
    }
  }

  /**
   * Add new rate row
   */
  if (addBtn) addBtn.addEventListener("click", () => createRateRow());

  /**
   * Save all rates
   */
  if (saveBtn) {
    saveBtn.addEventListener("click", async () => {
      const rows = container.querySelectorAll(".knx-rate-row");
      const rates = Array.from(rows).map((row) => ({
        from_miles: row.querySelector(".from").value,
        to_miles: row.querySelector(".to").value,
        price: row.querySelector(".price").value,
      }));

      try {
        const res = await fetch(apiSave, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ city_id: cityId, rates, knx_nonce: nonce }),
        });
        const out = await res.json();

        if (out.success) {
          knxToast("✅ Rates saved successfully", "success");
          loadRates();
        } else {
          knxToast(out.error || "⚠️ Failed to save rates", "error");
        }
      } catch {
        knxToast("⚠️ Network error saving rates", "error");
      }
    });
  }

  loadRates();
});
