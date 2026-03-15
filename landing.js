/**
 * Castle AV Controls - Landing Page Logic
 * Handles zone loading, color utilities, and dynamic navigation.
 * Reads configuration from site-config.json and zones from api/zones.php.
 */

(function () {
    'use strict';

    // ---- Color Utilities ----

    function getBrightness(hexColor) {
        var hex = hexColor.replace('#', '');
        var r = parseInt(hex.substr(0, 2), 16);
        var g = parseInt(hex.substr(2, 2), 16);
        var b = parseInt(hex.substr(4, 2), 16);
        return (r * 299 + g * 587 + b * 114) / 1000;
    }

    function adjustColor(hexColor, amount) {
        var hex = hexColor.replace('#', '');
        var r = Math.max(0, Math.min(255, parseInt(hex.substr(0, 2), 16) + amount));
        var g = Math.max(0, Math.min(255, parseInt(hex.substr(2, 2), 16) + amount));
        var b = Math.max(0, Math.min(255, parseInt(hex.substr(4, 2), 16) + amount));
        return '#' + [r, g, b].map(function (x) { return x.toString(16).padStart(2, '0'); }).join('');
    }

    function hexToRgba(hexColor, alpha) {
        var hex = hexColor.replace('#', '');
        var r = parseInt(hex.substr(0, 2), 16);
        var g = parseInt(hex.substr(2, 2), 16);
        var b = parseInt(hex.substr(4, 2), 16);
        return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
    }

    // ---- Zone Rendering ----

    function createZoneButton(zone) {
        var link = document.createElement('a');
        link.href = zone.id + '/';
        link.className = 'button';
        link.textContent = zone.name || zone.id;
        link.setAttribute('role', 'button');
        link.setAttribute('aria-label', 'Open ' + (zone.name || zone.id) + ' zone controls');
        if (zone.description) {
            link.setAttribute('title', zone.description);
        }
        if (zone.color) {
            link.style.background = 'linear-gradient(135deg, ' + zone.color + ' 0%, ' + adjustColor(zone.color, -20) + ' 100%)';
            link.style.color = getBrightness(zone.color) > 128 ? '#0a0a0f' : '#ffffff';
            link.style.boxShadow = '0 4px 15px ' + hexToRgba(zone.color, 0.3);
        }
        return link;
    }

    function createSpecialLink(linkData) {
        var a = document.createElement('a');
        a.href = linkData.url;
        a.className = 'button special-link';
        a.textContent = linkData.name;
        a.setAttribute('role', 'button');
        if (linkData.description) {
            a.setAttribute('title', linkData.description);
        }
        if (linkData.openInNewTab) {
            a.setAttribute('target', '_blank');
            a.setAttribute('rel', 'noopener noreferrer');
        }
        if (linkData.color) {
            a.style.background = 'linear-gradient(135deg, ' + linkData.color + ' 0%, ' + adjustColor(linkData.color, -20) + ' 100%)';
            a.style.color = getBrightness(linkData.color) > 128 ? '#0a0a0f' : '#ffffff';
            a.style.boxShadow = '0 4px 15px ' + hexToRgba(linkData.color, 0.3);
        }
        return a;
    }

    // ---- Load Zones from API ----

    function loadZones() {
        var container = document.getElementById('zone-buttons');
        if (!container) return;
        container.innerHTML = '<span class="loading">Loading zones...</span>';

        var fetchFn = (window.LiveCodeCompat && window.LiveCodeCompat.fetch) || fetch;

        var controller, timeoutId;
        try {
            controller = new AbortController();
            timeoutId = setTimeout(function () { controller.abort(); }, 10000);
        } catch (e) {
            controller = { signal: null };
            timeoutId = null;
        }

        fetchFn('api/zones.php', { signal: controller.signal, timeout: 10000 })
            .then(function (response) {
                if (timeoutId) clearTimeout(timeoutId);
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(function (data) {
                container.innerHTML = '';

                if (data.zones && data.zones.length > 0) {
                    data.zones.forEach(function (zone) {
                        container.appendChild(createZoneButton(zone));
                    });
                } else {
                    container.innerHTML = '<p class="help-text">No zones configured. Use Zone Manager to add zones.</p>';
                }

                // Special links (quick links)
                if (data.specialLinks && data.specialLinks.length > 0) {
                    data.specialLinks.forEach(function (link) {
                        container.appendChild(createSpecialLink(link));
                    });
                }

                // Device Directory link is already in the admin footer below the grid
            })
            .catch(function (error) {
                if (timeoutId) clearTimeout(timeoutId);
                var isTimeout = error.name === 'AbortError' || error.message === 'Request timeout';
                console.error('Failed to load zones:', isTimeout ? 'Request timed out' : error);
                container.innerHTML =
                    '<div class="error-loading">' +
                    '<p>' + (isTimeout ? 'Loading zones timed out.' : 'Failed to load zones.') + ' Using default zones.</p>' +
                    '<button class="retry-btn" onclick="window.__loadZones()">Retry</button>' +
                    '</div>' +
                    '<a href="bowling/" class="button">Bowling</a>' +
                    '<a href="bowlingbar/" class="button">Bowling Bar</a>' +
                    '<a href="rink/" class="button">Rink</a>' +
                    '<a href="jesters/" class="button">Jesters</a>' +
                    '<a href="facility/" class="button">Facility</a>' +
                    '<a href="outside/" class="button">Outside</a>' +
                    '<a href="all/" class="button">ALL</a>' +
                    '<a href="multi/" class="button">Multi</a>' +
                    '<a href="devices/" class="button special-link">Device Directory</a>';
            });
    }

    // Expose for retry button
    window.__loadZones = loadZones;

    // ---- Observe Container Visibility ----

    function watchForContainerVisibility() {
        var mainContainer = document.querySelector('.container');
        if (!mainContainer) return;

        // Load immediately if already visible
        if (mainContainer.style.display !== 'none') {
            loadZones();
            return;
        }

        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                    if (mainContainer.style.display !== 'none') {
                        loadZones();
                        observer.disconnect();
                    }
                }
            });
        });

        observer.observe(mainContainer, { attributes: true });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', watchForContainerVisibility);
    } else {
        watchForContainerVisibility();
    }
})();
