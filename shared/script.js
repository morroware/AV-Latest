/**
 * Combined JavaScript for AV Controls and Remote Control System
 * Enhanced with loading states, better feedback, and accessibility
 * Compatible with LiveCode browser widget
 */

// Use LiveCode-compatible fetch if available
const compatFetch = (window.LiveCodeCompat && window.LiveCodeCompat.fetch) || window.fetch.bind(window);

document.addEventListener('DOMContentLoaded', function() {
    // Initialize both systems
    initializeReceiverControls();
    loadTransmitters();
    loadFavoriteChannels();
    initializeAccessibility();
    initializeWledControls();

    // Lazy-load receiver status after page load
    lazyLoadReceivers();

    // Start cross-PC polling so changes made on one browser are reflected
    // on every other open browser within RECEIVER_POLL_INTERVAL_MS.
    startReceiverPolling();
});

// Add loading state to an element
function setLoading(element, isLoading) {
    if (isLoading) {
        element.classList.add('loading-state');
        element.disabled = true;
        element.dataset.originalText = element.textContent;
        if (element.tagName === 'BUTTON') {
            element.innerHTML = '<span class="spinner"></span> ' + element.textContent;
        }
    } else {
        element.classList.remove('loading-state');
        element.disabled = false;
        if (element.dataset.originalText) {
            element.textContent = element.dataset.originalText;
        }
    }
}

// Initialize accessibility features
function initializeAccessibility() {
    // Add keyboard navigation for remote buttons
    document.querySelectorAll('.remote-container button').forEach(button => {
        button.setAttribute('tabindex', '0');
    });

    // Announce changes to screen readers
    const announcer = document.createElement('div');
    announcer.setAttribute('aria-live', 'polite');
    announcer.setAttribute('aria-atomic', 'true');
    announcer.className = 'sr-only';
    announcer.id = 'announcer';
    document.body.appendChild(announcer);
}

function announce(message) {
    const announcer = document.getElementById('announcer');
    if (announcer) {
        announcer.textContent = message;
        setTimeout(() => announcer.textContent = '', 1000);
    }
}

// Receiver Control Functions - Modified for auto-submit
function initializeReceiverControls() {
    // Auto-submit for channel changes
    $(document).on('change', '.channel-select', function() {
        const select = $(this);
        const receiverCard = select.closest('.receiver');
        const deviceIp = select.data('ip') || receiverCard.data('ip');
        const channelName = select.find('option:selected').text();

        // Visual feedback
        receiverCard.addClass('updating');
        select.prop('disabled', true);

        // Suppress polling for a few seconds so the dropdown doesn't flicker
        // back to the previous channel while the device is still transitioning.
        markReceiverWritten(receiverCard[0]);

        const data = new FormData();
        data.append('receiver_ip', deviceIp);
        data.append('channel', this.value);

        $.ajax({
            url: '',
            type: 'POST',
            data: data,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    markReceiverWritten(receiverCard[0]);
                }
                showResponseMessage(response.success ? `Switched to ${channelName}` : response.message, response.success);
                announce(response.success ? `Channel changed to ${channelName}` : 'Channel change failed');
            },
            error: function() {
                showResponseMessage('Failed to update channel. Check device connection.', false);
            },
            complete: function() {
                receiverCard.removeClass('updating');
                select.prop('disabled', false);
            }
        });
    });

    // Also support the old class for backwards compatibility
    $(document).on('change', 'select.auto-submit', function() {
        const form = $(this).closest('form');
        const select = $(this);
        const receiverCard = select.closest('.receiver');

        receiverCard.addClass('updating');
        select.prop('disabled', true);

        const data = new FormData();
        data.append('receiver_ip', form.find('input[name="receiver_ip"]').val());
        data.append('channel', this.value);

        $.ajax({
            url: '',
            type: 'POST',
            data: data,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.message) {
                    showResponseMessage(response.message, response.success);
                }
            },
            error: function() {
                showResponseMessage('Failed to update channel', false);
            },
            complete: function() {
                receiverCard.removeClass('updating');
                select.prop('disabled', false);
            }
        });
    });

    // Debounced auto-submit for volume changes.  Per-slider timeout so two
    // receivers can be adjusted in quick succession without one cancelling
    // the other's pending request.  On failure we revert the slider to the
    // last value the device accepted, so the UI never misrepresents state.
    $(document).on('input', '.volume-slider, input[type="range"].auto-submit', function() {
        const slider = this;
        const $slider = $(slider);
        const receiverCard = $slider.closest('.receiver');
        const deviceIp = $slider.data('ip') || receiverCard.data('ip');

        // Remember the starting value the first time the user drags this slider
        if (typeof slider._lastConfirmedValue === 'undefined') {
            slider._lastConfirmedValue = slider.value;
        }

        // Update volume label immediately for visual responsiveness
        updateVolumeLabel(slider);

        // Suppress polling during drag so the slider doesn't snap back to the
        // device's old value mid-adjustment.
        markReceiverWritten(receiverCard[0]);

        if (slider._volumeTimeout) {
            clearTimeout(slider._volumeTimeout);
        }

        slider._volumeTimeout = setTimeout(() => {
            receiverCard.addClass('updating');
            const attemptedValue = slider.value;

            const data = new FormData();
            data.append('receiver_ip', deviceIp);
            data.append('volume', attemptedValue);

            $.ajax({
                url: '',
                type: 'POST',
                data: data,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        slider._lastConfirmedValue = attemptedValue;
                        markReceiverWritten(receiverCard[0]);
                        announce(`Volume set to ${attemptedValue}`);
                    } else {
                        // Device unreachable or rejected — revert UI so the
                        // slider matches the device state.
                        slider.value = slider._lastConfirmedValue;
                        updateVolumeLabel(slider);
                        showResponseMessage(response.message || 'Volume update failed', false);
                    }
                },
                error: function() {
                    slider.value = slider._lastConfirmedValue;
                    updateVolumeLabel(slider);
                    showResponseMessage('Failed to update volume. Check device connection.', false);
                },
                complete: function() {
                    receiverCard.removeClass('updating');
                }
            });
        }, 300); // 300ms debounce for volume changes
    });

    // Power On button handler with delayed second command
    $('#power-all-on').on('click', function() {
        // No response message - send command silently
        sendPowerCommandToAll('cec_tv_on.sh', false)
            .then(function() {
                console.log("First power-on command sent, will repeat in 30 seconds");

                // Set a timer to send the command again after 30 seconds
                setTimeout(function() {
                    sendPowerCommandToAll('cec_tv_on.sh', false, { repeatPass: true })
                        .then(function() {
                            console.log("Second power-on command sent");
                        });
                }, 30000); // 30 seconds delay
            });
    });

    // Power Off button handler
    $('#power-all-off').on('click', function() {
        // No response message - send command silently
        sendPowerCommandToAll('cec_tv_off.sh', false);
    });
}

// WLED Control Functions
function initializeWledControls() {
    const wledControls = document.getElementById('wled-footer-controls');
    if (!wledControls) return;

    const zone = wledControls.dataset.zone;
    if (!zone) {
        console.warn('WLED controls found but no zone specified');
        return;
    }

    // WLED Power On button
    const powerOnBtn = wledControls.querySelector('.power-on');
    if (powerOnBtn) {
        powerOnBtn.addEventListener('click', function() {
            sendWledCommand(zone, 'on', this);
        });
    }

    // WLED Power Off button
    const powerOffBtn = wledControls.querySelector('.power-off');
    if (powerOffBtn) {
        powerOffBtn.addEventListener('click', function() {
            sendWledCommand(zone, 'off', this);
        });
    }
}

function sendWledCommand(zone, action, buttonElement) {
    // Add visual feedback
    if (buttonElement) {
        buttonElement.classList.add('clicked');
        setTimeout(() => buttonElement.classList.remove('clicked'), 150);
    }

    const formData = new FormData();
    formData.append('zone', zone);
    formData.append('action', action);

    compatFetch('../wled.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showResponseMessage(`WLED lights turned ${action}`, true);
        } else {
            showResponseMessage(data.message || `Failed to turn WLED ${action}`, false);
        }
    })
    .catch(error => {
        console.error('WLED command error:', error);
        showResponseMessage(`Failed to send WLED ${action} command`, false);
    });
}

// Volume and Power functions
function updateVolumeLabel(slider) {
    const label = slider.parentElement.querySelector('.volume-label');
    if (label) {
        label.textContent = slider.value;
    }
}

function sendPowerCommand(deviceIp, command, showNotification = true) {
    return $.ajax({
        url: '',
        type: 'POST',
        data: {
            receiver_ip: deviceIp,
            power_command: command
        },
        dataType: 'json'
    }).then(function(response) {
        if (showNotification && response.message) {
            showResponseMessage(response.message, response.success);
        }
        return response;
    });
}

function waitMs(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

// Some TVs (Roku, certain Samsung/LG sets with aggressive CEC filtering) need
// an extended retry sequence — direct CEC binary calls plus repeated
// one-touch-play source selects — to reliably power on/off.  This used to be
// hardcoded to specific IPs; it's now opt-in per receiver via the
// `power_extended_retry` config flag (data-power-extended-retry on the card).
// The original IP/name list stays as a fallback so existing deployments keep
// working without a config edit.
function needsExtendedCecRetry(receiverElement, deviceIp) {
    if (receiverElement && receiverElement.dataset && receiverElement.dataset.powerExtendedRetry === '1') {
        return true;
    }

    const ip = String(deviceIp || '').trim();
    if (ip === '192.168.8.23' || ip === '192.168.8.44') {
        return true;
    }

    const name = String((receiverElement && receiverElement.dataset && receiverElement.dataset.name) || '').toLowerCase();
    return name.includes('billiards tv') || name.includes('dining area tv');
}

// Backwards-compatible alias — older inlined calls may still reference this.
function isRokuTarget(receiverElement, deviceIp) {
    return needsExtendedCecRetry(receiverElement, deviceIp);
}

function sendConfiguredPowerOn(receiverElement, deviceIp, showNotification = true, options = {}) {
    const powerOnCommand = receiverElement.dataset.powerOnCommand || 'cec_tv_on.sh';
    const alternatePowerOnCommand = (powerOnCommand === 'cec_power_on_tv') ? 'cec_tv_on.sh' : 'cec_power_on_tv';
    const extendedRetry = needsExtendedCecRetry(receiverElement, deviceIp);
    const followupCommand = receiverElement.dataset.powerOnFollowupCommand;
    const followupFallbackCommand = receiverElement.dataset.powerOnFollowupFallbackCommand;
    const followupDelayMs = parseInt(receiverElement.dataset.powerOnFollowupDelayMs, 10) || 7000;
    const shouldSendFollowup = Boolean(followupCommand);

    // Some displays may still react to power-on even when the HTTP request itself fails/times out.
    // Keep the sequence resilient by attempting the follow-up command regardless.
    return sendPowerCommand(deviceIp, powerOnCommand, showNotification)
        .catch(function(error) {
            console.warn('Power-on command request failed, continuing with follow-up if configured:', error);
            return null;
        })
        .then(function(response) {
            // Match the bulk power-on strategy for individual buttons too:
            // send both discrete variants to improve CEC reliability across
            // different TV brands (Roku, LG, Samsung, Sony behave differently).
            return waitMs(1500)
                .then(() => sendPowerCommand(deviceIp, alternatePowerOnCommand, false).catch(() => null))
                .then(function() {
                    if (!extendedRetry) {
                        return null;
                    }

                    // Extended retry: TVs with aggressive CEC filtering wake more
                    // reliably when the direct binary (cec_power_on_tv) is sent
                    // again with a gap — it issues both System Standby-Off (0x04)
                    // and Image/Text View-On (0x82/0x0D) on the CEC bus.
                    // NOTE: this previously sent the uppercase string 'CEC_TV_ON',
                    // which is not a valid command on the JAP device's Linux
                    // shell (filenames are case-sensitive) — it was effectively a
                    // no-op that only served to pace the sequence.  We now send
                    // the real binary so the retry actually does something.
                    return waitMs(1000)
                        .then(() => sendPowerCommand(deviceIp, 'cec_power_on_tv', false).catch(() => null))
                        .then(() => waitMs(1000))
                        .then(() => sendPowerCommand(deviceIp, 'cec_watch_me.sh', false).catch(() => null));
                })
                .then(() => response);
        })
        .then(function(response) {
            if (!shouldSendFollowup) {
                return response;
            }

            // Send follow-up (input switch) after delay, then retry once more
            // for CEC reliability — displays sometimes miss a single command.
            return waitMs(Math.max(0, followupDelayMs))
                .then(() => sendPowerCommand(deviceIp, followupCommand, false).catch(() => null))
                .then(() => waitMs(3000))
                .then(() => sendPowerCommand(deviceIp, followupCommand, false).catch(() => null))
                .then(function() {
                    // HTTP success does not guarantee HDMI input actually switched.
                    // For TVs flagged for extended retry, do additional source-select passes.
                    if (!extendedRetry) {
                        return null;
                    }

                    return waitMs(1500)
                        .then(() => sendPowerCommand(deviceIp, 'cec_watch_me.sh', false).catch(() => null))
                        .then(() => waitMs(2000))
                        .then(() => sendPowerCommand(deviceIp, 'cec_watch_me.sh', false).catch(() => null));
                })
                .then(function() {
                    if (!followupFallbackCommand) {
                        return null;
                    }

                    // Send fallback as an additional pass (not only on transport error)
                    // because some displays acknowledge but ignore the first source change.
                    return waitMs(1200)
                        .then(() => sendPowerCommand(deviceIp, followupFallbackCommand, false).catch(() => null));
                })
                .then(() => response);
        });
}

function sendConfiguredPowerOff(receiverElement, deviceIp, showNotification = true) {
    const powerOffCommand = receiverElement.dataset.powerOffCommand || 'cec_tv_off.sh';
    const alternatePowerOffCommand = (powerOffCommand === 'cec_power_off_tv') ? 'cec_tv_off.sh' : 'cec_power_off_tv';
    const preCommand = receiverElement.dataset.powerOffPreCommand;
    const preDelayMs = parseInt(receiverElement.dataset.powerOffPreDelayMs, 10) || 3000;
    const extendedRetry = needsExtendedCecRetry(receiverElement, deviceIp);

    function sendPowerOffWithRetry() {
        return sendPowerCommand(deviceIp, powerOffCommand, showNotification)
            .catch(function(error) {
                console.warn('Power-off command request failed, retrying once:', error);
                return null;
            })
            .then(function(response) {
                return waitMs(1500)
                    .then(() => sendPowerCommand(deviceIp, powerOffCommand, false).catch(() => null))
                    .then(function() {
                        if (!extendedRetry) {
                            return null;
                        }

                        // Extended retry: some TVs ignore a single Standby message
                        // depending on current HDMI input / firmware state.  Send
                        // both wrapper variants plus the direct CEC binary twice
                        // for max reliability.
                        // NOTE: previously sent the uppercase string 'CEC_TV_OFF',
                        // which is not a valid shell command on the JAP device
                        // (see sendConfiguredPowerOn).  Replaced with the real
                        // cec_power_off_tv binary so the retry actually issues a
                        // CEC Standby frame instead of returning "command not found".
                        return waitMs(1000)
                            .then(() => sendPowerCommand(deviceIp, alternatePowerOffCommand, false).catch(() => null))
                            .then(() => waitMs(1000))
                            .then(() => sendPowerCommand(deviceIp, 'cec_power_off_tv', false).catch(() => null))
                            .then(() => waitMs(1200))
                            .then(() => sendPowerCommand(deviceIp, 'cec_power_off_tv', false).catch(() => null));
                    })
                    .then(() => response);
            });
    }

    if (!preCommand) {
        return sendPowerOffWithRetry();
    }

    // For displays like Roku TVs that only accept CEC Standby when on the
    // correct HDMI input: switch input first, wait, then send standby.
    return sendPowerCommand(deviceIp, preCommand, false)
        .catch(function(error) {
            console.warn('Power-off pre-command failed, continuing with standby:', error);
            return null;
        })
        .then(function() {
            return waitMs(Math.max(0, preDelayMs));
        })
        .then(function() {
            return sendPowerOffWithRetry();
        });
}

function resolvePowerCommand(receiverElement, fallbackCommand) {
    const isPowerOn = fallbackCommand === 'cec_power_on_tv' || fallbackCommand === 'cec_tv_on.sh';
    const command = isPowerOn
        ? receiverElement.dataset.powerOnCommand
        : receiverElement.dataset.powerOffCommand;

    return command || fallbackCommand;
}

/**
 * Send AJAX commands in batches to avoid saturating the browser connection pool.
 * @param {Array} items - Array of items to process
 * @param {Function} commandFn - Function that takes an item and returns a Promise
 * @param {number} batchSize - Max items per batch (default 6, matching browser connection limit)
 * @param {number} staggerMs - Delay between batches in ms
 */
function sendCommandInBatches(items, commandFn, batchSize, staggerMs) {
    batchSize = batchSize || 6;
    staggerMs = staggerMs || 300;

    var batches = [];
    for (var i = 0; i < items.length; i += batchSize) {
        batches.push(items.slice(i, i + batchSize));
    }

    var chain = Promise.resolve();
    batches.forEach(function(batch, index) {
        chain = chain.then(function() {
            var promises = batch.map(function(item) { return commandFn(item); });
            return Promise.all(promises).then(function() {
                if (index < batches.length - 1) {
                    return waitMs(staggerMs);
                }
            });
        });
    });
    return chain;
}

/**
 * Phased power-on: separates power-on commands from follow-up (input switch)
 * commands so they never compete for the same browser connection slots.
 *
 * Phase 1: Send power-on to all devices in batches
 * Phase 2: Wait for TVs to stabilize (7s)
 * Phase 3: Send cec_watch_me.sh to all devices in batches
 * Phase 4: Wait 3s
 * Phase 5: Retry cec_watch_me.sh in batches
 */
function sendPhasedPowerOn(receivers, options) {
    // Phase 1a: Send configured power-on command to all devices
    return sendCommandInBatches(receivers, function(r) {
        var powerOnCommand = r.element.dataset.powerOnCommand || 'cec_tv_on.sh';
        return sendPowerCommand(r.ip, powerOnCommand, false).catch(function() { return null; });
    })
    .then(function() {
        // Phase 1b: Send alternative power-on command for belt-and-suspenders CEC reliability.
        // JAP devices return OK even if the TV ignores the CEC command, so we can't rely on
        // error-based fallback. Send both cec_tv_on.sh and cec_power_on_tv to maximize chances.
        return waitMs(1500);
    })
    .then(function() {
        return sendCommandInBatches(receivers, function(r) {
            var primary = r.element.dataset.powerOnCommand || 'cec_tv_on.sh';
            var alt = (primary === 'cec_power_on_tv') ? 'cec_tv_on.sh' : 'cec_power_on_tv';
            return sendPowerCommand(r.ip, alt, false).catch(function() { return null; });
        });
    })
    .then(function() {
        // On repeat pass, skip follow-up phases (input was already switched on first pass)
        if (options && options.repeatPass) return;

        // Collect receivers that have a follow-up command configured
        var followupReceivers = receivers.filter(function(r) {
            return Boolean(r.element.dataset.powerOnFollowupCommand);
        });
        if (followupReceivers.length === 0) return;

        // Find the max follow-up delay across all receivers
        var maxDelay = followupReceivers.reduce(function(max, r) {
            var delay = parseInt(r.element.dataset.powerOnFollowupDelayMs, 10) || 7000;
            return Math.max(max, delay);
        }, 0);

        // Phase 2: Wait for TVs to stabilize after power-on
        return waitMs(maxDelay)
            .then(function() {
                // Phase 3: Send follow-up (cec_watch_me.sh) to all devices
                return sendCommandInBatches(followupReceivers, function(r) {
                    return sendPowerCommand(r.ip, r.element.dataset.powerOnFollowupCommand, false)
                        .catch(function() { return null; });
                });
            })
            .then(function() {
                // Phase 4: Wait, then send BOTH follow-up AND fallback commands.
                // JAP devices return OK even when the TV ignores the CEC command,
                // so error-based fallback never triggers. Always send both commands.
                return waitMs(3000);
            })
            .then(function() {
                // Phase 5a: Retry follow-up command
                return sendCommandInBatches(followupReceivers, function(r) {
                    return sendPowerCommand(r.ip, r.element.dataset.powerOnFollowupCommand, false)
                        .catch(function() { return null; });
                });
            })
            .then(function() {
                // Phase 5b: Also send fallback command (e.g. cec_power_on_tv) which may
                // include Active Source on some devices, providing an alternative input switch
                var fallbackReceivers = followupReceivers.filter(function(r) {
                    return Boolean(r.element.dataset.powerOnFollowupFallbackCommand);
                });
                if (fallbackReceivers.length === 0) return;
                return waitMs(1500);
            })
            .then(function() {
                var fallbackReceivers = followupReceivers.filter(function(r) {
                    return Boolean(r.element.dataset.powerOnFollowupFallbackCommand);
                });
                if (fallbackReceivers.length === 0) return;
                return sendCommandInBatches(fallbackReceivers, function(r) {
                    return sendPowerCommand(r.ip, r.element.dataset.powerOnFollowupFallbackCommand, false)
                        .catch(function() { return null; });
                });
            });
    });
}

function sendPowerCommandToAll(command, showNotification, options) {
    showNotification = showNotification !== undefined ? showNotification : true;
    options = options || {};
    var isPowerOn = command === 'cec_power_on_tv' || command === 'cec_tv_on.sh';

    // Collect eligible receivers — skip show_power=false devices entirely
    var eligibleReceivers = [];
    $('.receiver').each(function() {
        if (this.dataset.showPower === '0') return;

        // Skip repeat-disabled devices on second pass (e.g., toggle-only Roku displays)
        if (options.repeatPass && this.dataset.powerOnRepeat === '0') return;

        var deviceIp = $(this).data('ip') ||
                       $(this).find('input[name="receiver_ip"]').val() ||
                       $(this).find('.channel-select').data('ip') ||
                       $(this).find('.volume-slider').data('ip');
        if (deviceIp) {
            eligibleReceivers.push({ element: this, ip: deviceIp });
        }
    });

    if (isPowerOn) {
        // Use phased approach: power-on first, then follow-ups separately
        return sendPhasedPowerOn(eligibleReceivers, options);
    }

    // Power-off: per-device handling (simpler chain, less contention)
    return Promise.all(eligibleReceivers.map(function(r) {
        return sendConfiguredPowerOff(r.element, r.ip, false);
    }));
}

// Response message handler
function showResponseMessage(message, success) {
    const responseElement = $('#response-message');
    if (!responseElement.length) return; // Skip if element doesn't exist
    
    responseElement
        .removeClass('success error')
        .addClass(success ? 'success' : 'error')
        .text(message)
        .fadeIn();

    setTimeout(() => responseElement.fadeOut(), 3000);
}

// Remote Control Functions
function loadTransmitters() {
    compatFetch('transmitters.txt', { timeout: 4000 })
        .then(response => response.text())
        .then(data => {
            const transmitters = data.split('\n').filter(line => line.trim() !== '');
            
            const select = document.createElement('select');
            select.id = 'transmitter';
            
            transmitters.forEach(transmitter => {
                const [name, url] = transmitter.split(',').map(item => item.trim());
                const option = document.createElement('option');
                option.value = url;
                option.textContent = name;
                select.appendChild(option);
            });
            
            const container = document.getElementById('transmitter-select');
            if (container) {
                container.innerHTML = 'Select Transmitter: ';
                container.appendChild(select);
            }
        })
        .catch(error => {
            console.error('Error loading transmitters:', error);
            showError('Failed to load transmitters');
        });
}

// Track in-flight remote commands to prevent double-fire from rapid/ghost taps
// in the LiveCode browser widget. Key: `${transmitter}|${action}`.
const _remoteInFlight = new Map();
const REMOTE_DEBOUNCE_MS = 350;
const REMOTE_TIMEOUT_MS = 5000;

function sendCommand(action) {
    const transmitter = document.getElementById('transmitter');
    if (!transmitter || !transmitter.value) {
        showError('Please select a transmitter');
        return Promise.resolve({ success: false, skipped: true });
    }

    // Snapshot the transmitter URL at tap time so a mid-flight dropdown change
    // can't redirect the in-flight request to a different device.
    const deviceUrl = transmitter.value;
    const key = deviceUrl + '|' + action;
    const now = Date.now();
    const last = _remoteInFlight.get(key);
    if (last && (now - last) < REMOTE_DEBOUNCE_MS) {
        // Duplicate tap within debounce window — silently drop.
        return Promise.resolve({ success: true, skipped: true });
    }
    _remoteInFlight.set(key, now);

    return new Promise(function(resolve) {
        $.ajax({
            url: '', // Use current page - handled by BaseController
            type: 'POST',
            data: {
                device_url: deviceUrl,
                action: action
            },
            dataType: 'json',
            timeout: REMOTE_TIMEOUT_MS
        }).then(function(response) {
            if (response && response.success) {
                showResponseMessage('Command sent: ' + action, true);
                resolve({ success: true });
            } else {
                showResponseMessage((response && response.message) || 'Command failed', false);
                resolve({ success: false });
            }
        }).fail(function(jqXHR, textStatus) {
            // IR is fire-and-forget on the server: timeouts / empty replies
            // do NOT mean the command failed (see shared/api.php and
            // BaseController::handleRemoteControlRequest). Treat them as sent.
            if (textStatus === 'timeout' || (jqXHR && jqXHR.status === 0 && textStatus !== 'abort')) {
                showResponseMessage('Command sent: ' + action, true);
                resolve({ success: true, softFail: textStatus });
            } else {
                showResponseMessage('Failed to send command', false);
                console.error('Remote command request failed:', textStatus);
                resolve({ success: false });
            }
        }).always(function() {
            // Release the debounce slot shortly after — keeps single-key
            // repeats (e.g. holding CH+) working while still blocking double-fires.
            setTimeout(function() {
                if (_remoteInFlight.get(key) === now) {
                    _remoteInFlight.delete(key);
                }
            }, REMOTE_DEBOUNCE_MS);
        });
    });
}

function showError(message) {
    const errorElement = document.getElementById('error-message');
    if (!errorElement) return; // Skip if element doesn't exist

    const errorTextElement = document.getElementById('error-text');
    if (errorTextElement) {
        errorTextElement.textContent = message;
    }
    errorElement.style.display = 'block';
    announce(message);

    setTimeout(() => {
        errorElement.style.display = 'none';
    }, 5000);
}

// Send a channel number by pressing each digit sequentially.
// Each digit waits for the previous POST to finish, then a fixed gap,
// before the next digit is sent. This guarantees in-order delivery even
// when the LiveCode browser widget's XHR latency spikes.
function sendChannelNumber(channelNumber) {
    const digits = channelNumber.toString().split('');
    const gapMs = 800; // gap between one digit's response and the next digit's send
    let chain = Promise.resolve();
    digits.forEach((digit, index) => {
        chain = chain.then(() => sendCommand(digit)).then(() => {
            if (index < digits.length - 1) {
                return new Promise(r => setTimeout(r, gapMs));
            }
        });
    });
    return chain;
}

// Load favorite channels if available
function loadFavoriteChannels() {
    const container = document.getElementById('favorite-channels-select');
    if (!container) return;

    const pathCandidates = [
        'favorites.ini',
        './favorites.ini',
        `${window.location.pathname.replace(/[^/]*$/, '')}favorites.ini`
    ];

    function fetchFavorites(pathIndex) {
        if (pathIndex >= pathCandidates.length) {
            throw new Error('Unable to load favorites.ini from known paths');
        }

        const path = pathCandidates[pathIndex];
        return compatFetch(path, { timeout: 3000 })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status} from ${path}`);
                return response.text();
            })
            .catch(() => fetchFavorites(pathIndex + 1));
    }

    fetchFavorites(0)
        .then(data => {
            const favorites = [];
            const lines = data
                .split(/\r?\n/)
                .map(line => line.trim())
                .filter(line => line && !line.startsWith('[') && !line.startsWith(';'));

            lines.forEach(line => {
                const separatorIndex = line.indexOf('=');
                if (separatorIndex === -1) return;

                const key = line.slice(0, separatorIndex).trim().replace(/"/g, '');
                const value = line.slice(separatorIndex + 1).trim().replace(/"/g, '');
                if (key && value) {
                    favorites.push({ key, value });
                }
            });

            if (favorites.length === 0) {
                container.textContent = 'Favorite Channels: None configured';
                return;
            }

            const wrapper = document.createElement('div');
            const label = document.createElement('label');
            label.setAttribute('for', 'favorites');
            label.textContent = 'Quick Channels: ';
            wrapper.appendChild(label);

            const select = document.createElement('select');
            select.id = 'favorites';
            select.setAttribute('aria-label', 'Quick channel selection');
            const defaultOpt = document.createElement('option');
            defaultOpt.value = '';
            defaultOpt.textContent = 'Select a favorite...';
            select.appendChild(defaultOpt);
            favorites.forEach(fav => {
                const opt = document.createElement('option');
                opt.value = fav.key;
                opt.textContent = fav.value;
                select.appendChild(opt);
            });
            wrapper.appendChild(select);
            container.innerHTML = '';
            container.appendChild(wrapper);

            // Handle favorite selection - send each digit sequentially
            document.getElementById('favorites').addEventListener('change', function() {
                if (this.value) {
                    sendChannelNumber(this.value);
                    this.value = ''; // Reset selection
                }
            });
        })
        .catch(error => {
            console.error('Favorites loading failed:', error);
            container.textContent = 'Favorite Channels: Failed to load';
        });
}

// ============================================================================
// LAZY LOADING RECEIVERS
// ============================================================================

/**
 * Lazy-load receiver status for all receivers on the page
 * Fetches status asynchronously after page load for better UX
 */
function lazyLoadReceivers() {
    const receivers = document.querySelectorAll('.receiver.receiver-loading');
    if (receivers.length === 0) return;

    // Get transmitters data from the page (injected by PHP)
    const transmitters = window.TRANSMITTERS || {};

    // Load each receiver's status
    receivers.forEach(receiver => {
        const ip = receiver.dataset.ip;
        if (!ip) return;

        loadReceiverStatus(receiver, ip, transmitters);
    });
}

/**
 * Fetch and populate a single receiver's status
 */
function loadReceiverStatus(receiverElement, ip, transmitters) {
    const contentDiv = receiverElement.querySelector('.receiver-content');
    if (!contentDiv) return;

    // Fetch status from API with timeout to prevent hanging on unreachable devices
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 8000); // 8s timeout

    compatFetch(`../api/receiver-status.php?ip=${encodeURIComponent(ip)}`, { signal: controller.signal })
        .then(response => { clearTimeout(timeoutId); return response.json(); })
        .then(data => {
            receiverElement.classList.remove('receiver-loading');

            if (!data.success) {
                // Device unreachable
                contentDiv.innerHTML = `
                    <p style="text-align: center; color: #ff6b6b; padding: 1rem;">
                        Device unreachable. Please check connection.
                    </p>`;
                return;
            }

            // Get receiver settings from data attributes
            const name = receiverElement.dataset.name || 'Receiver';
            const minVolume = parseInt(receiverElement.dataset.minVolume) || 0;
            const maxVolume = parseInt(receiverElement.dataset.maxVolume) || 11;
            const volumeStep = parseInt(receiverElement.dataset.volumeStep) || 1;
            const showPower = receiverElement.dataset.showPower === '1';
            const powerOffCommand = receiverElement.dataset.powerOffCommand || 'cec_tv_off.sh';

            // Build the controls HTML
            let html = '';

            // Channel selector
            html += `<label for="channel_${name}">Channel:</label>`;
            html += `<select id="channel_${name}" class="channel-select" data-ip="${ip}">`;
            for (const [transmitterName, channelNumber] of Object.entries(transmitters)) {
                const selected = (channelNumber == data.channel) ? ' selected' : '';
                html += `<option value="${channelNumber}"${selected}>${escapeHtml(transmitterName)}</option>`;
            }
            html += '</select>';

            // Volume slider if supported
            if (data.supportsVolume) {
                const volume = data.volume !== null ? data.volume : minVolume;
                html += `<label for="volume_${name}">Volume:</label>`;
                html += `<input type="range" id="volume_${name}" class="volume-slider" data-ip="${ip}" min="${minVolume}" max="${maxVolume}" step="${volumeStep}" value="${volume}">`;
                html += `<span class="volume-label">${volume}</span>`;
            }

            // Power buttons
            if (showPower) {
                html += '<div class="power-buttons">';
                html += `<button type="button" class="power-on" onclick="sendConfiguredPowerOn(this.closest('.receiver'), '${ip}', true, { repeatPass: true })">Power On</button>`;
                html += `<button type="button" class="power-off" onclick="sendConfiguredPowerOff(this.closest('.receiver'), '${ip}')">Power Off</button>`;
                html += '</div>';
            }

            contentDiv.innerHTML = html;
        })
        .catch(error => {
            clearTimeout(timeoutId);
            console.error('Error loading receiver status:', error);
            receiverElement.classList.remove('receiver-loading');
            const isTimeout = error.name === 'AbortError';
            contentDiv.innerHTML = `
                <p style="text-align: center; color: #ff6b6b; padding: 1rem;">
                    ${isTimeout ? 'Device timed out. Please check connection.' : 'Failed to load receiver status.'}
                </p>`;
        });
}

/**
 * Escape HTML special characters
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================================================
// CROSS-BROWSER POLLING
// ----------------------------------------------------------------------------
// Keeps receiver controls in sync across several PCs viewing the same zone.
// Every RECEIVER_POLL_INTERVAL_MS the client re-fetches receiver status and
// updates the UI only when: the element is not focused by the local user AND
// the local user has not written to that receiver in the last
// POLL_SKIP_AFTER_WRITE_MS.  The server-side 5s cache in receiver-status.php
// collapses the traffic so N polling PCs * M receivers stays manageable.
// ============================================================================

const RECEIVER_POLL_INTERVAL_MS = 7000;
const POLL_SKIP_AFTER_WRITE_MS = 3000;
let _pollTimer = null;
let _pollAbortController = null;

function markReceiverWritten(receiverElement) {
    if (receiverElement) {
        receiverElement._lastWriteTime = Date.now();
    }
}

function startReceiverPolling() {
    if (_pollTimer) return;
    _pollTimer = setInterval(pollAllReceivers, RECEIVER_POLL_INTERVAL_MS);
}

function stopReceiverPolling() {
    if (_pollTimer) {
        clearInterval(_pollTimer);
        _pollTimer = null;
    }
    if (_pollAbortController) {
        try { _pollAbortController.abort(); } catch (e) { /* ignore */ }
        _pollAbortController = null;
    }
}

function pollAllReceivers() {
    if (document.visibilityState === 'hidden') return;

    const receivers = document.querySelectorAll('.receiver:not(.receiver-loading)');
    if (receivers.length === 0) return;

    // Abort any still-inflight requests from the previous cycle — fresher
    // data is only RECEIVER_POLL_INTERVAL_MS away so there is no value in
    // hanging onto stale promises.
    if (_pollAbortController) {
        try { _pollAbortController.abort(); } catch (e) { /* ignore */ }
    }
    _pollAbortController = (typeof AbortController !== 'undefined') ? new AbortController() : null;
    const signal = _pollAbortController ? _pollAbortController.signal : undefined;

    receivers.forEach(function(receiver) {
        const ip = receiver.dataset.ip;
        if (!ip) return;
        pollReceiver(receiver, ip, signal);
    });
}

function pollReceiver(receiverElement, ip, signal) {
    // Skip polling updates shortly after a local write.  The remote device
    // may still be transitioning, so trusting the poll value would flicker
    // the UI back to the old state.
    const lastWrite = receiverElement._lastWriteTime || 0;
    if (Date.now() - lastWrite < POLL_SKIP_AFTER_WRITE_MS) return;

    const fetchOpts = signal ? { signal: signal } : {};
    compatFetch('../api/receiver-status.php?ip=' + encodeURIComponent(ip), fetchOpts)
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (!data || !data.success) return;

            // Re-check the write-skip window — another handler may have run
            // between dispatch and response completion.
            if (Date.now() - (receiverElement._lastWriteTime || 0) < POLL_SKIP_AFTER_WRITE_MS) {
                return;
            }

            // Channel: only swap if the option exists so we never clear the
            // dropdown when transmitters.txt disagrees with the device.
            const channelSelect = receiverElement.querySelector('.channel-select');
            if (channelSelect && document.activeElement !== channelSelect && data.channel !== null) {
                const nextChannel = String(data.channel);
                if (channelSelect.value !== nextChannel) {
                    const hasOption = Array.prototype.some.call(
                        channelSelect.options,
                        function(opt) { return opt.value === nextChannel; }
                    );
                    if (hasOption) channelSelect.value = nextChannel;
                }
            }

            // Volume: skip if the slider is focused (being dragged) or if
            // the user has a pending debounced write on this receiver.
            const volumeSlider = receiverElement.querySelector('.volume-slider');
            if (volumeSlider && document.activeElement !== volumeSlider && data.volume !== null) {
                const nextVolume = String(data.volume);
                if (volumeSlider.value !== nextVolume) {
                    volumeSlider.value = nextVolume;
                    volumeSlider._lastConfirmedValue = nextVolume;
                    updateVolumeLabel(volumeSlider);
                }
            }
        })
        .catch(function(err) {
            // AbortError is expected when a new cycle cancels this one;
            // everything else is a transient network issue we swallow so the
            // polling loop never alerts the user.
            if (err && err.name !== 'AbortError') {
                /* no-op */
            }
        });
}

// Pause polling when the tab is hidden to avoid wasting bandwidth and
// device I/O on a browser nobody is looking at.  Resume on return.
document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'hidden') {
        stopReceiverPolling();
    } else {
        startReceiverPolling();
    }
});
