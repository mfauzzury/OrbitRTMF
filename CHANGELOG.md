# Changelog

All notable changes to this project are documented in this file.

## Planned / Future

- Add FAQ icon link to topbar user-manual page, contextualized to the currently open page.
- Add AI Advisor rotator text in topbar to surface system insights and latest user issues.
- Redesign topbar with a darker "PowerBar" concept.
- Build notification module and add topbar notifications dropdown (similar to settings) showing the latest 5 notifications.

## [1.2.4] - 2026-05-17

### Added
- **Relations tab** on the RTMF frontend editor — SVG diagram showing bidirectional page relationships: incoming pages (sky/teal, left) and outgoing pages (violet, right) with labeled arrows. Tab badge shows combined count.
- Incoming links API endpoint (`GET /rtmf-frontends/{id}/incoming-links`) using PostgreSQL jsonb `@>` query to find all pages that link to the current page via action item conditions.

### Changed
- Page-level From/To link fields removed from editor, form requests, and controller — relationships now live exclusively at the form item level via the Condition / Page Link column.
- Feedback status display labels changed to Open / In Progress / Closed (DB values `open` / `reviewed` / `approved` unchanged). Updated across editor, list, dashboard, and catalog tracking views.
- Links column removed from the RTMF frontend list view.
- Item ID shown as small monospace label below the Type dropdown in the form items table for quick reference.
- Cancel / "Back to List" button is now always visible regardless of edit permission.
- Condition column header renamed to "Condition / Page Link".
- Dashboard role cards now show a three-segment stacked progress bar (Closed / In Progress / Open) instead of a single-color bar.
- Page picker selection in condition column now saves to DB immediately on pick.
- `allFrontends` loader now awaited in parallel with page data on mount to eliminate page chip race condition.

### Fixed
- Page picker chips showing empty on first open due to `allFrontends` not yet loaded.
- Selecting a page in the condition picker did not persist to the database.

### Removed
- Dead `frontendById()` function and unused `isAssignedToMe` computed.
- Duplicate `Trash2 as TrashIcon` icon alias — consolidated to single `Trash2`.

## [1.2.3] - 2026-05-16

### Fixed
- Document title now resolves correctly on hard refresh for all routes — `afterEach` defers title set until `site.load()` resolves, preventing stale default "CORRAD Laravel" from appearing.

## [1.2.2] - 2026-05-16

### Fixed
- Restored missing `rtmf.ts` API exports (`listRtmfProjects`, `listRtmfScenarios`, `listRtmfFrontendFeedbacks`, `upsertRtmfFrontendFeedback`, `importRtmfCatalog`, scenario/step/link CRUD, project member CRUD, API endpoint CRUD) lost after pulling from main.

### Changed
- Storefront layout: logo now uses natural proportions matching admin topbar (no forced square).
- Storefront layout: site name and tagline removed from top navbar.
- Storefront layout: Login button styled black with `LogIn` icon.
- Storefront layout: footer pinned to bottom of viewport.
- Storefront layout: content area is now full-width with no constraining box.
- Storefront layout: page title hidden — only page content renders.
- Storefront: document title now resolves from `webfrontTitle`/`siteTitle`, skipping literal `"null"` DB values.
- Login page: removed hardcoded default email and password values from input fields.

## [1.2.1] - 2026-05-16

### Added
- Page Catalog Tracking view (`/admin/catalog-tracking`) — mirrors the RTMF Dashboard with full KPI, module breakdown, review status cards, and role drill-down.

## [1.2.0] - 2026-03-22

### Upgraded
- Laravel 12.54.1 to Laravel 13.1.1 (PHP 8.3+ required).
- PHPUnit 11 to PHPUnit 12.
- Symfony packages from 7.4.x to 8.0.x.
- Vite 7 to Vite 8.0.1, laravel-vite-plugin 2.x to 3.0.0.
- Laravel Tinker 2.x to 3.0.0.

### Changed
- CSRF middleware reference updated from `ValidateCsrfToken` to `PreventRequestForgery` in Sanctum config.
- Added `serializable_classes => false` to cache config for deserialization hardening.
- Updated `CLAUDE.md` project identity to reflect Laravel 13, PHP 8.3+, Vite 8.

## [1.1.0] - 2026-03-11

### Added
- Global admin toast system with typed variants (`success`, `error`, `info`) and shared composable API.
- Global admin confirmation dialog service for destructive/reset actions.
- New reusable UI components for toast and confirm dialog mounting at app level.
- Toast showcase section in Kitchen Sink with live success/info/error triggers.

### Changed
- Admin topbar now supports inline toast presentation and sticky behavior for persistent feedback visibility.
- Admin menu model expanded to support depth-3 hierarchy (`parent > child > grandchild`).
- Sidebar navigation rendering updated for recursive active/open behavior across nested levels.
- `/admin/menus` redesigned to support reorder + hide controls at group/item/child/grandchild levels.
- Compact sidebar mode added and moved into topbar settings dropdown.
- Core action flows updated to use toast + confirm patterns (save, delete, upload, reset/hide-risk actions).

### Backend & Contracts
- `adminMenuPrefs` schema expanded with nested preference fields:
  - `childOrder`
  - `grandchildOrder`
  - `hiddenChildren`
  - `hiddenGrandchildren`
- Backward compatibility maintained for previously stored menu preferences.

### UX Polish
- Refined topbar toast motion, timing, and title/visibility choreography.
- Updated spacing/alignment behavior for site title/profile/topbar elements.

## [1.0.0] - 2026-03-01

### Added
- Initial full-stack CMS foundation with Vue 3 admin app and Express API server.
- Authentication flow, role-based admin capabilities, and core dashboard experience.
- Content management modules for posts, pages, categories, menus, settings, and media.
- Media metadata handling and development support screens/tools.
- Monorepo setup scripts for clean local install and bootstrapping.

### Changed
- Consolidated release for CMS main dashboard, media metadata improvements, and development tooling cleanup.
- UI refinements across early admin screens (login, profile/settings, sidebar/header behavior).

## Historical Notes (Pre-1.0.0)

- Early milestone commits introduced:
  - Base CMS scaffold and API/app wiring.
  - Sidebar/header and settings UX iterations.
  - User CRUD and shared user edit flows.
  - Theme color picker and login UI modernization.

[1.1.0]: https://github.com/mfauzzury/corrad-laravel/releases/tag/v1.1.0
[1.0.0]: https://github.com/mfauzzury/corrad-laravel/releases/tag/v1.0.0
