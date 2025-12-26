/**
 * ==========================================================
 * Kingdom Nexus - Cart Drawer (Production, DB Sync)
 * ----------------------------------------------------------
 * - Right side drawer (no overlay)
 * - Render items from localStorage("knx_cart")
 * - Sync cart with backend: /wp-json/knx/v1/cart/sync
 * - Quantity +/- and Remove
 * - Subtotal live
 * ==========================================================
 */

document.addEventListener("DOMContentLoaded", () => {
  const drawer   = document.getElementById("knxCartDrawer");
  const btnToggle = document.getElementById("knxCartToggle");
  const btnClose  = document.getElementById("knxCartClose");
  const listEl    = document.getElementById("knxCartItems");
  const totalEl   = document.getElementById("knxCartTotal");

  // Si falta algo crítico, salimos silenciosamente
  if (!drawer || !btnToggle || !btnClose || !listEl || !totalEl) {
    return;
  }

  /* ---------------------------------------------------------
   * DRAWER OPEN/CLOSE
   * (usa la clase .is-open que define tu CSS)
   * --------------------------------------------------------- */
  function openDrawer() {
    drawer.classList.add("is-open");
    // No bloqueamos scroll global para que se sienta ligero
  }

  function closeDrawer() {
    drawer.classList.remove("is-open");
  }

  btnToggle.addEventListener("click", (e) => {
    e.preventDefault();
    if (drawer.classList.contains("is-open")) {
      closeDrawer();
    } else {
      openDrawer();
    }
  });

  btnClose.addEventListener("click", (e) => {
    e.preventDefault();
    closeDrawer();
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && drawer.classList.contains("is-open")) {
      closeDrawer();
    }
  });

  /* ---------------------------------------------------------
   * CART STORAGE HELPERS (localStorage)
   * --------------------------------------------------------- */
  function readCart() {
    try {
      const raw = window.localStorage.getItem("knx_cart");
      if (!raw) return [];
      const parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed : [];
    } catch (_) {
      return [];
    }
  }

  function saveCart(cart) {
    try {
      window.localStorage.setItem("knx_cart", JSON.stringify(cart || []));
    } catch (_) {
      // ignoramos errores de cuota / modo privado
    }

    // Dispara evento global para otros módulos (navbar badge, etc.)
    try {
      const ev = new Event("knx-cart-updated");
      window.dispatchEvent(ev);
    } catch (_) {
      if (typeof document.createEvent === "function") {
        const evt = document.createEvent("Event");
        evt.initEvent("knx-cart-updated", true, true);
        window.dispatchEvent(evt);
      }
    }

    // Sincroniza con el backend (DB)
    syncCartToServer(cart || []);
  }

  /* ---------------------------------------------------------
   * SYNC CON BACKEND (DB)
   * --------------------------------------------------------- */
  function syncCartToServer(cart) {
    // Endpoint REST fijo; el permiso/seguridad se maneja en api-cart.php
    const url = "/wp-json/knx/v1/cart/sync";

    try {
      window.fetch(url, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json"
        },
        // Mandamos con dos llaves por compatibilidad: items + cart
        body: JSON.stringify({
          items: cart,
          cart: cart
        })
      }).catch(() => {
        // Fallo silencioso, el UI sigue funcionando con localStorage
      });
    } catch (_) {
      // Ambiente sin fetch; simplemente no sincronizamos
    }
  }

  /* ---------------------------------------------------------
   * RENDER FUNCTION
   * --------------------------------------------------------- */
  function renderCart() {
    const cart = readCart();
    listEl.innerHTML = "";

    if (!cart.length) {
      totalEl.textContent = "$0.00";
      listEl.innerHTML = '<div class="knx-cart-empty">Your cart is empty</div>';
      return;
    }

    let subtotal = 0;

    cart.forEach((item) => {
      const qty    = item.quantity || 1;
      const unit   = item.unit_price_with_modifiers || item.base_price || 0;
      const line   = item.line_total != null ? item.line_total : unit * qty;
      subtotal    += line;

      const modsText = item.modifiers && item.modifiers.length
        ? item.modifiers
            .map((m) => {
              const names = (m.options || []).map((o) => o.name).join(", ");
              return names ? `${m.name}: ${names}` : m.name;
            })
            .join(" • ")
        : "";

      const safeName  = item.name || "";
      const safeNotes = item.notes || "";

      const html = `
        <div class="knx-cart-line" data-id="${item.id}">
          <div class="knx-cart-line__top">
            <div class="knx-cart-line__title-row">
              <div style="display:flex;align-items:center;gap:8px;">
                ${item.image ? `<img src="${item.image}" alt="${safeName}" style="width:40px;height:40px;object-fit:cover;border-radius:8px;">` : ""}
                <h4 class="knx-cart-line__name">${safeName}</h4>
              </div>
              <div class="knx-cart-line__price">$${line.toFixed(2)}</div>
            </div>
            ${modsText ? `<div class="knx-cart-line__mods">${modsText}</div>` : ""}
            ${safeNotes ? `<div class="knx-cart-line__notes">${safeNotes}</div>` : ""}
          </div>

          <div class="knx-cart-line__bottom">
            <button class="knx-cart-line__qty-btn" data-delta="-1" type="button">-</button>
            <span class="knx-cart-line__qty">${qty}</span>
            <button class="knx-cart-line__qty-btn" data-delta="1" type="button">+</button>

            <button class="knx-cart-line__remove" type="button">Remove</button>
          </div>
        </div>
      `;

      listEl.insertAdjacentHTML("beforeend", html);
    });

    totalEl.textContent = "$" + subtotal.toFixed(2);
  }

  /* ---------------------------------------------------------
   * EVENTOS: MODIFY QTY / REMOVE
   * --------------------------------------------------------- */
  listEl.addEventListener("click", (e) => {
    const target = e.target;
    const lineEl = target.closest(".knx-cart-line");
    if (!lineEl) return;

    const id = lineEl.getAttribute("data-id");
    if (!id) return;

    let cart = readCart();
    const itemIndex = cart.findIndex((i) => i.id === id);
    if (itemIndex === -1) return;

    const item = cart[itemIndex];

    // Remove
    if (target.classList.contains("knx-cart-line__remove")) {
      cart.splice(itemIndex, 1);
      saveCart(cart);
      renderCart();
      return;
    }

    // Qty +/- 
    if (target.classList.contains("knx-cart-line__qty-btn")) {
      const delta = parseInt(target.getAttribute("data-delta"), 10);
      if (!delta) return;

      const currentQty = item.quantity || 1;
      const newQty = currentQty + delta;
      if (newQty < 1) return;

      item.quantity = newQty;

      const unit = item.unit_price_with_modifiers || item.base_price || 0;
      item.line_total = unit * newQty;

      cart[itemIndex] = item;
      saveCart(cart);
      renderCart();
      return;
    }
  });

  /* ---------------------------------------------------------
   * INITIAL RENDER + SYNC LISTENER
   * --------------------------------------------------------- */
  renderCart();

  // Cada vez que otro módulo actualice el carrito, volvemos a pintar
  window.addEventListener("knx-cart-updated", renderCart);
});
