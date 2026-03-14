# Improvement Proposals - Castle Fun Center AV Control System

This document catalogs potential improvements discovered during a comprehensive codebase review on 2026-03-14. Items are organized by priority and category.

---

## High Priority

### 1. Consolidate API_TIMEOUT Default Values

**Current state:** `shared/utils.php` defaults `API_TIMEOUT` to `5` seconds, but zones that load settings from `devices.json` (multi, facility, outside) may default to `2`. Static zone configs use varying values (1 or 2). This inconsistency can cause confusion.

**Proposal:** Standardize on a single documented default (recommend `2` seconds since most zones use it) and update the fallback in `shared/utils.php` to match.

**Files affected:** `shared/utils.php` (line 14), zone `config.php` files

---

### 2. HOME_URL Default Mismatch

**Current state:** `shared/utils.php` defaults `HOME_URL` to `http://localhost`, but nearly every zone overrides it to `/`. The `http://localhost` fallback could cause unexpected navigation on the actual server if a zone config accidentally omits it.

**Proposal:** Change the default in `shared/utils.php` to `/` to match the actual deployment intent.

**Files affected:** `shared/utils.php` (line 19)

---

### 3. Remove Deprecated DSP Wrapper Functions

**Current state:** `shared/utils.php` contains deprecated single-purpose wrappers (`disableDspLineAudio`, `enableDspLineAudio`, `disableDspHdmiAudio`, `enableDspHdmiAudio`) that simply call `setDspAudioState()` with hardcoded parameters. These are only called internally by `setChannelWithoutPopping()`.

**Proposal:** Replace calls to deprecated wrappers with direct `setDspAudioState()` calls and remove the deprecated functions.

**Files affected:** `shared/utils.php`

---

### 4. Multi Zone Config Fragility (Regex Parsing)

**Current state:** The `multi/config.php` uses regex to parse PHP source files to extract RECEIVERS arrays from other zone configs (lines 44-51). This is inherently fragile - any formatting change to a zone's config.php (e.g., multi-line values, comments, different quoting) could break extraction.

**Proposal:** Consider one of these alternatives:
- **Option A:** Have each zone's config.php also export a JSON sidecar file that multi/config.php reads instead of parsing PHP source
- **Option B:** Create a shared function in `shared/zones.php` that safely loads and returns a zone's RECEIVERS constant by requiring the file in an isolated scope
- **Option C:** Move receiver definitions to `devices.json` (already partially done for transmitters) so all zones read from the same JSON source

**Files affected:** `multi/config.php`, potentially all zone `config.php` files

---

## Medium Priority

### 5. Zone Template Completeness

**Current state:** The `zone-templates/` directory does not include several files that all deployed zones have: `api.php`, `settings.php`, `editini.php`, `wled.php`, `logo.gif`, `logo.png`. When creating a new zone from templates, these files must be added manually or by the Zone Manager.

**Proposal:** Either add these standard files to `zone-templates/` or ensure `createZoneDirectory()` in `shared/zones.php` generates them all. Currently `createDefaultZoneFiles()` creates some but not the alias files.

**Files affected:** `zone-templates/`, `shared/zones.php`

---

### 6. Centralize Receiver Definitions in devices.json

**Current state:** Receiver definitions are duplicated across zone config files. The `dj` and `all` zones have identical 24-receiver lists. Changes to receiver IPs or power configurations require updating multiple files.

**Proposal:** Extend `devices.json` to be the single source of truth for receivers (it already handles transmitters for some zones). Zone configs would reference device IDs or group names instead of duplicating full receiver arrays. The `multi` zone already demonstrates a dynamic approach, but `dj` and `all` still use static duplication.

**Files affected:** `devices.json`, all zone `config.php` files, `shared/utils.php`

---

### 7. Add Server-Side Session Authentication

**Current state:** Authentication is client-side only (password check in `landing.js`). While the system runs on a private network, any direct URL access bypasses the password entirely.

**Proposal:** Add lightweight PHP session-based authentication. On password submission, set a session cookie. Zone pages check for valid session before rendering. This doesn't need to be heavy security - just prevents accidental access to control pages from bookmarks or shared links without going through the landing page.

**Files affected:** `index.html`, new `shared/auth.php`, zone `index.php` files

---

### 8. Health Check Coverage Gaps

**Current state:** `scripts/health_check.php` validates zone configs, files, and JSON/PHP syntax. It does not validate:
- That receiver IPs are in the expected subnet (192.168.8.x)
- That `dj` and `all` zone receiver lists match
- That transmitter channel numbers don't conflict
- That WLED IPs are in the correct subnet (192.168.6.x)
- That referenced files in INI configs actually exist

**Proposal:** Extend health_check.php with additional validation rules for network consistency and cross-zone coherence.

**Files affected:** `scripts/health_check.php`

---

### 9. Standardize Logging Across Zones

**Current state:** Log levels vary by zone (`error` for most, `info` for rink and jesters). Some zones don't generate `av_controls.log` at all (rink has no log file present). There's no centralized log viewer or rotation.

**Proposal:**
- Standardize log levels to `error` for production (allow override via a global setting)
- Add a simple log viewer accessible from the Zone Manager or Settings UI
- Implement log rotation (e.g., keep last 7 days or limit file size to 1MB)

**Files affected:** `shared/utils.php`, zone `config.php` files, potentially new log viewer

---

### 10. Bulk Operations for bowlingbar

**Current state:** The `bowlingbar` zone has a `bulk.php` file for bulk input switching. This is a useful feature that other large zones (dj, all) could benefit from but don't have.

**Proposal:** Generalize `bulk.php` into a shared component available to any zone, triggered from the zone template or settings.

**Files affected:** `bowlingbar/bulk.php`, new `shared/bulk.php`, zone templates

---

## Low Priority

### 11. Remove Archive/ZIP Files from Repository

**Current state:** `bowlingbar/bowlingbar.zip` and `all/avcon1.zip` are checked into the repository. These appear to be backup archives that don't belong in version control.

**Proposal:** Add `*.zip` to `.gitignore` and remove these files from the repository.

**Files affected:** `.gitignore`, `bowlingbar/bowlingbar.zip`, `all/avcon1.zip`

---

### 12. Consolidate Device Directory Pages

**Current state:** There are multiple device directory interfaces: `devices.php` (PHP-based), `device-directory.html` (standalone HTML), and `multi/devices.php` (zone-specific). This creates maintenance overhead.

**Proposal:** Consolidate into a single device directory implementation. If the HTML version is preferred for speed, have `devices.php` redirect to it. Remove the redundant version.

**Files affected:** `devices.php`, `device-directory.html`, `multi/devices.php`

---

### 13. Add Receiver Status Caching

**Current state:** Every page load triggers individual HTTP requests to each receiver via `/api/receiver-status.php` for current channel, volume, and model info. For zones with 24 receivers, this means 24+ sequential or parallel API calls.

**Proposal:** Implement short-lived server-side caching (5-10 seconds) for receiver status. Use a shared cache file or APCu. This would dramatically speed up page loads in large zones and reduce load on the AV devices.

**Files affected:** `api/receiver-status.php`, potentially new `shared/cache.php`

---

### 14. Add WLED Preset Support

**Current state:** WLED control is limited to on/off. WLED devices support presets, colors, brightness, and effects via their JSON API.

**Proposal:** Extend `shared/wled.php` and the frontend to support:
- Brightness control (slider)
- Color selection (color picker or presets)
- Effect selection (from WLED's built-in effects)
- Named presets per zone (stored in `WLEDlist.ini` or a new `wled-presets.json`)

**Files affected:** `shared/wled.php`, `shared/script.js`, zone templates

---

### 15. Automated Testing

**Current state:** No automated test suite exists. Validation is limited to the health check script (syntax and config validation only).

**Proposal:** Add basic PHPUnit tests for critical paths:
- `shared/utils.php` functions (sanitizeInput, model detection, volume control models)
- `shared/zones.php` CRUD operations (using temp files)
- `shared/BaseController.php` request routing (mock API calls)
- API endpoint response format validation

This would catch regressions when modifying shared code.

**Files affected:** New `tests/` directory

---

### 16. Frontend Build Process

**Current state:** JavaScript and CSS are served as raw files with no minification, bundling, or versioning. Browser caching may serve stale assets after updates.

**Proposal:** Add cache-busting query parameters based on file modification time (e.g., `script.js?v=1710432000`). This is a minimal change that doesn't require a build tool. Template files would use `filemtime()` to generate the version parameter.

**Files affected:** Zone `template.php` files, `index.html`

---

### 17. Consistent Power Command Configuration

**Current state:** Power command fields are inconsistent across zones. Some receivers have `power_on_command` and `power_off_command`, others have `power_off_pre_command` with delays, and others have none. The CLAUDE.md documents `power_off_command` as a valid key, but many zones don't use it (relying on default CEC behavior instead).

**Proposal:** Document the complete power command configuration matrix with examples for each TV/display model in use. Add validation in health_check.php to warn about incomplete or inconsistent power configurations.

**Files affected:** `CLAUDE.md`, `scripts/health_check.php`

---

### 18. Mobile-Responsive Remote Control

**Current state:** The virtual remote control in `shared/script.js` uses a standard HTML layout. On mobile devices, the number pad and navigation buttons may be difficult to tap accurately.

**Proposal:** Add dedicated mobile-friendly CSS for the remote control section with larger touch targets (minimum 44px), better spacing, and a layout optimized for portrait orientation.

**Files affected:** `shared/styles.css`, potentially zone templates

---

## Documentation Improvements Made (2026-03-14)

The following inaccuracies were corrected in this review:

### CLAUDE.md Fixes
- Fixed `API_TIMEOUT` default documentation (was `2`, actual fallback in utils.php is `5`)
- Fixed `HOME_URL` default documentation (was `/`, actual fallback in utils.php is `http://localhost`)
- Corrected which zones have `audio_toggle_handler.php` (dj, multi, and all -- not just multi and all)
- Corrected that only `multi` zone has `devices.php` (not both multi and all)
- Added `bowlingbar/bulk.php` to documentation
- Added missing root files: `device-directory.html`, `README.md`, `USER_GUIDE.md`, `.gitignore`
- Added missing per-zone files: `logo.gif`, `logo.png`, `reboot.php` (in applicable zones)
- Removed `saved_volumes.json` from zone file listing (file does not currently exist in any zone)
- Added note about multi zone's dynamic receiver aggregation behavior

### USER_GUIDE.md Fixes
- Fixed Multi zone receiver count from "2" to "Dynamic" (aggregates from all other zones)
- Fixed ALL zone receiver count from "21 total" to "24 total"
