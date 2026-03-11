# Castle Fun Center AV Control System

## Project Overview

A PHP/jQuery web application that controls 50+ AV devices (Just Add Power receivers, IR transmitters, WLED lighting) across 9 entertainment zones at Castle Fun Center. Runs on a dedicated private network (192.168.8.0/24 for AV, 192.168.6.0/24 for WLED).

## Architecture

- **Backend**: PHP 7.4+ with cURL for device communication
- **Frontend**: HTML5, CSS3 (Material Design dark theme), jQuery 3.7.1
- **Storage**: File-based (JSON, INI, PHP constants) - no database
- **Devices**: Just Add Power 2G/3G AV-over-IP receivers with HTTP REST API

### Directory Structure

```
/                          Root - landing page, global config
├── shared/                Shared codebase (core PHP, JS, CSS)
│   ├── BaseController.php   AJAX routing, request validation, anti-popping
│   ├── utils.php            Device communication (volume, channel, power, DSP)
│   ├── zones.php            Zone CRUD, atomic writes, config caching
│   ├── settings.php         Settings management UI with backup/restore
│   ├── editini.php          INI/TXT config file editor
│   ├── wled.php             WLED lighting control handler
│   ├── reboot.php           Device reboot script (reads RECEIVERS from zone config)
│   ├── api.php              Legacy shared IR command endpoint
│   ├── site-config.php      Global constants (HOME_URL, ADMIN_URL, API_BASE_PATH)
│   ├── styles.css           Material Design dark theme with glassmorphism
│   └── script.js            Client-side controls, remote, accessibility, lazy loading
├── api/                   Global API endpoints
│   ├── zones.php            REST endpoint - returns zones, specialLinks, settings
│   ├── receiver-status.php  GET endpoint - returns channel, volume, capabilities for a device IP
│   └── devices.php          Aggregates all device info into a single JSON response
├── scripts/               Maintenance and reference scripts
│   ├── health_check.php     Pre-deploy validation (zones, files, JSON, PHP syntax)
│   └── fluxhandlerV2.sh     IR command handler reference (runs ON JAP devices, not server)
├── zone-templates/        Template files for creating new zones
│   ├── config.php, index.php, template.php
│   ├── transmitters.txt, payloads.txt, favorites.ini, WLEDlist.ini
│   └── README.md
├── [zone]/                Zone directories (bowling, bowlingbar, rink, jesters,
│                          facility, outside, dj, multi, all)
│   ├── index.php            Entry point - loads config, BaseController, template
│   ├── config.php           Zone-specific settings (RECEIVERS, TRANSMITTERS, etc.)
│   ├── template.php         Zone UI template
│   ├── api.php              IR remote command handler
│   ├── transmitters.txt     IR blaster device list (CSV: Name, URL)
│   ├── payloads.txt         IR command codes (INI: action=sendir,...)
│   ├── favorites.ini        Favorite channel presets
│   ├── WLEDlist.ini         WLED device IPs
│   ├── settings.php         Alias to ../settings.php with ?zone parameter
│   ├── editini.php          Alias to ../editini.php with ?zone parameter
│   ├── wled.php             Alias to ../wled.php with ?zone parameter
│   ├── saved_volumes.json   Persistent volume state (some zones)
│   └── av_controls.log      Activity log (per-zone)
├── index.html             Password-protected landing page with dynamic zone loading
├── script.js              Root auth/navigation JS (password in landing.js)
├── landing.js             Zone loading, color utilities, LiveCode compat
├── landing.css            Landing page styles (glassmorphism, zone grid)
├── livecode-compat.js     LiveCode browser widget compatibility layer
├── zones.json             Master zone registry (single source of truth)
├── site-config.json       Site-wide config (network, UI, device directory)
├── config.ini             Alert/monitoring config (Slack, email, SMS, webhooks)
├── devices.json           Global device registry (receivers, transmitters, settings)
├── DBconfigs.ini          Infrastructure devices (sensors, WLED, printers, Pi projects)
├── devices.php            Device directory web interface
├── devices.css            Device directory styles
├── status.php             IOT device status checker (HTTP + ping, uses curl_multi)
├── zonemanager.php        Zone management interface (CRUD, reorder, quick links)
├── settings.php           Settings entry point (?zone=X)
├── editini.php            Config file editor entry point (?zone=X)
├── edit.php               Configuration file editor entry point
├── wled.php               WLED control entry point (?zone=X)
├── fix_permissions.sh     File permission setup script
├── .htaccess              Apache security (blocks .ini, .json.lock, .log, backups)
└── logo.png               Castle Fun Center branding
```

### Request Flow

1. User authenticates via client-side password on `index.html`
2. Selects zone from navigation grid (zones loaded from `/api/zones.php`)
3. Zone's `index.php` loads `config.php` + `shared/BaseController.php` + `shared/utils.php`
4. Page renders with lazy-loaded receiver status (async fetch from `/api/receiver-status.php`)
5. AJAX requests route through BaseController to utility functions
6. `utils.php` communicates with AV devices via HTTP API (cURL)

### Key Files

- `zones.json` - Master zone registry (single source of truth for zones)
- `site-config.json` - Site-wide configuration (network, UI, device directory)
- `config.ini` - Alert/monitoring configuration (Slack, email, SMS)
- `devices.json` - Global device directory (receivers, transmitters, settings)
- `DBconfigs.ini` - Infrastructure devices (sensors, Pi projects, printers, WLED)
- `shared/BaseController.php` - AJAX routing, request validation, anti-popping
- `shared/utils.php` - Device communication (volume, channel, power, DSP)
- `shared/zones.php` - Zone CRUD with atomic file writes and caching
- `shared/script.js` - Client-side controls, remote commands, accessibility
- `shared/styles.css` - Material Design dark theme with glassmorphism
- `livecode-compat.js` - LiveCode browser widget compatibility (storage, fetch, keyboard)

## Development Guidelines

### Zone Architecture

Each zone follows a standard pattern. When creating new functionality:
- Put shared logic in `shared/` and have zone files delegate to it
- Zone `config.php` defines constants: RECEIVERS, TRANSMITTERS, MAX_VOLUME, etc.
- Zone `template.php` renders the UI and includes shared JS/CSS
- Zone `index.php` is the thin entry point that wires everything together

### Adding/Modifying Zones

Use the Zone Manager (`zonemanager.php`) or manually:
1. Copy `zone-templates/` to new directory
2. Configure `config.php` with zone-specific receivers and transmitters
3. Add zone entry to `zones.json`

### Configuration Constants (in zone config.php)

- `RECEIVERS` - Array of device name => [ip, show_power, power commands]
- `TRANSMITTERS` - Array of source name => channel number
- `MAX_VOLUME` / `MIN_VOLUME` / `VOLUME_STEP` - Volume limits
- `API_TIMEOUT` - Seconds for device API calls (default: 2)
- `LOG_LEVEL` - debug, info, warning, error
- `LOG_FILE` - Path to zone activity log (default: `__DIR__ . '/av_controls.log'`)
- `HOME_URL` - Home navigation target (default: `/`)
- `REMOTE_CONTROL_COMMANDS` - Whitelist of valid IR remote actions
- `VOLUME_CONTROL_MODELS` - JAP models that support volume control
- `ERROR_MESSAGES` - User-facing error strings for connection/global/remote errors

### Key Backend Functions (shared/utils.php)

- `makeApiCall($method, $deviceIp, $endpoint, $data, $contentType)` - Low-level HTTP API wrapper
- `getCurrentChannel($deviceIp)` / `setChannel($deviceIp, $channel)` - Channel control
- `getCurrentVolume($deviceIp)` / `setVolume($deviceIp, $volume)` - Volume control
- `supportsVolumeControl($deviceIp)` - Check device model for volume support
- `getDeviceModel($deviceIp, $forceRefresh)` - Get model string with caching
- `supportsDspControl($deviceIp)` - Check for DSP support (3G+AVP TX, 3G+WP4 TX)
- `setDspAudioState($deviceIp, $type, $enabled)` - DSP line/HDMI audio control
- `setChannelWithoutPopping($deviceIp, $channel)` - Anti-popping mute/change/unmute sequence
- `generateReceiverForms()` / `generateReceiverForm()` - HTML receiver card generation
- `getTransmittersJson()` - Export TRANSMITTERS as JSON for frontend
- `sanitizeInput($data, $type, $options)` - Input validation (int, ip, string)
- `logMessage($message, $level)` - Logging with level hierarchy
- `loadPayloads($filename)` - Load INI-format IR command file

### Key Frontend Features (shared/script.js)

- Lazy loading of receiver status via `/api/receiver-status.php`
- Channel auto-submit on dropdown change
- Volume control with 300ms debounce
- Power on/off with configurable follow-up commands and delays
- IR remote control with sequential digit sending (1000ms between)
- WLED lighting on/off via zone's `wled.php`
- Accessibility: ARIA announcements, keyboard navigation, screen reader support
- LiveCode compatibility via `window.LiveCodeCompat.fetch`

### Anti-Popping Audio Sequence

The `setChannelWithoutPopping()` function in `utils.php`:
1. Mute volume to 0 (500ms wait)
2. Disable DSP/HDMI/stereo audio outputs (500ms wait)
3. Change channel
4. Wait for signal stabilization (1.5s)
5. Restore all audio outputs and original volume (300ms wait)

### Testing & Validation

Run the health check before deploying:
```bash
php scripts/health_check.php
```

This validates zone configs, required files, JSON syntax, and PHP syntax. Returns non-zero exit code on errors.

### Common Patterns

- **Device API calls**: Use functions from `shared/utils.php` (`setChannel()`, `setVolume()`, `makeApiCall()`)
- **File writes**: Use atomic writes with file locking (see `shared/zones.php`)
- **Anti-popping**: Channel changes mute audio first, change channel, then unmute (handled by BaseController)
- **IR commands**: Pipe payload through `fluxhandlerV2.sh` on the JAP device via CLI endpoint
- **Lazy loading**: Receiver status fetched asynchronously after page render via `/api/receiver-status.php`
- **Config backups**: Automatic before every save, keeps 3 most recent, up to 10 restorable in Settings UI

### Network

- AV devices: `192.168.8.0/24`
- WLED devices: `192.168.6.0/24`
- Infrastructure: `192.168.1.0/24`
- Server: `192.168.8.127`

### Device API Endpoints (on JAP devices at 192.168.8.X)

- `GET /cgi-bin/api/details/channel` - Current channel
- `POST /cgi-bin/api/command/channel` - Set channel
- `GET /cgi-bin/api/details/audio/stereo/volume` - Current volume
- `POST /cgi-bin/api/command/audio/stereo/volume` - Set volume
- `GET /cgi-bin/api/details/device/model` - Device model string
- `POST /cgi-bin/api/command/cli` - Execute CLI command (IR, power, reboot)
- `POST /cgi-bin/api/command/audio/dsp/line` - DSP line audio control
- `POST /cgi-bin/api/command/audio/dsp/hdmi` - DSP HDMI audio control
- `POST /cgi-bin/api/command/hdmi/audio/mute` - Mute HDMI audio
- `POST /cgi-bin/api/command/hdmi/audio/unmute` - Unmute HDMI audio

## Important Notes

- This runs on a **dedicated private network** - security is not a primary concern
- User experience and reliability are the top priorities
- All zone templates should include jQuery 3.7.1 from CDN and `livecode-compat.js`
- The `footer` element must be inside `<body>`, not after `</body>`
- Templates should use consistent header buttons (Home, Settings, Devices) with proper SVG icons
- The `dj` zone is the largest with 17 receivers; `all` zone controls 21 receivers across all zones
- `multi` and `all` zones have additional files: `audio_toggle_handler.php`, `devices.php`
- IR command execution uses fire-and-forget semantics - HTTP errors don't indicate command failure; only DNS/connection-refused means the device is unreachable
