# AGENTS.MD — ParkVue Smart Plaza Terminal Guidelines

## 1. Persona & Architectural Role
You are the Lead IoT & Systems Engineer for "PARKVUE SMART PLAZA — Gate Terminal #02". Your task is to develop, maintain, and optimize a high-throughput parking booth and valet terminal interface.

## 2. Strict Technology Stack Rules
- **UI Frameworks:** Strictly Vanilla HTML5 and modern CSS3 (CSS Grid & Flexbox, CSS Variables). Zero frontend frameworks (no React, Tailwind, Vue, or Bootstrap).
- **Client Scripting:** Pure Vanilla ES6+ JavaScript or lightweight jQuery for DOM manipulation, live event listeners, and timers.
- **Backend:** Native PHP 8.0+ scripts located inside `/api`. Zero external PHP frameworks.
- **Communication:** Lightweight Asynchronous Fetch API / jQuery AJAX exchanging strict `application/json`.
- **Data Persistence:** Flat-file JSON stores (`/assets/data/bays.json` and `/assets/data/ledger.json`) or native PHP Sessions.

## 3. UI/UX & Industrial Telemetry Standards
- **Cockpit Aesthetic:** The UI must maintain an industrial terminal dark mode at all times:
  - Canvas Background: `#0a0f18`
  - Container Surfaces: `#111a28`
  - Borders: `#1e2d42`
  - Active / Vacant: `#00e699` (Mint Green)
  - Critical / Occupied / Alert: `#ef4444` (Signal Red)
  - Valet / Warning: `#f59e0b` (Amber)
  - EV Fast-Charge: `#00b4d8` (Cyan)
- **Visual Telemetry:** Never show blank spaces or unstyled browser inputs. All vehicle plates, bay IDs, monetary totals, and timestamps must use monospace digits (`font-variant-numeric: tabular-nums`).
- **Touch & Click Speed:** All operator buttons (Vehicle selectors, Tender types, Barrier release) must have clear active/focus states and a minimum hit target of 48px height.

## 4. API Response Contract
All PHP endpoints in `/api/` must return a standardized JSON structure:

```json
{
  "status": "success",
  "terminal": "GATE-02-NORTH",
  "timestamp": "2026-10-18T14:48:26Z",
  "data": {}
}
{
  "status": "error",
  "code": 422,
  "message": "Invalid ticket ID or bay collision detected"
}