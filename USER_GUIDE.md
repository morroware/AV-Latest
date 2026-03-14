# Castle AV Control System - User Guide

This guide covers all the common features and functionality of the Castle AV Control System.

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Understanding Zones](#understanding-zones)
3. [Controlling Receivers](#controlling-receivers)
4. [Using the Remote Control](#using-the-remote-control)
5. [Channel Presets (Favorites)](#channel-presets-favorites)
6. [WLED Smart Lighting](#wled-smart-lighting)
7. [Multi-Zone Control](#multi-zone-control)
8. [Device Directory](#device-directory)
9. [Zone Management](#zone-management)
10. [Settings & Configuration](#settings--configuration)
11. [Troubleshooting](#troubleshooting)

---

## Getting Started

### Logging In

1. Navigate to the AV Control System in your web browser
2. Enter the system password on the landing page
3. Click **Enter** or press the Enter key
4. You will be directed to the zone selection screen

**Session Duration**: Your session remains active until midnight. After that, you'll need to log in again.

**Failed Attempts**: After 10 incorrect attempts, the login form will lock. Refresh the page to reset the counter.

### Navigating the System

After logging in, you'll see the **Zone Selection Grid** showing all available zones. Click on any zone to access its controls.

**Navigation Tips**:
- Click the **Home** button (top-left) to return to the zone selection grid
- Click the **Settings** button (gear icon, top-right) to access zone configuration
- Click the **Devices** button to view the device directory
- Use **Ctrl+Click** on the zone logo to quickly access settings
- Use **Ctrl+Double-Click** on the logo to return home

---

## Understanding Zones

Zones are distinct areas of the facility, each with their own AV equipment. The system includes the following zones:

| Zone | Receivers | Description |
|------|-----------|-------------|
| Bowling Lanes | 4 | NeoVerse displays and bowling music system |
| Bowling Bar | 10 | Bar area TVs, NeoVerse displays, dining area, billiards |
| Roller Rink | 2 | Rink video displays and audio |
| Jesters | 3 | Arcade area and bar TVs |
| Facility | 1 | Facility-wide audio control |
| Outside | 1 | Outdoor area controls |
| DJ Booth | 24 | Main entertainment hub - all receivers |
| Multi | 2 | Control multiple zones at once |
| ALL | 24 | Control all zones simultaneously |

Each zone contains one or more **receivers** (output devices like TVs or speakers) that can be controlled independently.

---

## Controlling Receivers

Each receiver card in a zone provides the following controls:

### Changing Channels

1. Locate the receiver you want to control
2. Click the **Channel dropdown** menu
3. Select the desired input source (transmitter)
4. The channel change is applied automatically

**Available Input Sources**:
- Cable Box 1, 2, 3 (Attic TX 1-3)
- Apple TV
- RockBot Audio
- Wireless Mic
- Mobile Video/Audio
- Unifi Signage
- Trivia

### Adjusting Volume

1. Find the **Volume slider** on the receiver card
2. Drag the slider left (lower) or right (higher)
3. The volume level updates automatically after a brief delay (300ms)

**Note**: Not all devices support volume adjustment. If a device doesn't support it, the volume slider will not appear. Volume support depends on the receiver model.

### Power Control

Some receivers support power on/off control:

1. Look for the **Power On** (green) and **Power Off** (red) buttons
2. Click **Power On** to turn the display on
3. Click **Power Off** to turn it off

**Power On Sequence**: Some displays have a multi-step power-on process. The system sends a primary power command, may send an alternate ON variant, then follow-up CEC source-select commands after delay/retry windows to improve reliability.

**Power All On/Off**: Use the power buttons at the top of the zone to power on or off all receivers at once. Power-all-on runs in phases and sends a second pass automatically after ~30 seconds for devices that need extra time (when `power_on_repeat` is enabled).

**Note**: Power buttons only appear on receivers configured for CEC (Consumer Electronics Control).

---

## Using the Remote Control

The virtual remote control allows you to control cable boxes just like a physical remote.

### Selecting a Transmitter

1. Use the **Transmitter dropdown** at the top of the remote section
2. Select which IR blaster/cable box to control
3. All remote button presses will be sent to that transmitter

### Remote Buttons

| Button | Function |
|--------|----------|
| **Power** | Turn cable box on/off |
| **Guide** | Open the channel guide |
| **Arrow Keys** | Navigate menus (Up/Down/Left/Right) |
| **OK/Select** | Confirm selection |
| **CH+/CH-** | Change channel up/down |
| **0-9** | Enter channel numbers directly |
| **Last** | Return to previous channel |
| **Exit** | Exit current menu |

### Entering a Channel Directly

1. Use the number pad (0-9) to enter the channel number
2. Each digit is sent with a short delay (1 second) between them
3. The channel will tune after the last digit

**Example**: To tune to channel 35, press **3** then **5**.

---

## Channel Presets (Favorites)

Each zone has preset favorite channels for quick access.

### Using Favorites

1. Find the **Favorites dropdown** in the remote section
2. Click to see the list of preset channels
3. Select a channel (e.g., "ESPN", "NHL Network")
4. The system automatically sends the channel digits to the selected transmitter

### Common Preset Channels

- ESPN (Channel 35)
- ESPN2 (Channel 36)
- YES Network (Channel 70)
- SNY (Channel 60)
- NHL Network (Channel 219)
- MLB Network (Channel 213)

**Note**: Available presets vary by zone based on typical viewing preferences for that area. Presets can be customized via the config file editor.

---

## WLED Smart Lighting

Zones with WLED addressable lighting can be controlled directly from the interface.

### Turning Lights On/Off

1. Scroll to the **WLED Controls** section at the bottom of the zone page
2. Click **Lights On** to turn on all WLED devices in the zone
3. Click **Lights Off** to turn them all off

### Status Feedback

- A success message confirms when all devices respond
- If any devices fail to respond, you'll see which ones had issues
- Each device has a 3-second timeout

---

## Multi-Zone Control

The system provides two ways to control multiple zones at once:

### Multi Zone

The **Multi** zone allows you to select specific zones for batch operations:

1. Navigate to the **Multi** zone from the zone selection grid
2. Select which zones you want to control
3. Make your changes (channel, volume, power)
4. Changes apply to all selected zones

### ALL Zone

The **ALL** zone broadcasts commands to every receiver (21 total):

1. Navigate to the **ALL** zone
2. Any command you send applies to all receivers in the facility
3. Useful for facility-wide announcements or power management

**Use with caution**: ALL zone affects every display and speaker in the building.

---

## Device Directory

The Device Directory provides a centralized view of all devices in the system.

### Accessing the Directory

- Click the **Devices** button in any zone header
- Or navigate directly to `/devices.php`

### Device Categories

| Category | Description |
|----------|-------------|
| **AV Receivers** | Video displays and audio receivers (192.168.8.x) |
| **Transmitters** | IR blasters and input source mappings |
| **WLED Lighting** | Addressable LED controllers (192.168.6.x) |
| **Infrastructure** | Sensors, announcers, and Pi projects |
| **Network Printers** | Kitchen and wristband printers |

### Features

- **Search**: Filter devices by name using the search bar
- **Category Grouping**: Devices organized by type with icons
- **Zone Info**: See which zones each receiver belongs to
- **Clickable IPs**: Click device URLs to access their web interfaces directly

---

## Zone Management

Administrators can manage zones through the Zone Manager interface.

### Accessing Zone Manager

1. Navigate to `/zonemanager.php` in your browser
2. Or access through the admin settings

### Adding a New Zone

1. Click **Add Zone**
2. Enter the zone ID (folder name, lowercase, no spaces)
3. Enter the display name
4. Optionally add a description
5. Choose whether to copy configuration from an existing zone
6. Click **Create**

### Editing a Zone

1. Find the zone in the list
2. Click the **Edit** button
3. Modify the name, description, or visibility settings
4. Adjust colors if desired
5. Click **Save**

### Reordering Zones

1. Drag and drop zones in the list to change their navigation order
2. The order is saved automatically

### Deleting a Zone

1. Click the **Delete** button on the zone
2. Choose whether to also delete the zone's directory
3. Confirm the deletion

**Warning**: Deleting a zone's directory removes all its configuration files permanently.

### Quick Links

Quick Links are special navigation buttons on the home screen (e.g., Dashboard, OSD):

- **Add**: Create links to external URLs or system pages
- **Edit**: Modify link name, URL, color, and visibility
- **Reorder**: Drag and drop to change display order
- **Delete**: Remove links you no longer need

---

## Settings & Configuration

### Accessing Zone Settings

1. Navigate to the zone you want to configure
2. Click the **Settings** button (gear icon) in the top-right
3. Or use **Ctrl+Click** on the zone logo

### Managing Receivers

**Adding a Receiver**:
1. Click **Add Receiver**
2. Enter the display name
3. Enter the IP address (must be valid format)
4. Choose whether to show power buttons
5. Click **Save**

**Removing a Receiver**:
1. Find the receiver in the list
2. Click the **Remove** button
3. Confirm the removal

### Configuring Transmitters

1. Open zone settings
2. Find the **Transmitters** section
3. Each transmitter shows its channel number mapping
4. Modify channel numbers as needed
5. Changes save automatically

### Volume Settings

Configure volume limits per zone:

| Setting | Description |
|---------|-------------|
| **Min Volume** | Lowest allowed volume level |
| **Max Volume** | Highest allowed volume level |
| **Volume Step** | Increment for each volume change |

### API Settings

| Setting | Description |
|---------|-------------|
| **API Timeout** | How long to wait for device responses (seconds) |
| **Log Level** | Amount of logging detail (error, warn, info, debug) |

### Configuration Backups

The system automatically backs up configuration before changes.

**Restoring a Backup**:
1. Open zone settings
2. Scroll to **Backups** section
3. View the list of available backups (up to 10)
4. Click **Restore** on the backup you want
5. Confirm the restoration

### Editing Config Files

For advanced configuration, use the config file editor:
1. Access via `/editini.php?zone=zonename`
2. Edit transmitters.txt, favorites.ini, WLEDlist.ini, or payloads.txt
3. Save changes

---

## Troubleshooting

### Receiver Shows "Loading..."

**Cause**: The system is fetching the receiver's current status asynchronously.

**Solution**: Wait a few seconds. If it persists:
1. Check that the receiver is powered on
2. Verify the network connection
3. Try refreshing the page

### Channel Change Not Working

**Possible Causes**:
1. Wrong transmitter selected in the remote section
2. IR blaster not responding
3. Network connectivity issue

**Solutions**:
1. Verify the correct transmitter is selected in the dropdown
2. Try the channel change again
3. Check that the cable box is responding to the remote

### Volume Slider Not Appearing

**Cause**: The device model does not support volume control.

**Solution**: Only certain JAP models support volume adjustment (3G+4+ TX, 3G+AVP RX, 3G+AVP TX, 3G+WP4 TX, 2G/3G SX). If the receiver is a different model, volume control is not available.

### WLED Devices Not Responding

**Possible Causes**:
1. Device is offline or powered down
2. Network connectivity issue
3. Device IP has changed

**Solutions**:
1. Check that WLED devices are powered on
2. Verify devices are on the correct network (192.168.6.x)
3. Update device IPs in WLEDlist.ini if needed

### Password Not Working

**Possible Causes**:
1. Incorrect password
2. Too many failed attempts (locks after 10)

**Solutions**:
1. Verify you're using the correct password
2. If locked out, refresh the page to reset the attempt counter

### Page Not Loading

**Solutions**:
1. Check your network connection
2. Clear browser cache
3. Try a different browser
4. Verify the server is running

### Power (On/Off) Not Working

**Possible Causes**:
1. Display doesn't support the specific CEC command variant configured
2. Power buttons not enabled for the receiver (`show_power=false`)
3. Device-specific command mapping is incorrect for that TV model

**Solutions**:
1. Verify `show_power` is set to true in the zone config
2. Confirm configured commands for that receiver (`power_on_command`, `power_off_command`, `power_on_followup_command`, `power_off_pre_command`) exist on the target device
3. For Roku TVs, prefer `cec_power_on_tv` / `cec_power_off_tv` and keep `cec_watch_me.sh` follow-up/pre-off sequencing
4. Try manual power cycle once, then retest from UI

---

## Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| **Ctrl+Click** on logo | Open Settings |
| **Ctrl+Double-Click** on logo | Return to Home |
| **Tab** | Navigate between controls |
| **Enter** | Activate buttons/submit forms |
| **Escape** | Close dialogs |

---

## Tips & Best Practices

1. **Use Favorites** - Preset channels are faster than entering digits manually
2. **Check the Transmitter** - Always verify the correct transmitter is selected before using the remote
3. **Allow Time for Updates** - Volume and channel changes may take a moment to apply (especially with anti-popping enabled)
4. **Use Multi Zone Carefully** - Changes affect multiple areas simultaneously
5. **Backup Before Major Changes** - The system creates automatic backups, but verify before making significant configuration changes
6. **Use the Device Directory** - Quick way to check which devices are in which zones and find device IPs
7. **Check Device Status** - If multiple devices aren't responding, use the status checker to quickly identify offline devices

---

## Getting Help

If you encounter issues not covered in this guide:

1. Check the system logs for error messages (`[zone]/av_controls.log`)
2. Use the Device Directory or status checker to verify device connectivity
3. Contact your system administrator
4. Report issues at the project repository

---

*Castle AV Control System - User Guide*
