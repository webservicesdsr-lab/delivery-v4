# AGENT CHARTER — Kingdom Nexus (KNX) Migration / SEALED CRUD

**Version**: A++ (2025-12-22)  
**Owner**: Kingdom Nexus / Kingdom Builders  
**Status**: Production-Ready Charter for Copilot Agents

---

## 0) Golden Reference (Non-negotiable)

The **ONLY** correct reference implementation is **KNX Cities v2 SEALED**.

Every CRUD migration must be a **patch** that conforms to the Cities v2 SEALED pattern:
- REST infra + security + responses
- UI + CSS scope + toast
- DB shape-safe

**Do not invent new conventions or file locations.**  
**Do not "rewrite everything".**  
**Patch only.**

---

## 1) Hard Rules (must obey)

### Security / REST

All SEALED endpoints MUST use the wrappers/guards stack:

- `knx_rest_wrap()`
- `knx_rest_require_session()`
- `knx_rest_require_role()`
- `knx_rest_verify_nonce()`
- `knx_rest_response()` / `knx_rest_error()`

**Standard payload only**:
```json
{ 
  "success": true|false, 
  "message": "...", 
  "data": ... 
}
```

**Mutations (POST/PUT/DELETE) ALWAYS require**:
1. Session
2. Role
3. Nonce (action-specific)

`permission_callback` may be permissive ONLY if handler enforces session+role+nonce. (Prefer strict anyway.)

### DB shape-safe

- Assume some columns might not exist on some installs.
- Before using columns like `deleted_at`, `is_operational`, `status`, etc., detect them (SHOW COLUMNS helper or existing helper).
- If missing: do best effort without fatals and return meaningful message.

### Folder architecture

- **REST infra helpers** live in `/inc/core/rest/` (helpers only, no route registering).
- **SEALED resource routes** live in `/inc/core/resources/<resource>/` (routes + handlers).
- **UI internal modules** in `/inc/modules/<module>/` (shortcode + JS + CSS + modal files).

**Do not move files elsewhere.**

### UI + assets

- UI modules are allowed to load assets via echo `<link>` and `<script>` inside shortcodes (project style).
- Do not force WP enqueue if module is already echo-based.
- JS must use toast ONLY as: `knxToast("msg","success|error|info|warning")`
- **NEVER** `knxToast.show(...)` or `window.knxToast.show(...)`

### CSS / Theme bleed

- CSS MUST be scoped under one root container: `.knx-<module>-signed`
- Must neutralize Hello Elementor bleed inside scope:
  - **links**: no underline, no color change on hover/focus/active
  - **buttons**: no theme transforms/filters/shadows/transitions
- No global overrides outside scope.
- **"No hover" requirement**: edit icon, delete icon, cancel buttons in modals, yes delete button.

### Platform constraint

- **Do NOT introduce or depend on `wp_footer` (or fallback).** It broke navbar previously.

---

## 2) Your Workflow (mandatory)

You will run work in **3 phases** for any resource (Hubs, Menus, Categories, Items, Drivers, Customers):

### Phase 1 — Evidence Map (no code changes)

1. Read `current-status.md` (ruleset).
2. Locate the **Cities v2 SEALED files** and summarize their structure as a **"Golden Reference Snapshot"**.
3. Use CodeSearchBox to build a **Legacy Map** per resource:
   - UI module file paths (shortcode, JS, CSS, modals)
   - REST routes used (legacy/v1/v2)
   - DB tables/columns referenced
   - Any old slugs/links (`/edit-.../?id=`), admin-ajax usage, or direct SQL
   - Security risks (missing nonce, role checks, open endpoints)
   - Theme bleed risks (unscoped CSS, global selectors)

**Output of Phase 1 MUST be deterministic and include exact file paths and route strings.**

### Phase 2 — Patch Plan (no code changes)

For each resource, propose a **patch plan** that mirrors Cities v2 SEALED:

- Endpoints to create under `/inc/core/resources/<resource>/`
- Nonce actions to use (names aligned with resource)
- Role matrix (super_admin vs manager) with scope enforcement where needed
- UI changes needed to consume v2 only
- CSS scope + anti-bleed plan
- Temporary alias routes (ONLY if needed to prevent breaking legacy UI)
- Test matrix

Also list **exactly what files will be touched**.

### Phase 3 — Execution (only after explicit approval)

**You will NOT implement Phase 3 until I say:**
> "Ok, execute Phase 3 for \<RESOURCE\>"

When you execute:

- Work in **commit-sized chunks** (small, reversible).
- After each chunk, provide:
  - What changed + why
  - Routes added
  - How to test
  - Confirm no legacy calls remain for that module

---

## 3) Required Search Queries (must run & report)

Run these (and add more if needed). Report results with file paths:

- `register_rest_route(`
- `wp-json/knx`
- `knx/v1`
- `knx/v2`
- `permission_callback`
- `__return_true`
- `wp_footer`
- `edit-`
- `?id=`
- `admin-ajax.php`
- `knxToast.show`
- `window.knxToast.show`
- `knxToast(`
- `/inc/core/resources/knx-cities`
- `/inc/modules/knx-cities`

---

## 4) Output Format (strict)

Respond ONLY with:

### Golden Reference Snapshot (Cities v2 SEALED)

List files and key patterns observed (routes, nonces, wrapper usage, UI calls, CSS scope).

### Legacy Map (by resource)

For each: Hubs, Menus, Categories, Items, Drivers, Customers:

- **UI files**:
- **REST routes**:
- **DB tables/columns**:
- **Legacy dependencies**:
- **Security gaps**:
- **CSS/theme-bleed risks**:

### Patch Plan (by resource)

- Endpoints to add (exact routes)
- Nonces (action names)
- Roles + scope enforcement
- UI changes
- CSS scope plan
- Alias strategy (if required)
- Files to touch

### Test Matrix

- logged out → unauthorized
- wrong role → forbidden
- invalid nonce → forbidden
- happy path
- DB shape-safe (missing columns)
- dependency blocks on delete
- no 404 on `/wp-json/knx/v2/*`

---

## STOP CONDITIONS

**Stop after Phase 2. Do not implement any code until approved.**

If you are unsure about a route name, file location, or nonce action, **STOP** and ask for the file path evidence from CodeSearch results. **Do not guess.**

**If any route base / nonce scheme / loader location is uncertain → STOP and show CodeSearch evidence. Do not guess.**

---

## EVIDENCE & NO-HALLUCINATION RULES

These rules are **mandatory** to prevent hallucinated claims and ensure audit-ready evidence:

1. **Never claim a file was created/modified** unless you provide:
   - Repo-relative path (e.g., `/inc/core/resources/knx-hubs/add-hub.php`)
   - Diff summary (or commit hash if available)

2. **Never output `file:///` URLs.** Always use repo-relative paths.

3. **Evidence requirement**: Every claim must include:
   - Repo-relative file path
   - Exact matched string (route string, fetch URL, nonce action, etc.)
   - If line numbers aren't available, include a unique snippet from the file

4. **Loader first**: Locate how `/inc/core/resources/*` are included/loaded **before** adding new resource files.
   - Do not invent new loader hooks.
   - Show evidence of the existing loader pattern (e.g., `kingdom-nexus.php` or similar).

5. **Nonce scheme**:
   - Payload key: `knx_nonce`
   - Action naming: `knx_<resource>_<verb>`
   - Examples: `knx_hub_add`, `knx_hub_delete`, `knx_hub_operational_toggle`
   - If repo uses a different scheme, **STOP** and show evidence.

6. **Alias routes**: Only create aliases if:
   - Evidence shows legacy UI depends on them
   - Aliases must call v2 handlers internally
   - Aliases must keep standard response format

**Violation of these rules = invalid output. Start over.**

---

## Agent Session Instructions

When pasting this charter into a new agent session:

1. Agent reads this charter
2. Agent confirms understanding of the 3-phase workflow
3. Agent begins Phase 1 (Evidence Map) for the resource you specify
4. Agent presents Phase 2 (Patch Plan) for approval
5. Agent waits for explicit "execute Phase 3" command
6. Agent works in small, reversible chunks with clear reporting

---

**End of Charter**
