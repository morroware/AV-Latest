# Zone Template Files

These template files are used when creating new zones in the AV Control System.

## Files

- **config.php** - Main zone configuration (receivers, transmitters, settings)
- **index.php** - Zone entry point (handles requests)
- **template.php** - UI template for the zone control page
- **transmitters.txt** - IR transmitter device addresses
- **payloads.txt** - IR command codes for remote control
- **favorites.ini** - Quick-access channel presets
- **WLEDlist.ini** - WLED smart lighting device addresses

## Creating a New Zone

1. Go to Zone Manager (`/zonemanager.php`)
2. Click "Add New Zone"
3. Enter a zone ID (lowercase, no spaces)
4. Optionally copy settings from an existing zone
5. Edit `config.php` in the new zone folder to add your receivers and transmitters

## Customizing Templates

You can customize these template files to change defaults for all new zones.
Changes here won't affect existing zones.
