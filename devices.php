<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Device Directory - Castle AV Controls</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" onerror="this.remove()">
    <link rel="stylesheet" href="devices.css">
</head>
<body>
    <header class="dir-header">
        <div class="dir-header-inner">
            <a href="index.html" class="dir-home-link" title="Back to Home">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                </svg>
                Home
            </a>
            <div class="dir-title-group">
                <img src="logo.png" alt="Castle AV" class="dir-logo">
                <div>
                    <h1>Device Directory</h1>
                    <p class="dir-subtitle">All network devices and IP addresses</p>
                </div>
            </div>
            <div class="dir-search-wrap">
                <svg class="dir-search-icon" width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/>
                </svg>
                <input type="text" id="deviceSearch" placeholder="Search devices or IPs..." aria-label="Search devices">
            </div>
        </div>
    </header>

    <main class="dir-main">
        <div id="device-container">
            <div class="dir-loading">
                <div class="dir-spinner"></div>
                Loading device inventory...
            </div>
        </div>
    </main>

    <footer class="dir-footer">
        <span id="device-count"></span>
        <span id="last-updated"></span>
    </footer>

    <script>
    (function() {
        'use strict';

        var container = document.getElementById('device-container');
        var searchInput = document.getElementById('deviceSearch');
        var countEl = document.getElementById('device-count');
        var updatedEl = document.getElementById('last-updated');

        // Category icons (inline SVGs)
        var icons = {
            'monitor': '<svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.489.27.764-1.522 1.508A.75.75 0 0111.13 18H8.87a.75.75 0 01-.519-.239l-1.522-1.508.27-.764.124-.489H5a2 2 0 01-2-2V5zm12 0H5v8h10V5z" clip-rule="evenodd"/></svg>',
            'broadcast': '<svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.05 3.636a1 1 0 010 1.414 7 7 0 000 9.9 1 1 0 11-1.414 1.414 9 9 0 010-12.728 1 1 0 011.414 0zm9.9 0a1 1 0 011.414 0 9 9 0 010 12.728 1 1 0 11-1.414-1.414 7 7 0 000-9.9 1 1 0 010-1.414zM7.879 6.464a1 1 0 010 1.414 3 3 0 000 4.243 1 1 0 11-1.415 1.414 5 5 0 010-7.07 1 1 0 011.415 0zm4.242 0a1 1 0 011.415 0 5 5 0 010 7.072 1 1 0 01-1.415-1.415 3 3 0 000-4.242 1 1 0 010-1.415zM10 9a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/></svg>',
            'lightbulb': '<svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path d="M11 3a1 1 0 10-2 0v1a1 1 0 102 0V3zM15.657 5.757a1 1 0 00-1.414-1.414l-.707.707a1 1 0 001.414 1.414l.707-.707zM18 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1zM5.05 6.464A1 1 0 106.464 5.05l-.707-.707a1 1 0 00-1.414 1.414l.707.707zM4 11a1 1 0 100-2H3a1 1 0 000 2h1zM10 18a3 3 0 003-3h-1a2 2 0 11-4 0H7a3 3 0 003 3zM10 6a4 4 0 00-4 4c0 1.1.45 2.1 1.17 2.83l.42.42A1 1 0 018 14h4a1 1 0 01.41-.75l.42-.42A4 4 0 0010 6z"/></svg>',
            'server': '<svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 5a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 01-2 2H4a2 2 0 01-2-2V5zm14 1a1 1 0 11-2 0 1 1 0 012 0zM2 13a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 01-2 2H4a2 2 0 01-2-2v-2zm14 1a1 1 0 11-2 0 1 1 0 012 0z" clip-rule="evenodd"/></svg>',
            'printer': '<svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd"/></svg>'
        };

        var categoryColors = {
            'av-receivers': '#6366f1',
            'transmitters': '#f59e0b',
            'wled': '#10b981',
            'infrastructure': '#22d3ee',
            'printers': '#8b5cf6'
        };

        function buildDeviceUrl(device) {
            if (!device.ip) return null;
            if (device.url) {
                var url = device.url;
                if (url.indexOf('://') === -1) url = 'http://' + url;
                return url;
            }
            return 'http://' + device.ip;
        }

        function renderDevices(data) {
            var devices = data.devices || [];
            var categories = data.categories || [];

            if (devices.length === 0) {
                container.innerHTML = '<p class="dir-empty">No devices found.</p>';
                return;
            }

            // Group by category
            var grouped = {};
            categories.forEach(function(cat) { grouped[cat.id] = { info: cat, devices: [] }; });
            devices.forEach(function(d) {
                var cat = d.category || 'infrastructure';
                if (!grouped[cat]) grouped[cat] = { info: { id: cat, name: cat, icon: 'server' }, devices: [] };
                grouped[cat].devices.push(d);
            });

            var html = '';
            var totalDevices = 0;

            categories.forEach(function(cat) {
                var group = grouped[cat.id];
                if (!group || group.devices.length === 0) return;

                var color = categoryColors[cat.id] || '#6366f1';
                var icon = icons[cat.icon] || icons['server'];

                html += '<section class="dir-category" data-category="' + cat.id + '">';
                html += '<div class="dir-category-header" style="border-left-color: ' + color + '">';
                html += '<span class="dir-category-icon" style="color: ' + color + '">' + icon + '</span>';
                html += '<h2>' + escapeHtml(cat.name) + '</h2>';
                html += '<span class="dir-badge" style="background: ' + color + '">' + group.devices.length + '</span>';
                html += '</div>';
                html += '<div class="dir-device-grid">';

                group.devices.forEach(function(device) {
                    totalDevices++;
                    var url = buildDeviceUrl(device);
                    var isLogical = device.type === 'logical';

                    html += '<div class="dir-device-card" data-name="' + escapeHtml(device.name).toLowerCase() + '" data-ip="' + (device.ip || '') + '">';
                    html += '<div class="dir-device-name">' + escapeHtml(device.name) + '</div>';

                    if (device.ip) {
                        html += '<a href="' + url + '" target="_blank" rel="noopener" class="dir-device-ip" title="Open ' + escapeHtml(device.ip) + ' in new tab">';
                        html += device.ip;
                        html += '<svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor" class="dir-ext-icon"><path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/><path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/></svg>';
                        html += '</a>';
                    } else if (device.channel !== undefined && device.channel !== null) {
                        html += '<span class="dir-device-channel">Channel ' + device.channel + '</span>';
                    }

                    if (device.zones && device.zones.length > 0) {
                        html += '<div class="dir-device-zones">';
                        device.zones.forEach(function(z) {
                            html += '<span class="dir-zone-tag">' + escapeHtml(z) + '</span>';
                        });
                        html += '</div>';
                    }

                    html += '</div>';
                });

                html += '</div></section>';
            });

            container.innerHTML = html;
            countEl.textContent = totalDevices + ' devices';
            updatedEl.textContent = 'Updated: ' + (data.generated || 'now');
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // Search / filter
        searchInput.addEventListener('input', function() {
            var query = this.value.toLowerCase().trim();
            var cards = document.querySelectorAll('.dir-device-card');
            var sections = document.querySelectorAll('.dir-category');

            cards.forEach(function(card) {
                var name = card.getAttribute('data-name') || '';
                var ip = card.getAttribute('data-ip') || '';
                card.style.display = (name.indexOf(query) !== -1 || ip.indexOf(query) !== -1) ? '' : 'none';
            });

            // Hide empty sections
            sections.forEach(function(section) {
                var visibleCards = section.querySelectorAll('.dir-device-card:not([style*="display: none"])');
                section.style.display = visibleCards.length > 0 ? '' : 'none';
            });
        });

        // Load data
        fetch('api/devices.php')
            .then(function(r) {
                if (!r.ok) throw new Error('Failed to load');
                return r.json();
            })
            .then(renderDevices)
            .catch(function(err) {
                console.error('Device directory error:', err);
                container.innerHTML = '<div class="dir-error">Failed to load device inventory. <button onclick="location.reload()">Retry</button></div>';
            });
    })();
    </script>
</body>
</html>
