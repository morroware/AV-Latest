# Castle Fun Center AV Control System

A centralized, password-protected web-based audio-visual control system designed for Castle Fun Center. This system manages AV equipment across multiple entertainment zones including bowling lanes, a roller rink, arcade areas, DJ booth, and outdoor spaces. Each zone features dedicated controls for receiver management, IR remote functionality, power management, volume control, and smart lighting integration.

---

## Table of Contents

- [System Overview](#system-overview)
- [Project Structure](#project-structure)
- [Features](#features)
- [Architecture](#architecture)
- [Hardware Integration](#hardware-integration)
- [Zone Descriptions](#zone-descriptions)
- [Setup Instructions](#setup-instructions)
- [Configuration Guide](#configuration-guide)
- [Usage Guide](#usage-guide)
- [Authentication and Security](#authentication-and-security)
- [API Reference](#api-reference)
- [Alert & Monitoring System](#alert--monitoring-system)
- [Troubleshooting](#troubleshooting)
- [Maintenance](#maintenance)

---

## System Overview

The Castle Fun Center AV Control System is a production-grade web application that provides centralized control over 50+ AV devices distributed across 9 entertainment zones. The system is designed for reliability and ease of use by venue staff.

### Key Capabilities

- **Multi-Zone Control**: 9 independent zones with tailored interfaces
- **Bulk Operations**: Control all zones simultaneously or select multiple zones
- **Real-time Feedback**: Instant status updates with toast notifications and visual states
- **Smart Lighting**: WLED-compatible addressable LED control per zone
- **IR Remote Emulation**: Full cable box and media device control with number pad
- **Power Management**: CEC-based display power control with configurable sequences
- **Anti-Popping Audio**: Intelligent audio muting during channel changes with DSP support
- **Persistent Settings**: Volume and configuration persistence across sessions
- **Quick Links**: Configurable special navigation links (Dashboard, OSD, etc.)
- **Dynamic Zone Loading**: Zones loaded from API with fallback support
- **Device Directory**: Aggregated view of all AV, WLED, and infrastructure devices
- **Device Status Monitoring**: IOT status checker with HTTP and ping fallback
- **Device Reboot**: Bulk reboot capability for zone receivers
- **LiveCode Compatibility**: Browser widget support with storage, fetch, and keyboard fallbacks

### Technology Stack

| Component | Technology |
|-----------|------------|
| Backend | PHP 7.4+ with cURL |
| Frontend | HTML5, CSS3 (Material Design), jQuery 3.7.1 |
| Storage | JSON, INI, PHP constants (file-based) |
| Network | HTTP API over private 192.168.8.0/24 network |
| Lighting | WLED JSON API on 192.168.6.0/24 network |
| Fonts | Inter (Google Fonts) |
| Compatibility | LiveCode browser widget support via livecode-compat.js |

---

## Project Structure

```
AV-system/
├── index.html                 # Password-protected landing page with dynamic zone loading
├── script.js                  # Authentication, navigation logic, Ctrl+double-click handling
├── landing.js                 # Zone loading, color utilities, LiveCode widget compat
├── landing.css                # Landing page styles (glassmorphism, zone grid)
├── livecode-compat.js         # LiveCode browser widget compatibility layer
├── logo.png                   # Castle Fun Center branding
│
├── zones.json                 # Master zone configuration registry (single source of truth)
├── site-config.json           # Site-wide config (network, UI, device directory categories)
├── config.ini                 # System-wide alerts & webhooks configuration
├── devices.json               # Global device registry (receivers, transmitters, settings)
├── DBconfigs.ini              # Infrastructure devices (sensors, WLED, printers, Pi projects)
├── .htaccess                  # Apache security configuration
│
├── zonemanager.php            # Zone management interface (add/edit/delete/duplicate/reorder)
├── settings.php               # Settings entry point (?zone=X)
├── editini.php                # Config file editor entry point (?zone=X)
├── edit.php                   # Configuration file editor entry point
├── wled.php                   # WLED control entry point (?zone=X)
├── devices.php                # Device directory web interface (grouped by category)
├── devices.css                # Device directory styles
├── status.php                 # IOT device status checker (HTTP + ping, curl_multi batch)
├── fix_permissions.sh         # File permission setup script
│
├── api/
│   ├── zones.php              # REST API - zone configuration with directory validation
│   ├── receiver-status.php    # REST API - receiver channel/volume/capabilities (GET ?ip=X)
│   └── devices.php            # REST API - aggregated device directory from all sources
│
├── shared/                    # Shared codebase (core functionality)
│   ├── BaseController.php     # AJAX request routing, validation, anti-popping orchestration
│   ├── utils.php              # Device API communication, volume/channel/DSP, form generation
│   ├── zones.php              # Zone CRUD operations with atomic writes and config caching
│   ├── settings.php           # Settings management UI with backup/restore (up to 10 backups)
│   ├── editini.php            # INI/TXT configuration file editor
│   ├── wled.php               # WLED lighting control handler (bulk on/off per zone)
│   ├── reboot.php             # Device reboot script (reads RECEIVERS from zone config)
│   ├── api.php                # Legacy shared IR command endpoint with SSRF protection
│   ├── site-config.php        # Site-wide PHP constants (HOME_URL, ADMIN_URL, API_BASE_PATH)
│   ├── styles.css             # Material Design dark theme with glassmorphism effects
│   └── script.js              # Shared JS - receiver controls, remote, WLED, accessibility
│
├── zone-templates/            # Template files for new zone creation
│   ├── README.md              # Zone template instructions
│   ├── config.php             # Zone configuration template with commented examples
│   ├── index.php              # Zone entry point template
│   ├── template.php           # Zone UI template (header, receivers, remote, WLED footer)
│   ├── transmitters.txt       # IR blaster device list template
│   ├── payloads.txt           # IR command codes template
│   ├── favorites.ini          # Favorite channel mappings template
│   └── WLEDlist.ini           # WLED device IP addresses template
│
├── scripts/                   # Maintenance and reference scripts
│   ├── health_check.php       # Pre-deploy validation (zones, files, JSON/PHP syntax)
│   └── fluxhandlerV2.sh       # IR command handler reference (runs on JAP devices)
│
└── [zone]/                    # Zone directories (9 total)
    ├── index.php              # Zone entry point (handles AJAX via BaseController)
    ├── config.php             # Zone-specific configuration (receivers, transmitters, limits)
    ├── template.php           # Zone UI template
    ├── api.php                # IR remote command handler
    ├── transmitters.txt       # IR blaster device list (CSV: Name, URL)
    ├── payloads.txt           # IR command codes (sendir/hex format)
    ├── favorites.ini          # Favorite channel presets
    ├── WLEDlist.ini           # WLED device IP addresses
    ├── settings.php           # Alias to ../settings.php with ?zone parameter
    ├── editini.php            # Alias to ../editini.php with ?zone parameter
    ├── wled.php               # Alias to ../wled.php with ?zone parameter
    ├── saved_volumes.json     # Persistent volume state (some zones)
    └── av_controls.log        # Zone activity log

Zone Directories:
├── bowling/                   # Bowling Lanes (4 receivers)
├── bowlingbar/                # Bowling Bar (10 receivers)
├── rink/                      # Roller Rink (2 receivers)
├── jesters/                   # Jesters Arcade Area (3 receivers)
├── facility/                  # Facility-wide Controls (1 receiver)
├── outside/                   # Outdoor Area (1 receiver)
├── dj/                        # DJ Booth (17 receivers - largest zone)
├── multi/                     # Multi-zone Selection Control (+audio_toggle_handler.php, devices.php)
└── all/                       # ALL Zones Simultaneous Control (+audio_toggle_handler.php)
```

---

## Features

### Core Features

| Feature | Description |
|---------|-------------|
| **Multi-Zone Control** | 9 independent zones with customized interfaces |
| **Receiver Management** | Channel and volume control for all AV receivers |
| **IR Remote Control** | Full cable box emulation (power, guide, navigation, numbers, channel up/down) |
| **Power Management** | CEC-based TV/display power on/off with configurable follow-up commands |
| **Volume Control** | Per-receiver volume with model-based support detection and 300ms debounce |
| **Anti-Popping Audio** | Mutes HDMI/stereo/DSP audio during channel changes to prevent pops |
| **DSP Audio Control** | Advanced audio control for 3G+AVP TX and 3G+WP4 TX devices |
| **WLED Integration** | Smart addressable LED lighting control per zone |
| **Favorite Channels** | Quick-access channel presets per zone (via INI files) |
| **Bulk Operations** | Control multiple receivers/zones simultaneously |
| **Device Reboot** | Bulk reboot capability for all receivers in a zone |
| **Lazy Loading** | Receiver status loaded asynchronously after page render |

### Management Features

| Feature | Description |
|---------|-------------|
| **Zone Manager** | Add, edit, duplicate, reorder, and remove zones with drag-and-drop |
| **Quick Links Manager** | Add, edit, and manage special navigation links (Dashboard, OSD) |
| **Settings Editor** | Web-based receiver/transmitter configuration with validation |
| **Config File Editor** | Edit INI files (transmitters, favorites, WLED, payloads) |
| **Device Directory** | Aggregated view of all devices grouped by category |
| **IOT Status Checker** | HTTP and ping-based device monitoring with curl_multi batch checks |
| **Automatic Backups** | Configuration backups before every save (keeps 3 most recent) |
| **Backup Restoration** | Restore from any of the 10 most recent backups |
| **Atomic File Writes** | File locking with temp files prevents corruption during saves |
| **Zone Templates** | Pre-configured templates for creating new zones |
| **Configuration Caching** | In-memory caching reduces file reads |

### User Experience

| Feature | Description |
|---------|-------------|
| **Responsive Design** | Works on desktop, tablet, and mobile with touch optimization (48px min targets) |
| **Material Design** | Dark theme with glassmorphism and gradient effects |
| **Modern UI** | Ambient glow effects, hover animations, loading spinners |
| **Session Persistence** | Authentication persists until midnight (localStorage) |
| **Real-time Feedback** | Toast notifications, visual updating states, success/error indicators |
| **Keyboard Navigation** | Full keyboard accessibility support with tabindex |
| **Screen Reader Support** | ARIA labels, live regions, and announcements |
| **High Contrast Mode** | Respects `prefers-contrast: high` media query |
| **Reduced Motion** | Respects `prefers-reduced-motion` media query |
| **Password Visibility Toggle** | Show/hide password with accessible button |
| **Attempt Limiting** | 10 failed password attempts locks form until refresh |
| **Dynamic Zone Loading** | Zones loaded via API with fallback to hardcoded list |
| **Ctrl+Click Shortcuts** | Ctrl+Click on logo opens settings, Ctrl+double-click returns home |
| **LiveCode Compatibility** | Full support for LiveCode browser widget environments |

### UI Components

| Component | Description |
|-----------|-------------|
| **Navigation Grid** | 2-column responsive button grid for zone selection |
| **Receiver Cards** | Individual cards per receiver with channel/volume/power controls |
| **Virtual Remote** | Full IR remote interface with navigation pad and number pad |
| **Transmitter Selector** | Dropdown to select IR transmitter for remote commands |
| **Favorite Channels** | Quick-access dropdown for preset channels |
| **WLED Footer** | Zone-specific lighting on/off buttons |
| **Loading States** | Spinner animations and disabled states during operations |
| **Error Messages** | Clear error display with shake animation on failures |
| **Channel Overlay** | On-screen number pad for channel entry (LiveCode fallback) |

---

## Architecture

### Request Flow

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│   Web Browser   │────▶│   index.html     │────▶│  Zone Selection │
│                 │     │  (Password Gate) │     │                 │
└─────────────────┘     └──────────────────┘     └────────┬────────┘
                                                          │
                              ┌────────────────┐          │
                              │ /api/zones.php │◀─────────┘
                              └───────┬────────┘
                                      │ JSON (zones, links)
                                      ▼
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│  JSON Response  │◀────│   utils.php      │◀────│  /[zone]/index  │
│                 │     │  (API Calls)     │     │  (Controller)   │
└─────────────────┘     └────────┬─────────┘     └─────────────────┘
                                 │                        ▲
                                 │                        │ Lazy load
                                 ▼               ┌───────────────────┐
                        ┌──────────────────┐     │ /api/receiver-    │
                        │   AV Devices     │────▶│  status.php       │
                        │  (192.168.8.x)   │     └───────────────────┘
                        └──────────────────┘
```

### Component Responsibilities

| Component | Responsibility |
|-----------|---------------|
| `index.html` | Password gate, zone navigation, dynamic zone loading from API |
| `script.js` (root) | Authentication, session management, Ctrl+click shortcuts |
| `landing.js` | Zone button rendering, color utilities, LiveCode compat |
| `livecode-compat.js` | Storage adapter, fetch wrapper, keyboard helper, channel buffer |
| `api/zones.php` | REST API for zone configuration, validates zone directories |
| `api/receiver-status.php` | REST API for individual receiver status (channel, volume, capabilities) |
| `api/devices.php` | REST API aggregating devices from zone configs, devices.json, DBconfigs.ini |
| `BaseController.php` | AJAX routing, request validation, response formatting, anti-popping |
| `utils.php` | Device communication, volume/channel control, DSP audio, input validation |
| `zones.php` | Zone CRUD operations, atomic file writes, configuration caching |
| `[zone]/config.php` | Zone-specific settings (receivers, transmitters, limits) |
| `[zone]/template.php` | Zone UI rendering with receiver forms and remote control |
| `shared/script.js` | Client-side controls, accessibility, debounced volume, remote commands |
| `status.php` | IOT device monitoring with HTTP + ping fallback and curl_multi |
| `devices.php` (root) | Device directory web interface with category grouping and search |
| `shared/reboot.php` | Bulk device reboot using zone RECEIVERS config |

### Data Flow

1. User authenticates via password on landing page
2. Session stored in localStorage with daily expiration (midnight)
3. Zones loaded dynamically from `/api/zones.php` with timeout and fallback
4. User selects zone from navigation grid
5. Zone page renders immediately; receiver status lazy-loaded via `/api/receiver-status.php`
6. User actions trigger AJAX requests to zone controller
7. Controller validates input and calls appropriate utility functions
8. Utility functions communicate with devices via HTTP API
9. Response returned to UI with toast notifications and visual feedback

### File Locking Strategy

The system uses atomic writes with file locking to prevent data corruption:

1. Acquire exclusive lock on `.lock` file (5-second timeout, 100ms retry intervals)
2. Write data to temporary file (`.tmp.[pid]`)
3. Atomic rename of temp file to target file
4. Release lock and clean up

---

## Hardware Integration

### AV Receivers/Processors

The system is built specifically for **Just Add Power (JAP) 2G/3G series** AV over IP devices. These devices expose a proprietary HTTP REST API that this system uses for control.

> **Note**: This is NOT a generic "Crestron-compatible" system. While JAP devices can integrate with Crestron control systems, this application uses JAP's proprietary API. Other AV over IP devices would require code modifications to work.

**Supported JAP Models:**

| Model | Volume Support | DSP Support | Features |
|-------|---------------|-------------|----------|
| 3G+4+ TX | Yes | No | Full control |
| 3G+AVP RX | Yes | No | Full control |
| 3G+AVP TX | Yes | Yes | DSP line/HDMI audio control |
| 3G+WP4 TX | Yes | Yes | DSP line/HDMI audio control |
| 2G/3G SX | Yes | No | Basic control |

**Network**: All receivers on 192.168.8.0/24

**Adding Support for Other JAP Models:**

To add volume control support for additional JAP models, add the model string to `VOLUME_CONTROL_MODELS` in:
- `zone-templates/config.php` (for new zones)
- Each zone's `config.php` (for existing zones)

### IR Transmitters (Blasters)

Just Add Power IR blasters with HTTP API:
- Support for SENDIR format IR codes
- Support for Pronto hex format IR codes
- Multiple channels per transmitter (1-10)
- Commands executed via JAP's `fluxhandlerV2.sh` script
- Fire-and-forget semantics: HTTP errors don't indicate command failure

#### How `fluxhandlerV2.sh` works in this project

`fluxhandlerV2.sh` is the command adapter that sits between this PHP app and the JAP device's IR serial interface.

**How this app uses it (request flow):**
1. UI action (for example, Power/Volume/Input) maps to an IR payload in `payloads.txt`.
2. API code wraps that payload in a shell pipeline:
   - `echo "<payload>" | ./fluxhandlerV2.sh`
3. The payload is sent to the JAP device `command/cli` endpoint.
4. On the JAP side, `fluxhandlerV2.sh` reads one input line at a time and decides what to do.

**What `fluxhandlerV2.sh` does with input:**
- `sendir,...` input: normalizes the sendir payload (including repeat-count adjustment) and transmits IR.
- `getdevices`: returns a static device list response (`ETHERNET`, `IR`, `endlistdevices`).
- `getversion`: returns the script version string (`FluxCapacitor_v2`).
- `get_NET`: returns IP/mask/gateway from runtime network variables.
- `get_IR` / `stopir`: returns expected IR status response strings.
- Raw hex IR input: forwards directly for transmission if format is valid.
- Unknown/invalid input: returns `ERR_001`.

**IR transport details:**
- For commands that need transmit, the script writes to `/dev/ttyS0` via `microcom` (`115200` baud).
- It prints command responses back to stdout with `\r` line endings, which is what the API caller receives.

**Operational requirement:**
- `fluxhandlerV2.sh` must exist and be executable on the target JAP host, because all zone API handlers in this repo call that exact script name.

### Input Sources

| Source | Description |
|--------|-------------|
| Cable Box 1-3 | Cable TV receivers (Attic TX 1-3) |
| Apple TV | Streaming device |
| RockBot Audio | Background music system |
| Wireless Mic TX | Microphone transmitter |
| Mobile Video TX | Portable video source |
| Mobile Audio TX | Portable audio source |
| Unifi Signage | Digital signage system |
| Trivia | Trivia system source |

### Smart Lighting (WLED)

WLED-compatible addressable LED controllers:
- Network: 192.168.6.0/24
- Protocol: JSON API (`/json/state`)
- Per-zone device lists in `WLEDlist.ini`
- Bulk on/off operations
- 3-second timeout per device with 2-second connection timeout
- Failure reporting with affected device list

---

## Zone Descriptions

| Zone | ID | Receivers | Description |
|------|-----|-----------|-------------|
| **Bowling Lanes** | `bowling` | 4 | NeoVerse displays + Bowling Music receiver |
| **Bowling Bar** | `bowlingbar` | 10 | Bar area TVs, NeoVerse displays, dining area, billiards |
| **Roller Rink** | `rink` | 2 | Roller rink video/audio system |
| **Jesters** | `jesters` | 3 | Arcade and entertainment area |
| **Facility** | `facility` | 1 | Facility-wide control |
| **Outside** | `outside` | 1 | Outdoor displays and audio |
| **DJ Booth** | `dj` | 17 | Main entertainment control hub (largest zone) |
| **Multi** | `multi` | 2 | Select multiple zones for batch control |
| **ALL** | `all` | 21 | Control all zones simultaneously |

### Special Zones

- **ALL Zone**: Sends commands to all 21 receivers across all zones. Includes `audio_toggle_handler.php` for bulk audio toggling.
- **Multi Zone**: Allows selecting specific zones for batch operations. Includes `audio_toggle_handler.php` and zone-specific `devices.php`.
- **DJ Zone**: Central control hub with 17 receivers for main event management.

### Quick Links

| Link | ID | Description |
|------|-----|-------------|
| **Dashboard** | `dashboard` | System monitoring dashboard |
| **OSD** | `osd` | On-screen display controls |

---

## Setup Instructions

### Requirements

**Server:**
- PHP 7.4+ with extensions: `curl`, `json`
- Web server (Apache/Nginx) with PHP support
- Write permissions on zone directories
- POSIX functions for file ownership info (optional)

**Network:**
- Access to AV devices on 192.168.8.0/24 network
- Access to WLED devices on 192.168.6.0/24 network
- IR transmitter devices at configured IPs
- CEC-enabled displays for power control

### Installation

1. **Clone the repository:**
   ```bash
   git clone [repository_url] /var/www/html/AV-system
   cd /var/www/html/AV-system
   ```

2. **Set directory permissions:**
   ```bash
   # Use the included script:
   bash fix_permissions.sh

   # Or manually:
   chmod -R 755 .
   chmod -R 777 */  # Allow writes to zone directories
   chmod 666 zones.json  # Allow zone configuration updates
   ```

3. **Configure web server** (Apache example):
   ```apache
   <Directory /var/www/html/AV-system>
       AllowOverride All
       Require all granted
   </Directory>
   ```

4. **Verify network connectivity:**
   ```bash
   ping 192.168.8.25  # Test receiver connectivity
   ping 192.168.6.13  # Test WLED connectivity
   ```

### Password Configuration

The system password is configured in two places:
- `site-config.json` under `site.password`
- `landing.js` (client-side validation)

**Important**: Change the default password before deploying to production.

---

## Configuration Guide

### Zone Configuration (config.php)

Each zone has a `config.php` file defining its settings:

```php
<?php
// Receivers: AV devices that accept commands
const RECEIVERS = [
    'Display Name' => [
        'ip' => '192.168.8.XX',    // Device IP address
        'show_power' => true,       // Show power on/off buttons
        'power_on_command' => 'cec_tv_on.sh',   // Optional per-device power-on CLI command
        'power_off_command' => 'cec_tv_off.sh', // Optional per-device power-off CLI command
        'power_on_repeat' => true,  // Optional: include in delayed second Power All On pass (30s)
        'power_on_followup_command' => 'cec_watch_me.sh', // Optional: follow-up CEC source-select command
        'power_on_followup_fallback_command' => 'cec_power_on_tv', // Optional: fallback if primary fails
        'power_on_followup_delay_ms' => 7000, // Optional delay before follow-up (default 5000ms)
        'power_off_pre_command' => 'cec_watch_me.sh', // Optional: switch input before power off
        'power_off_pre_delay_ms' => 3000, // Optional delay before actual power off (default 3000ms)
    ],
];

// Transmitters: Input sources mapped to channel numbers
const TRANSMITTERS = [
    'Cable Box 1' => 7,
    'Apple TV' => 2,
    'RockBot Audio' => 10,
];

// Volume settings
const MAX_VOLUME = 11;
const MIN_VOLUME = 0;
const VOLUME_STEP = 1;

// API settings
const API_TIMEOUT = 2;           // Seconds
const LOG_LEVEL = 'error';       // debug, info, warning, error

// System URLs
const HOME_URL = '/';            // Relative path for home button
const LOG_FILE = __DIR__ . '/av_controls.log';

// Remote control commands (whitelist)
const REMOTE_CONTROL_COMMANDS = [
    'power', 'guide', 'up', 'down', 'left', 'right', 'select',
    'channel_up', 'channel_down',
    '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
    'last', 'exit'
];

// Device models that support volume control
const VOLUME_CONTROL_MODELS = [
    '3G+4+ TX', '3G+AVP RX', '3G+AVP TX', '3G+WP4 TX', '2G/3G SX'
];

// User-facing error messages
const ERROR_MESSAGES = [
    'connection' => 'Unable to connect to %s (%s). Please check the connection and try again.',
    'global' => 'Unable to connect to any receivers. Please check your network connection and try again.',
    'remote' => 'Unable to send remote command. Please try again.',
];
```

### Site-Wide Configuration (site-config.json)

Central configuration for the entire site:

```json
{
    "site": {
        "name": "Castle AV Controls",
        "tagline": "Audio-Visual Control System",
        "logo": "logo.png",
        "password": "1313",
        "sessionExpiresEndOfDay": true,
        "homeUrl": "/",
        "adminUrl": "zonemanager.php"
    },
    "network": {
        "avSubnet": "192.168.8",
        "wledSubnet": "192.168.6",
        "infraSubnet": "192.168.1",
        "apiBasePath": "/cgi-bin/api/",
        "apiTimeout": 2,
        "serverIp": "192.168.8.127"
    },
    "ui": {
        "theme": "dark",
        "primaryColor": "#6366f1",
        "accentColor": "#22d3ee",
        "defaultZoneColor": "#00C853",
        "touchMinHeight": "48px",
        "lowResBreakpoint": "1024px",
        "compactMode": false
    },
    "deviceDirectory": {
        "showInNav": true,
        "groupByCategory": true,
        "categories": [
            { "id": "av-receivers", "name": "AV Receivers", "icon": "monitor" },
            { "id": "transmitters", "name": "Transmitters", "icon": "broadcast" },
            { "id": "wled", "name": "WLED Lighting", "icon": "lightbulb" },
            { "id": "infrastructure", "name": "Infrastructure", "icon": "server" },
            { "id": "printers", "name": "Network Printers", "icon": "printer" }
        ]
    }
}
```

### Master Zone Registry (zones.json)

The `zones.json` file is the single source of truth for zone configuration:

```json
{
    "_readme": "Zone Management Configuration",
    "_instructions": {
        "adding_zone": "Add a new entry with unique 'id' (lowercase, no spaces). Set 'enabled' to true.",
        "removing_zone": "Set 'enabled' to false or remove the entry entirely.",
        "display_order": "Zones appear in the order listed here.",
        "hidden_zones": "Set 'showInNav' to false to hide from navigation."
    },
    "zones": [
        {
            "id": "bowling",
            "name": "Bowling Lanes",
            "description": "Bowling lanes area AV controls",
            "enabled": true,
            "showInNav": true,
            "icon": "bowling",
            "color": "#00C853"
        }
    ],
    "specialLinks": [
        {
            "id": "dashboard",
            "name": "Dashboard",
            "url": "dashboard/",
            "enabled": true,
            "showInNav": true,
            "color": "#2196F3"
        }
    ],
    "settings": {
        "defaultColor": "#00C853",
        "allowUserZoneCreation": true,
        "requirePasswordForZoneManagement": true
    }
}
```

### Infrastructure Devices (DBconfigs.ini)

Registry of non-AV infrastructure devices used by the status checker and device directory:

```ini
[Temp Sensors]
Sensor Name = 192.168.1.XX

[Pi Projects]
Project Name = 192.168.1.XX

[WLED]
Device Name = 192.168.6.XX

[Printers]
Printer Name = 192.168.1.XX
```

### Global Device Registry (devices.json)

Defines receiver and transmitter information used by the device directory:

```json
{
    "receivers": [
        { "name": "Device Name", "ip": "192.168.8.XX", "type": "video", "show_power": true }
    ],
    "transmitters": [
        { "name": "Cable Box 1", "channel": 4, "description": "Attic TX 1" }
    ],
    "settings": {
        "max_volume": 11,
        "min_volume": 0,
        "volume_step": 1,
        "api_timeout": 2
    }
}
```

### Data Files

| File | Format | Purpose |
|------|--------|---------|
| `transmitters.txt` | CSV | IR blaster devices: `Name, http://IP` |
| `payloads.txt` | INI | IR commands: `command=sendir,...` |
| `favorites.ini` | INI | Quick channels: `channel_number=Channel Name` under `[favorites]` |
| `WLEDlist.ini` | INI | WLED IPs: `ip1 = "192.168.X.X"` under `[WLEDs]` section |
| `saved_volumes.json` | JSON | Persistent volume state per receiver |

### IR Payload Format

IR commands in `payloads.txt` support two formats:

**SENDIR format (Global Cache):**
```ini
power=sendir,1:1,1,58000,1,1,192,192,48,145,...
channel_up=sendir,1:1,1,58000,1,1,193,192,49,...
```

**Pronto hex format:**
```ini
guide=0000 0048 0000 0018 00c0 00c0...
```

---

## Usage Guide

### Daily Operations

1. **Access the System**: Navigate to the server address in a web browser
2. **Authenticate**: Enter the system password (10 attempts allowed)
3. **Select Zone**: Click desired zone from the navigation grid
4. **Control Devices**:
   - **Channel**: Use dropdown to select input source (auto-submits)
   - **Volume**: Adjust slider (debounced auto-save, 300ms delay)
   - **Power**: Click Power On/Off buttons
   - **Remote**: Use virtual remote for detailed control

### Zone Manager

Access via the Zone Manager link on the home page or navigate to `/zonemanager.php`:

- **Add Zone**: Create new zone with optional template copy from existing zone
- **Edit Zone**: Modify name, description, visibility, icon, and color
- **Duplicate Zone**: Clone existing zone configuration and files
- **Reorder**: Drag and drop to change navigation order
- **Delete Zone**: Remove zone (optionally delete directory and files)
- **Quick Links**: Add, edit, and manage special navigation links

### Settings Editor

Access via Ctrl+Click on zone logo or `/settings.php?zone=zonename`:

- Add/remove receivers with IP validation
- Configure transmitter mappings with channel numbers
- Set volume limits (min, max, step)
- Configure API timeout
- View/restore from configuration backups (up to 10)
- File permission and ownership information displayed

### Device Directory

Access via the Devices button in zone headers or `/devices.php`:

- Browse all devices grouped by category (AV Receivers, Transmitters, WLED, Infrastructure, Printers)
- Search/filter devices by name
- View device IPs and zone assignments
- Clickable device URLs for direct access

### WLED Control

Control smart lighting via WLED buttons in zone footer:

- Zone-specific lighting control
- Bulk on/off operations for all WLED devices in zone
- Per-device status tracking with failure reporting
- 3-second timeout per device with 2-second connection timeout

### Keyboard Shortcuts

| Shortcut | Location | Action |
|----------|----------|--------|
| `Ctrl+Click` | Zone logo | Open Settings Editor |
| `Ctrl+Double-Click` | Zone logo | Return to Home page |
| `Enter` | Password field | Submit password |
| `Tab` | Remote buttons | Navigate between buttons |

---

## Authentication and Security

### Authentication Model

- **Client-Side Validation**: Password verified in browser (defined in `landing.js` and `site-config.json`)
- **Session Storage**: localStorage with daily expiration (midnight)
- **Attempt Limiting**: 10 failed attempts locks form until page refresh
- **Zone Validation**: Server-side whitelist prevents unauthorized zone access

### Security Features

| Feature | Implementation |
|---------|---------------|
| Input Sanitization | `sanitizeInput()` validates all user input (int, ip, string types) |
| IP Validation | `filter_var()` with `FILTER_VALIDATE_IP` prevents SSRF |
| Output Escaping | `htmlspecialchars()` prevents XSS |
| Zone Whitelist | Validation against `zones.json` registry |
| File Locking | Atomic writes with exclusive locks prevent race conditions |
| Error Masking | Internal paths not exposed to users |
| Config Protection | .htaccess blocks direct access to .ini, .log, .json.lock, backup files |
| CORS Headers | API endpoints allow cross-origin requests |

### Security Recommendations

For production deployment:
- Implement server-side authentication (LDAP, SSO)
- Enable HTTPS for all connections
- Restrict access by IP address
- Move `config.ini` outside web root
- Implement rate limiting on API endpoints
- Enable PHP error logging to file (not display)

---

## API Reference

### Device API Endpoints

Communication with AV receivers (on device at 192.168.8.X):

| Endpoint | Method | Purpose | Response |
|----------|--------|---------|----------|
| `/cgi-bin/api/details/channel` | GET | Get current channel | `{"data": 2}` |
| `/cgi-bin/api/command/channel` | POST | Set channel | `{"data": "OK"}` |
| `/cgi-bin/api/details/audio/stereo/volume` | GET | Get volume | `{"data": 10}` |
| `/cgi-bin/api/command/audio/stereo/volume` | POST | Set volume | `{"data": "OK"}` |
| `/cgi-bin/api/details/device/model` | GET | Get device model | `{"data": "3G+AVP RX"}` |
| `/cgi-bin/api/command/cli` | POST | Execute CLI command | `{"data": "OK"}` |
| `/cgi-bin/api/command/audio/dsp/line` | POST | DSP line control | `{"data": "OK"}` |
| `/cgi-bin/api/command/audio/dsp/hdmi` | POST | DSP HDMI control | `{"data": "OK"}` |
| `/cgi-bin/api/command/hdmi/audio/mute` | POST | Mute HDMI audio | `{"data": "OK"}` |
| `/cgi-bin/api/command/hdmi/audio/unmute` | POST | Unmute HDMI | `{"data": "OK"}` |

### System Web APIs

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/zones.php` | GET | Get zones, quick links, and settings configuration |
| `/api/receiver-status.php?ip=X` | GET | Get receiver channel, volume, and capabilities |
| `/api/devices.php` | GET | Get aggregated device directory from all sources |
| `/[zone]/` | POST | Control zone receivers (channel, volume, power, remote) |
| `/settings.php?zone=X` | GET/POST | Zone settings management |
| `/editini.php?zone=X` | GET/POST | Config file editor |
| `/wled.php` | POST | WLED lighting control |
| `/zonemanager.php` | POST | Zone management CRUD operations |
| `/status.php` | GET/POST | IOT device status checks (single, batch, all) |

### Receiver Status API

`GET /api/receiver-status.php?ip=192.168.8.XX`

**Response:**
```json
{
    "success": true,
    "channel": 4,
    "volume": 8,
    "supportsVolume": true
}
```

### Device Directory API

`GET /api/devices.php`

**Response:**
```json
{
    "devices": [
        {
            "name": "Device Name",
            "ip": "192.168.8.XX",
            "category": "av-receivers",
            "zones": ["Bowling Lanes", "DJ Booth"]
        }
    ],
    "generated": "2026-03-11 12:00:00",
    "categories": [
        { "id": "av-receivers", "name": "AV Receivers", "icon": "monitor" }
    ]
}
```

### IOT Status Checker API

`GET/POST /status.php`

**Actions:**
| Action | Parameters | Description |
|--------|------------|-------------|
| `check_single` | ip | Check status of a single device |
| `check_batch` | ips (JSON array) | Check multiple devices in parallel |
| `check_all` | - | Check all devices from DBconfigs.ini |

### Zone Manager API Actions

| Action | Parameters | Description |
|--------|------------|-------------|
| `add` | id, name, description, showInNav, icon, color, copyFrom | Create new zone |
| `update` | id, name, description, enabled, showInNav, icon, color | Update zone |
| `delete` | id, deleteDirectory | Remove zone |
| `duplicate` | sourceId, newId, newName | Clone zone |
| `reorder` | order (JSON array) | Reorder zones |
| `getZones` | - | Get all zones |
| `addQuickLink` | id, name, url, description, showInNav, color, openInNewTab | Add quick link |
| `updateQuickLink` | id, name, url, description, enabled, showInNav, color, openInNewTab | Update quick link |
| `deleteQuickLink` | id | Remove quick link |
| `reorderQuickLinks` | order (JSON array) | Reorder quick links |
| `getQuickLinks` | - | Get all quick links |

### WLED API

| Endpoint | Method | Payload |
|----------|--------|---------|
| `/json/state` (on WLED device) | POST | `{"on": true}` or `{"on": false}` |

---

## Alert & Monitoring System

### Configuration (config.ini)

```ini
[general]
alerts_enabled    = "true"
alert_cooldown    = "1"          # Minutes between alerts
alert_hours_start = ""           # Optional: start hour (0-23)
alert_hours_end   = ""           # Optional: end hour (0-23)

[security]
api_key     = ""                 # API key for authentication
allowed_ips = ""                 # Comma-separated allowed IPs

[slack_bot]
bot_token         = "xoxb-..."
channel           = "#av-alerts"
dashboard_url     = "http://192.168.8.127/monitor"
alert_on_down     = "true"
alert_on_recovery = "true"

[slack]
webhook_url = ""                 # Incoming webhook URL

[custom_webhook]
url               = ""
method            = "POST"
content_type      = "json"
body_template     = ""
headers           = ""
timeout           = "5"
retry_count       = "2"
basic_auth_user   = ""
basic_auth_pass   = ""
alert_on_down     = "true"
alert_on_recovery = "true"

[email]
recipients        = "tech@castlefun.com"
from_email        = "av-system@castlefun.com"
from_name         = ""
alert_on_down     = "true"
alert_on_recovery = "true"

[textbee]
enabled           = true
api_key           = ""
device_id         = ""           # Android device ID from TextBee app
recipients        = "+1234567890"
high_priority_only = false
include_url       = true         # Include monitor URL in SMS
```

### Supported Alert Channels

| Channel | Description |
|---------|-------------|
| Slack Bot | Direct channel messages via Bot API token |
| Slack Webhook | Incoming webhook notifications |
| Email | PHP mail() function |
| TextBee SMS | SMS alerts via TextBee API (requires Android device) |
| Custom Webhook | Configurable HTTP webhooks with auth support |

---

## Troubleshooting

### Authentication Issues

| Problem | Solution |
|---------|----------|
| Locked out | Refresh page to reset attempt counter |
| Session expired | Re-enter password (expires at midnight) |
| Clear session | Delete localStorage entries for the site |

### Device Communication

| Problem | Solution |
|---------|----------|
| Connection timeout | Verify device IP in config.php, check network |
| API errors | Confirm device is powered on and accessible |
| Volume not changing | Device may not support volume (check model list) |
| Power not working | Verify `show_power` is true and device supports CEC |
| Receiver shows "Loading..." | Wait a few seconds; device may be unreachable |

### IR Commands

| Problem | Solution |
|---------|----------|
| Commands not working | Verify IPs in transmitters.txt |
| Wrong actions | Check payloads.txt has correct IR codes |
| Intermittent failures | Normal - IR uses fire-and-forget semantics |
| No transmitters listed | Ensure transmitters.txt exists and is readable |

### WLED Issues

| Problem | Solution |
|---------|----------|
| Lights not responding | Verify IPs in WLEDlist.ini under `[WLEDs]` section |
| Partial control | Some devices may be offline (check failure list in response) |
| Timeout errors | Increase device timeout or check network |

### Configuration

| Problem | Solution |
|---------|----------|
| Settings not saving | Check write permissions on zone directories (777) |
| Backup failures | Ensure sufficient disk space |
| Config file locked | Wait for lock timeout (5 seconds) or check for stuck processes |
| Zones not loading | Check zones.json syntax and `/api/zones.php` response |

### Enabling Debug Logging

In zone `config.php`:
```php
define('LOG_LEVEL', 'debug');
```

Logs written to `[zone]/av_controls.log`

---

## Maintenance

### Quality Assurance

Run the built-in health check before deployments to validate zone configuration, required files, JSON syntax, and PHP syntax:

```bash
php scripts/health_check.php
```

The script is compatible with PHP 7.4+ and returns a non-zero exit code when issues are found.

Recommended pre-deploy flow:
1. Run `php scripts/health_check.php`.
2. Confirm `Errors: 0` in the summary.
3. Apply or restore any zone file/config fixes before shipping.

### Backup Procedures

The system automatically creates backups:
- `config_backup_YYYYMMDD_HHMMSS.php` - Before each config save
- Keeps 3 most recent backups per zone (older ones auto-deleted)
- Up to 10 backups available for restoration in Settings UI
- Backup files blocked from direct web access via .htaccess

**Manual backup:**
```bash
tar -czf av-system-backup-$(date +%Y%m%d).tar.gz /var/www/html/AV-system
```

### Log Management

Logs are stored per-zone in `av_controls.log`. To rotate:
```bash
for zone in bowling bowlingbar rink jesters facility outside dj multi all; do
    mv /var/www/html/AV-system/$zone/av_controls.log \
       /var/www/html/AV-system/$zone/av_controls.log.$(date +%Y%m%d)
done
```

### Device Reboot

To reboot all receivers in a zone:
```bash
cd /var/www/html/AV-system/[zone]
php -r "require 'config.php'; require '../shared/reboot.php';"
```

Devices reboot with 250ms delay between commands and take approximately 90 seconds to come back online.

### Health Checks

1. **Verify zone accessibility**: Visit each zone in browser
2. **Test device communication**: Change channel on each receiver
3. **Check WLED connectivity**: Toggle lights in each zone
4. **Review logs**: Check for error patterns in `[zone]/av_controls.log`
5. **Test API endpoint**: Visit `/api/zones.php` to verify JSON response
6. **Check device status**: Use `/status.php` or `/devices.php` for device overview

### Adding New Hardware

1. **New Receiver**:
   - Add entry to zone's `config.php` RECEIVERS array
   - Or use Settings Editor UI (`/settings.php?zone=zonename`)

2. **New Transmitter (Input Source)**:
   - Add entry to zone's `config.php` TRANSMITTERS array
   - Assign unique channel number

3. **New IR Blaster**:
   - Add entry to zone's `transmitters.txt`

4. **New WLED Device**:
   - Add entry to zone's `WLEDlist.ini` under `[WLEDs]` section

5. **New Infrastructure Device**:
   - Add entry to `DBconfigs.ini` under the appropriate section

### Creating a New Zone

1. **Via Zone Manager (Recommended)**:
   - Navigate to `/zonemanager.php`
   - Click "Add Zone"
   - Optionally copy from existing zone
   - Configure receivers and transmitters via Settings

2. **Manually**:
   - Copy `zone-templates/` to new directory
   - Rename and configure all files
   - Add zone to `zones.json`

---

## Authors

- **Seth Morrow** - System architecture and development

---

## License

This project is proprietary software for Castle Fun Center AV Control System.

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 3.0 | 2025 | Complete refactor with shared codebase, zone templates, atomic file writes |
| 3.0.1 | 2025 | Added Quick Links manager, improved UI with glassmorphism, enhanced accessibility |
| 3.0.2 | 2025-2026 | Device directory, IOT status checker, device reboot, LiveCode compatibility, receiver lazy loading |
| 2.0 | - | Zone manager and settings UI |
| 1.0 | - | Initial multi-zone implementation |
