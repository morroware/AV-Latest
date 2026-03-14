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

    // Debounced auto-submit for volume changes
    let volumeTimeout = null;
    $(document).on('input', '.volume-slider, input[type="range"].auto-submit', function() {
        const slider = this;
        const $slider = $(slider);
        const receiverCard = $slider.closest('.receiver');
        const deviceIp = $slider.data('ip') || receiverCard.data('ip');

        // Update volume label immediately
        updateVolumeLabel(slider);

        // Debounce volume changes to avoid too many requests
        if (volumeTimeout) {
            clearTimeout(volumeTimeout);
        }

        volumeTimeout = setTimeout(() => {
            receiverCard.addClass('updating');

            const data = new FormData();
            data.append('receiver_ip', deviceIp);
            data.append('volume', slider.value);

            $.ajax({
                url: '',
                type: 'POST',
                data: data,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    // Only show message on error - volume feedback is visual
                    if (!response.success) {
                        showResponseMessage(response.message || 'Volume update failed', false);
                    }
                    announce(`Volume set to ${slider.value}`);
                },
                error: function() {
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

function sendConfiguredPowerOn(receiverElement, deviceIp, showNotification = true, options = {}) {
    const powerOnCommand = receiverElement.dataset.powerOnCommand || 'cec_tv_on.sh';
    const followupCommand = receiverElement.dataset.powerOnFollowupCommand;
    const followupFallbackCommand = receiverElement.dataset.powerOnFollowupFallbackCommand;
    const followupDelayMs = parseInt(receiverElement.dataset.powerOnFollowupDelayMs, 10) || 5000;
    const receiverRepeatsPowerOn = receiverElement.dataset.powerOnRepeat !== '0';
    const shouldSendFollowup = Boolean(followupCommand) && (!receiverRepeatsPowerOn || options.repeatPass);

    // Some displays may still react to power-on even when the HTTP request itself fails/times out.
    // Keep the sequence resilient by attempting the follow-up command regardless.
    return sendPowerCommand(deviceIp, powerOnCommand, showNotification)
        .catch(function(error) {
            console.warn('Power-on command request failed, continuing with follow-up if configured:', error);
            return null;
        })
        .then(function(response) {
            if (!shouldSendFollowup) {
                return response;
            }

            return waitMs(Math.max(0, followupDelayMs))
                .then(() => sendPowerCommand(deviceIp, followupCommand, false)
                    .catch(function(error) {
                        if (!followupFallbackCommand) {
                            throw error;
                        }

                        console.warn('Primary follow-up command failed, trying fallback command:', error);
                        return sendPowerCommand(deviceIp, followupFallbackCommand, false);
                    }))
                .catch(function(error) {
                    console.warn('Power-on follow-up sequence failed:', error);
                    return response;
                })
                .then(() => response);
        });
}

function sendConfiguredPowerOff(receiverElement, deviceIp, showNotification = true) {
    const powerOffCommand = receiverElement.dataset.powerOffCommand || 'cec_tv_off.sh';
    const preCommand = receiverElement.dataset.powerOffPreCommand;
    const preDelayMs = parseInt(receiverElement.dataset.powerOffPreDelayMs, 10) || 3000;

    if (!preCommand) {
        return sendPowerCommand(deviceIp, powerOffCommand, showNotification);
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
            return sendPowerCommand(deviceIp, powerOffCommand, showNotification);
        });
}

function resolvePowerCommand(receiverElement, fallbackCommand) {
    const isPowerOn = fallbackCommand === 'cec_power_on_tv' || fallbackCommand === 'cec_tv_on.sh';
    const command = isPowerOn
        ? receiverElement.dataset.powerOnCommand
        : receiverElement.dataset.powerOffCommand;

    return command || fallbackCommand;
}

function sendPowerCommandToAll(command, showNotification = true, options = {}) {
    const receivers = $('.receiver');
    let promises = [];

    receivers.each(function() {
        const powerCommand = resolvePowerCommand(this, command);
        const isPowerOn = command === 'cec_power_on_tv' || command === 'cec_tv_on.sh';

        // Skip second-pass power-on for receivers configured without repeat (e.g., toggle-only displays)
        if (options.repeatPass && this.dataset.powerOnRepeat === '0') {
            return;
        }

        // Try multiple ways to get the device IP for compatibility
        let deviceIp = $(this).data('ip') ||
                       $(this).find('input[name="receiver_ip"]').val() ||
                       $(this).find('.channel-select').data('ip') ||
                       $(this).find('.volume-slider').data('ip');
        if (deviceIp) {
            if (isPowerOn) {
                promises.push(sendConfiguredPowerOn(this, deviceIp, false, { repeatPass: options.repeatPass === true }));
            } else {
                promises.push(sendConfiguredPowerOff(this, deviceIp, false));
            }
        }
    });

    return Promise.all(promises);
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
    compatFetch('transmitters.txt')
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

function sendCommand(action) {
    const transmitter = document.getElementById('transmitter');
    if (!transmitter || !transmitter.value) {
        showError('Please select a transmitter');
        return;
    }

    $.ajax({
        url: '', // Use current page - handled by BaseController
        type: 'POST',
        data: {
            device_url: transmitter.value,
            action: action
        },
        dataType: 'json'
    }).then(function(response) {
        if (response.success) {
            showResponseMessage('Command sent: ' + action, true);
        } else {
            showResponseMessage(response.message || 'Command failed', false);
        }
    }).fail(function(jqXHR, textStatus) {
        showResponseMessage('Failed to send command', false);
        console.error('Remote command request failed:', textStatus);
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

// Send a channel number by pressing each digit sequentially
function sendChannelNumber(channelNumber) {
    const digits = channelNumber.toString().split('');
    const delay = 1000; // 1000ms between each digit press (cable boxes need time to register each digit)

    digits.forEach((digit, index) => {
        setTimeout(() => {
            sendCommand(digit);
        }, index * delay);
    });
}

// Load favorite channels if available
function loadFavoriteChannels() {
    compatFetch('favorites.ini')
        .then(response => {
            if (!response.ok) return null;
            return response.text();
        })
        .then(data => {
            if (!data) return;

            const container = document.getElementById('favorite-channels-select');
            if (!container) return;

            const favorites = [];
            const lines = data.split('\n').filter(line => line.trim() && !line.startsWith('[') && !line.startsWith(';'));

            lines.forEach(line => {
                const [key, value] = line.split('=').map(s => s.trim().replace(/"/g, ''));
                if (key && value) {
                    favorites.push({ key, value });
                }
            });

            if (favorites.length === 0) {
                container.style.display = 'none';
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
            console.log('No favorites file or error loading:', error);
            const container = document.getElementById('favorite-channels-select');
            if (container) container.style.display = 'none';
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

    // Fetch status from API
    compatFetch(`../api/receiver-status.php?ip=${encodeURIComponent(ip)}`)
        .then(response => response.json())
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
            console.error('Error loading receiver status:', error);
            receiverElement.classList.remove('receiver-loading');
            contentDiv.innerHTML = `
                <p style="text-align: center; color: #ff6b6b; padding: 1rem;">
                    Failed to load receiver status.
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
