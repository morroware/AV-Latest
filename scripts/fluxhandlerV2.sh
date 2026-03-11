#!/bin/bash
#
# fluxhandlerV2.sh — IR Command Handler for Just Add Power (JAP) Devices
# ======================================================================
#
# Last Modified 2021-08-27 — Just Add Power
# Updated to fix printf syntax and add debugging for ezlogger
#
# IMPORTANT: This script does NOT run on the AV control server (192.168.8.127).
# It lives on each Just Add Power receiver/transmitter unit and is included in
# this repository for REFERENCE AND DOCUMENTATION PURPOSES ONLY.
#
# ─────────────────────────────────────────────────────────────────────
# PURPOSE
# ─────────────────────────────────────────────────────────────────────
#
# This script is the bridge between the AV control system's HTTP API and the
# physical IR blaster hardware on each JAP device. It reads commands from
# stdin (fed via TCP port 4998 or piped from the CLI endpoint), interprets
# them, and either:
#   - Sends IR signals out through the serial-attached IR transmitter, or
#   - Returns device/version/network metadata responses.
#
# ─────────────────────────────────────────────────────────────────────
# HOW THIS PROJECT INVOKES THIS SCRIPT
# ─────────────────────────────────────────────────────────────────────
#
# The PHP backend (shared/BaseController.php and shared/api.php) sends IR
# commands to JAP devices by POSTing to the device's HTTP CLI endpoint:
#
#   POST http://192.168.8.XX/cgi-bin/api/command/cli
#   Body: echo "<payload>" | ./fluxhandlerV2.sh
#
# Where <payload> is an IR code string from a zone's payloads.txt file.
# Payloads come in two formats:
#
#   1. Global Caché "sendir" format (comma-separated):
#      sendir,1:1,1,58000,1,1,192,192,48,145,48,145,...
#
#   2. Pronto hex format (space-separated hex words):
#      0000 0048 0000 0018 00c1 00c0 0031 0090 ...
#
# The JAP device's CLI endpoint executes this as a shell command, which
# pipes the payload string into this script's stdin. The script then
# forwards it to the IR blaster via the serial port (/dev/ttyS0).
#
# ─────────────────────────────────────────────────────────────────────
# PROTOCOL OVERVIEW
# ─────────────────────────────────────────────────────────────────────
#
# This script emulates a subset of the Global Caché iTach IR protocol,
# which is a well-known standard for IP-to-IR control. The supported
# commands are:
#
#   sendir,<connector>,<id>,<freq>,<repeat>,<offset>,<data...>
#     → Transmit an IR signal with the given parameters
#
#   getdevices
#     → List available device modules (returns ETHERNET + IR)
#
#   getversion
#     → Return firmware/handler version string
#
#   get_NET,<module>:<port>
#     → Return network configuration (IP, mask, gateway)
#
#   get_IR,<module>:<port>
#     → Return IR module info
#
#   stopir,<module>:<port>
#     → Stop any in-progress IR transmission
#
#   <hex data>
#     → Raw Pronto hex IR code, sent directly to serial
#
# ─────────────────────────────────────────────────────────────────────
# HARDWARE DETAILS
# ─────────────────────────────────────────────────────────────────────
#
# - Serial port: /dev/ttyS0 at 115200 baud
# - The IR blaster MCU is connected via this serial interface
# - microcom is used for serial communication with a 350ms timeout
# - The JAP device runs a custom Linux (justOS) with busybox utilities
#
# ─────────────────────────────────────────────────────────────────────

# Source the JAP device's OS environment. This provides:
#   - JUST_DEBUG(): Logging function that writes to the device's debug log
#   - MYIP, MYMASK, MYGW: Network configuration variables for this device
#   - Various other justOS utility functions
source justOS

# Enable debug tracing for this script. When set, JUST_DEBUG calls will
# output to the device's log (viewable via the JAP web UI or ezlogger).
declare -r DEBUG_THIS

# ─────────────────────────────────────────────────────────────────────
# MAIN LOOP
# ─────────────────────────────────────────────────────────────────────
#
# Read lines from stdin continuously. In normal operation, the CLI
# endpoint pipes a single command, so this loop typically runs once.
# When used as a TCP listener on port 4998 (Global Caché emulation),
# it stays open for multiple commands per session.
while read -r INPUT; do

  # Log the raw input for debugging. Visible in JAP device logs.
  JUST_DEBUG "Got \"${INPUT}\" from TCP:4998"

  # ─────────────────────────────────────────────────────────────────
  # COMMAND DISPATCHER
  # ─────────────────────────────────────────────────────────────────
  #
  # Match the input against known command patterns and prepare either:
  #   - SEND: data to transmit via serial to the IR blaster, or
  #   - RESPOND: text to send back to the caller (stdout/TCP)
  case "${INPUT}" in

  # ── sendir: Transmit an IR signal ──────────────────────────────
  #
  # Input format: sendir,<connector>,<id>,<freq>,<repeat>,<offset>,<on>,<off>,...
  #   - connector: module:port (e.g., "1:1" = module 1, port 1)
  #   - id:        sequence ID for tracking (echoed in response)
  #   - freq:      carrier frequency in Hz (e.g., 38000 for most devices)
  #   - repeat:    number of times to repeat the IR pattern
  #   - offset:    offset into pattern data for repeat start
  #   - on/off:    pairs of mark/space durations in carrier cycles
  #
  # Processing steps:
  #   1. Split the comma-separated string into an array (GCFMT)
  #   2. Override the repeat count (index 4) to 3. This is a workaround
  #      for Sony IR codes which require 3 repetitions to be recognized.
  #      Most other devices tolerate extra repeats without issue.
  #   3. Reassemble the array back into a comma-separated string (SEND)
  #
  # The modified payload is then sent to the IR blaster via serial port.
  *sendir*)

    # Split CSV into array by replacing commas with spaces
    GCFMT=(${INPUT//,/ }) && GCFMT[4]=3 # hack repeat count to 3 for sony codes.. hope no break others..

    # Rejoin array with commas to form the modified sendir command
    SEND=${GCFMT[*]} && SEND=${SEND// /,}

    ;;

  # ── getdevices: List available device modules ──────────────────
  #
  # Returns a static device list mimicking Global Caché iTach response.
  # This tells the controlling software what modules are available:
  #   - device,0,0 ETHERNET: Network module
  #   - device,1,1 IR: IR blaster module on connector 1
  #   - endlistdevices: Terminator for the device list
  #
  # Our PHP app doesn't call this, but third-party Global Caché
  # clients (like iRule, Simple Control) use it for device discovery.
  *getdevices*) RESPOND=$(printf "%s\r" "device,0,0 ETHERNET" "device,1,1 IR" "endlistdevices") ;;

  # ── getversion: Return handler version ─────────────────────────
  #
  # Identifies this script and its version. Useful for diagnostics
  # and verifying the correct handler is installed on a JAP device.
  *getversion*) RESPOND="version,1,FluxCapacitor_v2" ;;

  # ── get_NET: Return network configuration ──────────────────────
  #
  # Returns the device's network settings using variables from justOS:
  #   - MYIP:   Device IP address (e.g., 192.168.8.41)
  #   - MYMASK: Subnet mask (e.g., 255.255.255.0)
  #   - MYGW:   Default gateway (e.g., 192.168.8.1)
  #
  # Format: NET,<module>:<port>,LOCKED,STATIC,<ip>,<mask>,<gateway>
  *get_NET*) RESPOND="NET,0:1,LOCKED,STATIC,${MYIP},${MYMASK},${MYGW}" ;;

  # ── get_IR: Return IR module info ──────────────────────────────
  #
  # Confirms the IR module is present on connector 1, port 1.
  *get_IR*) RESPOND="IR,1:1,IR" ;;

  # ── stopir: Stop IR transmission ───────────────────────────────
  #
  # Acknowledges a stop request. The actual IR transmission is
  # typically already complete by the time this arrives due to the
  # short duration of IR bursts, but the protocol requires a response.
  *stopir*) RESPOND="stopir,1:1" ;;

  # ── Default: Handle raw hex codes or return error ──────────────
  #
  # Two possibilities:
  #
  # 1. Pronto hex format: A string of space-separated 4-digit hex words
  #    (e.g., "0000 0048 0000 0018 00c1 00c0 ..."). Some entries in
  #    payloads.txt use this format instead of sendir. The regex checks
  #    for one or more 4-character hex groups. If matched, the raw hex
  #    is sent directly to the IR blaster via serial.
  #
  # 2. Unrecognized input: Returns ERR_001 (unknown command).
  *)

    if [[ "$INPUT" =~ ^([0-9a-fA-F]{4}\s?)+ ]]; then

      # Valid Pronto hex — pass through to serial as-is
      SEND=${INPUT}

    else

      # Unknown command — return error code
      RESPOND="ERR_001"

    fi

    ;;

  esac

  # ─────────────────────────────────────────────────────────────────
  # SERIAL TRANSMISSION
  # ─────────────────────────────────────────────────────────────────
  #
  # If SEND is set (from sendir or raw hex), transmit the IR data
  # to the IR blaster MCU via the serial port.
  #
  # Serial parameters:
  #   - /dev/ttyS0: UART connected to the IR blaster hardware
  #   - 115200 baud: Communication speed
  #   - 350ms timeout (-t 350): How long to wait for a response from
  #     the IR blaster before giving up. IR transmissions complete in
  #     ~100ms, so 350ms provides generous margin.
  #   - -X: Exit after timeout (don't hang waiting for more data)
  #
  # The printf wraps the data in \r (carriage return) delimiters,
  # which is the line terminator expected by the IR blaster firmware.
  #
  # The response from the IR blaster (if any) is captured in RESPOND.
  if [ -n "$SEND" ]; then

    JUST_DEBUG ">>> $SEND"

    # Pipe the IR data to the serial port and capture any response
    RESPOND=$(printf "\r%s\r" "$SEND" | microcom -t 350 -s 115200 -X /dev/ttyS0)

  fi

  # ─────────────────────────────────────────────────────────────────
  # RESPONSE OUTPUT
  # ─────────────────────────────────────────────────────────────────
  #
  # Send the response back to the caller (stdout → TCP or CLI pipe).
  # The \r terminator follows Global Caché protocol conventions.
  #
  # For IR sends, this is the IR blaster's acknowledgment.
  # For queries (getdevices, getversion, etc.), this is the metadata.
  # For errors, this is "ERR_001".
  if [ -n "$RESPOND" ]; then

    JUST_DEBUG "<<< $RESPOND"

    # Send response back to caller with carriage return terminator
    printf "%s\r" "$RESPOND"

  fi

  # ─────────────────────────────────────────────────────────────────
  # CLEANUP
  # ─────────────────────────────────────────────────────────────────
  #
  # Clear SEND and RESPOND so the next iteration of the loop starts
  # fresh. Without this, a command that only sets RESPOND would
  # accidentally re-send the previous iteration's SEND data.
  unset SEND
  unset RESPOND

done
