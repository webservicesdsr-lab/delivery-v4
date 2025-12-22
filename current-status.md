# KNX Guidelines (Kingdom Nexus)

**Documento maestro de migración, seguridad y estilo**
Última actualización: **2025-12-22**
Owner: **Kingdom Nexus / Kingdom Builders**

---

## 1) De dónde venimos (Legacy)

### 1.1 Contexto

Kingdom Nexus nació con una base “legacy” que:

* Mezcla endpoints REST con lógica de UI.
* Tiene endpoints públicos/legacy que hay que **reemplazar sin romper el sistema**.
* Usa módulos internos estilo “CRUD” renderizados por shortcodes.

### 1.2 Problemas que detectamos en legacy

* Endpoints expuestos o inconsistentes: `permission_callback` permisivo, seguridad fragmentada.
* Diferencias de esquemas en DB (columnas presentes/ausentes según instalaciones).
* UI dependiente de endpoints antiguos y slugs antiguos (p. ej. `/edit-city/?id=`).
* “Theme bleed” (Hello Elementor) aplicando **hovers y estilos externos** no deseados.
* Riesgo de “critical errors” por callbacks incorrectos o wrappers no cargados a tiempo.

---

## 2) Dónde estamos (Estado actual estable)

### 2.1 Objetivo logrado: **KNX Cities v2 SEALED**

Se construyó un **CRUD v2 SEALED** para Cities con:

* **GET Cities**
* **Add City**
* **Operational Toggle**
* **Delete City (soft delete)**
* UI responsiva (tabla desktop + cards mobile)
* Modal Add + Modal Delete
* Toast global
* CSS sanitizado (bloquea hover bleed del theme)

✅ Resultado: **CRUD Cities v2 estable** y listo para permitir **eliminación de legacy** cuando se complete la migración de las pantallas relacionadas (principalmente edit-city).

---

## 3) A dónde vamos (Roadmap inmediato)

### 3.1 Prioridad #1: completar migración y borrar legacy Cities

Para borrar legacy sin romper:

1. Confirmar que el UI v2 SEALED cubre:

   * Add City (modal)
   * Toggle operational (y conservar status en DB)
   * Delete City (soft delete + bloqueo si hubs)
   * Edit redirect al nuevo slug (si aplica)
2. Migrar/crear **edit-city v2** (nuevo slug y nuevo endpoint set).
3. Remover includes legacy y endpoints legacy.
4. Mantener compat routes (si se necesitan temporalmente) y luego retirarlas.

### 3.2 Prioridad #2: aplicar patrón SEALED a otros recursos

* Hubs (si hay legacy mezclado)
* Menus
* Items / Categories
* Drivers / Customers
  La meta es **unificar infraestructura**: wrappers + guards + respuesta estándar.

---

## 4) Principios no negociables (Reglas del proyecto)

### 4.1 Seguridad

* Ningún endpoint “sensible” debe confiar en `permission_callback`.
* Permitimos `permission_callback => '__return_true'` **solo si**:

  * El handler exige sesión + rol + nonce donde aplique.
* Todo endpoint de mutación (POST/PUT/DELETE) requiere:

  1. Session válida
  2. Role permitido
  3. Nonce válido (action específica)

### 4.2 Wrappers obligatorios (REST infra)

Todos los endpoints SEALED deben usar el stack:

* `knx_rest_response()` / `knx_rest_error()` → formato estándar
* `knx_rest_require_session()` → **gating** de sesión
* `knx_rest_require_role()` → role check
* `knx_rest_verify_nonce()` → nonce check
* `knx_rest_wrap()` → normaliza errores / output

✅ Regla: **no inventar formatos**. Siempre devolver el payload estándar:

```json
{ "success": true|false, "message": "...", "data": ... }
```

### 4.3 Registro de rutas REST (patrón correcto)

Para evitar rutas 404 por wrap ejecutado “en el momento equivocado”:

* Usar un callback que envuelva **de forma lazy** si se detectan issues.

Patrón recomendado (safe):

* `callback` debe ser un callable válido para WP al registrar.
* Si hay dudas con `knx_rest_wrap('handler')` directo, usar:

  * callback closure que llama el wrap dentro.

### 4.4 DB “shape-safe”

Los endpoints deben asumir que columnas pueden no existir y actuar “best effort”:

* Detectar columnas con helper (SHOW COLUMNS).
* Soft delete solo si `deleted_at` existe.
* Operational solo si `is_operational` existe.
* Status mantenerlo (no borrar) como parte de la transición.

### 4.5 UI: “Echo assets” (estilo Nexus)

Los módulos internos suelen cargar assets con `<link>` y `<script>` (echo) dentro del shortcode.

* Es válido y forma parte del estilo del proyecto.
* No confiar en `enqueue` para estos módulos si el sistema ya está basado en echo.

### 4.6 Toast global

Existe un toast global:

* `window.knxToast(message, type)`
* Tipos: `success | error | info | warning`

Regla:

* Ningún JS de módulo debe llamar `window.knxToast.show(...)`.
* Se usa `knxToast("texto", "success")`.

### 4.7 Theme bleed / Hello Elementor

Las páginas internas (módulos) deben ser “theme-safe”:

* CSS scoped por contenedor del módulo (`.knx-cities-signed`).
* Bloquear hovers/colores que se cuelan del theme:

  * Links: hover/focus/active sin underline ni cambio de color
  * Buttons: sin transform/filter/shadow, sin transición impuesta por theme
* **No usar overrides globales** fuera del scope.

---

## 5) Estructura de carpetas (Arquitectura recomendada)

### 5.1 REST infra (helpers “no endpoints”)

`/inc/core/rest/`

* `knx-rest-response.php`
* `knx-rest-guard.php`
* `knx-rest-wrapper.php`

Regla:

* Aquí no se registran rutas.
* No se hace output.

### 5.2 Recursos SEALED (endpoints)

`/inc/core/resources/<resource>/`
Ejemplo:
`/inc/core/resources/knx-cities/`

* `get-cities.php`
* `add-city.php`
* `post-operational-toggle.php`
* `delete-city.php`

Regla:

* Cada archivo registra su(s) rutas y contiene handlers.
* Usa wrappers/guards.
* Respeta DB shape-safe.
* Respeta nonces.

### 5.3 UI de módulos internos

`/inc/modules/<module>/`
Ejemplo:
`/inc/modules/knx-cities/`

* `knx-cities-shortcode.php`
* `knx-cities-script.js`
* `knx-cities-style.css`
* `knx-cities-delete-modal.php`
* `knx-cities-delete-modal.js`

Regla:

* La UI consume **solo v2 SEALED endpoints** (no legacy).
* El CSS es scoped.
* JS usa `knxToast()`.

---

## 6) Contratos de endpoints (Cities v2 SEALED)

### 6.1 GET Cities

`GET /wp-json/knx/v2/cities/get`
Alias: `GET /wp-json/knx/v2/cities`

**Seguridad**:

* Session requerida
* Role: `super_admin | manager`

**Respuesta**:

* `data.scope`: `all | assigned`
* `data.cities`: lista

### 6.2 Add City

`POST /wp-json/knx/v2/cities/add`

**Payload**:

* `name`
* `knx_nonce` (action `knx_city_add`)

**Seguridad**:

* Session requerida
* Role: `super_admin`
* Nonce requerido

### 6.3 Operational Toggle

`POST /wp-json/knx/v2/cities/operational-toggle`

**Payload**:

* `city_id`
* `operational` (0|1)
* `knx_nonce` (action `knx_city_operational_toggle`)

**Seguridad**:

* Session requerida
* Role: `super_admin | manager`
* Nonce requerido
* Manager scope enforcement

### 6.4 Delete City (soft delete)

`POST /wp-json/knx/v2/cities/delete`

**Payload**:

* `city_id`
* `knx_nonce` (action `knx_city_delete`)

**Seguridad**:

* Session requerida
* Role: `super_admin`
* Nonce requerido
* Integridad: no borrar si hay hubs con city_id

---

## 7) Slugs y navegación (Módulos internos)

### 7.1 Política de slugs nuevos

Meta:

* Módulos internos migran a slugs nuevos (ej. `knx-edit-city`) para separar legacy.

Regla:

* Se permite compatibilidad temporal.
* Cuando el flujo esté migrado, se retiran slugs legacy.

---

## 8) Estándar de CSS para módulos internos

### 8.1 Scope obligatorio

* Todo el CSS debe colgar de un contenedor único: `.knx-<module>-signed` o similar.

### 8.2 Sanitización del theme

Dentro del scope:

* Bloquear link hover bleed
* Bloquear button hover bleed
* Deshabilitar transform/filter/shadow inesperado
* Mantener estilos consistentes con NEXUS

### 8.3 Qué NO debe tener hover

En módulos internos:

* Icono edit
* Icono delete
* Cancel (modal add)
* Cancel y Yes Delete (modal delete)

---

## 9) Metodología de migración (para Copilot)

### 9.1 Estrategia recomendada

1. Crear endpoints SEALED v2 en `/inc/core/resources/<resource>/`
2. Crear UI v2 que consuma SOLO v2
3. Añadir alias routes si hay UI legacy consumiendo endpoints viejos
4. Probar:

   * logged out → unauthorized
   * role incorrecto → forbidden
   * nonce inválido → forbidden
   * happy path
5. Crear checkpoint
6. Remover legacy gradualmente

### 9.2 Reglas para Copilot

* No inventar endpoints ni slugs.
* No romper el formato estándar de respuesta.
* No mover archivos a otras carpetas.
* No usar estilos globales fuera del scope del módulo.
* No introducir `wp_footer` como dependencia para módulos internos (evitar hooks que afecten navbar).
* Si se toca REST callback: usar patrón “callable seguro”.

---

## 10) Checkpoints ACTUAL:

* `knx-cities-v2-sealed-migrations-ready`
---

## 11) Estado final esperado (Definition of Done para borrar legacy)

Se puede eliminar legacy Cities cuando:

* `knx-cities` v2 SEALED cubre CRUD completo.
* `edit-city` ya está migrado al nuevo slug y consume v2 endpoints.
* No hay llamadas en frontend/admin a endpoints legacy.
* Se retiran includes legacy del loader principal.
* Se verifica que no hay 404 en `/wp-json/knx/v2/*` relevantes.
