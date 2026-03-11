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
├── shared/                Shared codebase (BaseController, utils, styles, JS)
├── zone-templates/        Template files for creating new zones
├── api/                   Global API endpoints (zones, devices, receiver-status)
├── scripts/               Maintenance scripts (health_check.php)
├── [zone]/                Zone directories (bowling, bowlingbar, rink, jesters,
│                          facility, outside, dj, multi, all)
│   ├── index.php          Entry point - loads config, BaseController, template
│   ├── config.php         Zone-specific settings (RECEIVERS, TRANSMITTERS, etc.)
│   ├── template.php       Zone UI template
│   ├── api.php            IR remote command handler (zones with IR blasters)
│   ├── transmitters.txt   IR blaster device list (CSV: Name, URL)
│   ├── payloads.txt       IR command codes (INI: action=sendir,...)
│   ├── favorites.ini      Favorite channel presets
│   └── WLEDlist.ini       WLED device IPs
```

### Request Flow

1. User authenticates via client-side password on `index.html`
2. Selects zone from navigation grid (zones loaded from `/api/zones.php`)
3. Zone's `index.php` loads `config.php` + `shared/BaseController.php` + `shared/utils.php`
4. AJAX requests route through BaseController to utility functions
5. `utils.php` communicates with AV devices via HTTP API (cURL)

### Key Files

- `zones.json` - Master zone registry (single source of truth for zones)
- `site-config.json` - Site-wide configuration (network, UI, device directory)
- `config.ini` - Alert/monitoring configuration (Slack, email, SMS)
- `shared/BaseController.php` - AJAX routing, request validation, anti-popping
- `shared/utils.php` - Device communication (volume, channel, power, DSP)
- `shared/script.js` - Client-side controls, remote commands, accessibility
- `shared/styles.css` - Material Design dark theme with glassmorphism

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
- `VOLUME_CONTROL_MODELS` - JAP models that support volume

### Testing & Validation

Run the health check before deploying:
```bash
php scripts/health_check.php
```

This validates zone configs, required files, JSON syntax, and PHP syntax.

### Common Patterns

- **Device API calls**: Use functions from `shared/utils.php` (`setChannel()`, `setVolume()`, `sendCliCommand()`)
- **File writes**: Use atomic writes with file locking (see `shared/zones.php`)
- **Anti-popping**: Channel changes mute audio first, change channel, then unmute (handled by BaseController)
- **IR commands**: Pipe payload through `fluxhandlerV2.sh` on the JAP device via CLI endpoint

### Network

- AV devices: `192.168.8.0/24`
- WLED devices: `192.168.6.0/24`
- Infrastructure: `192.168.1.0/24`
- Server: `192.168.8.127`

## Important Notes

- This runs on a **dedicated private network** - security is not a primary concern
- User experience and reliability are the top priorities
- All zone templates should include jQuery 3.7.1 from CDN and `livecode-compat.js`
- The `footer` element must be inside `<body>`, not after `</body>`
- Templates should use consistent header buttons (Home, Settings, Devices) with proper SVG icons
