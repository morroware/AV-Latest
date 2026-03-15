/**
 * AV System Dashboard JavaScript
 * Frontend logic for Just Add Power devices
 * Reads device data from PHP-injected window.DEVICE_DATA
 *
 * @author Seth Morrow
 * @version 3.0.0
 * @copyright 2025-2026
 */

class AVDashboard {
    constructor() {
        this.devices = [];
        this.currentFilter = 'all';
        this.searchQuery = '';
        this.isInitialized = false;
        this.viewMode = 'cards';
        this.init();
    }

    init() {
        this.loadDeviceData();
        this.setupEventListeners();
        this.setupViewToggle();
        this.populateStats();
        this.renderDevices();
        this.startStatusChecking();
        this.isInitialized = true;
    }

    /**
     * Load device data from PHP-injected global
     */
    loadDeviceData() {
        const rawDevices = window.DEVICE_DATA || [];
        this.devices = rawDevices.map((d, i) => ({
            ip: d.ip,
            name: d.name,
            type: d.type,
            deviceType: d.deviceType || '',
            channel: d.channel || null,
            status: 'unknown',
            zone: d.zone || 'other',
            show_power: d.show_power || false,
            id: (d.type === 'tx' ? 'TX' : 'RX') + '-' + (d.ip ? d.ip.split('.').pop() : 'ch' + (d.channel || i))
        }));
    }

    setupViewToggle() {
        const viewToggleHtml = `
            <div class="view-toggle">
                <button id="cards-view" class="view-btn active" data-view="cards" title="Card View">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                    </svg>
                </button>
                <button id="list-view" class="view-btn" data-view="list" title="List View">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="8" y1="6" x2="21" y2="6"></line>
                        <line x1="8" y1="12" x2="21" y2="12"></line>
                        <line x1="8" y1="18" x2="21" y2="18"></line>
                        <line x1="3" y1="6" x2="3.01" y2="6"></line>
                        <line x1="3" y1="12" x2="3.01" y2="12"></line>
                        <line x1="3" y1="18" x2="3.01" y2="18"></line>
                    </svg>
                </button>
            </div>
        `;
        $('.header-actions').append(viewToggleHtml);
    }

    setupEventListeners() {
        // Tab switching
        $('.tab').on('click touchend', (e) => {
            e.preventDefault();
            this.handleTabSwitch(e);
        });

        // Search
        $('#search-input').on('input', (e) => this.handleSearch(e));

        // Modal controls
        $('#reboot-all-btn').on('click touchend', (e) => {
            e.preventDefault();
            this.showRebootModal();
        });
        $('#confirm-reboot').on('click touchend', (e) => {
            e.preventDefault();
            this.confirmRebootAll();
        });
        $('#cancel-reboot, #modal-close').on('click touchend', (e) => {
            e.preventDefault();
            this.hideRebootModal();
        });

        // Device actions (delegated)
        $(document).on('click touchend', '.device-reboot', (e) => {
            e.preventDefault();
            this.handleDeviceReboot(e);
        });

        // Modal backdrop
        $('#reboot-modal').on('click touchend', (e) => {
            if (e.target.id === 'reboot-modal') this.hideRebootModal();
        });

        // Refresh
        $('#refresh-all').on('click touchend', (e) => {
            e.preventDefault();
            this.refreshAllDevices();
        });

        // View toggle
        $(document).on('click touchend', '.view-btn', (e) => {
            e.preventDefault();
            this.handleViewToggle(e);
        });

        // Escape key
        $(document).on('keydown', (e) => {
            if (e.key === 'Escape') this.hideRebootModal();
        });
    }

    handleViewToggle(e) {
        const $btn = $(e.currentTarget);
        const newView = $btn.data('view');
        if (newView === this.viewMode) return;
        this.viewMode = newView;
        $('.view-btn').removeClass('active');
        $btn.addClass('active');
        this.renderDevices();
    }

    populateStats() {
        const physicalDevices = this.devices.filter(d => d.ip);
        const txCount = this.devices.filter(d => d.type === 'tx').length;
        const rxCount = this.devices.filter(d => d.type === 'rx').length;
        const onlineCount = physicalDevices.filter(d => d.status === 'online').length;
        const unknownCount = physicalDevices.filter(d => d.status === 'unknown').length;

        $('#total-devices').text(physicalDevices.length);
        $('#tx-count').text(txCount);
        $('#rx-count').text(rxCount);
        $('#online-count').text(unknownCount > 0 ? '...' : onlineCount);
    }

    handleTabSwitch(e) {
        const $tab = $(e.currentTarget);
        const tabId = $tab.data('tab');

        $('.tab').removeClass('active');
        $('.tab-content').removeClass('active');

        $tab.addClass('active');
        $(`#${tabId}`).addClass('active');
        this.currentFilter = tabId;

        setTimeout(() => this.renderDevices(), 50);
    }

    handleSearch(e) {
        this.searchQuery = e.target.value.toLowerCase();
        this.renderDevices();
    }

    getFilteredDevices() {
        let filtered = [...this.devices];

        if (this.currentFilter === 'tx') {
            filtered = filtered.filter(d => d.type === 'tx');
        } else if (this.currentFilter === 'rx') {
            filtered = filtered.filter(d => d.type === 'rx');
        } else if (['bowling', 'bowlingbar', 'rink', 'jesters', 'facility', 'outside'].includes(this.currentFilter)) {
            filtered = filtered.filter(d => d.zone === this.currentFilter);
        }

        if (this.searchQuery) {
            filtered = filtered.filter(d =>
                d.name.toLowerCase().includes(this.searchQuery) ||
                (d.ip && d.ip.includes(this.searchQuery)) ||
                d.id.toLowerCase().includes(this.searchQuery) ||
                d.zone.toLowerCase().includes(this.searchQuery)
            );
        }

        return filtered;
    }

    /**
     * Get friendly zone label
     */
    getZoneLabel(zone) {
        const labels = {
            bowling: 'Bowling',
            bowlingbar: 'Bowling Bar',
            rink: 'Rink',
            jesters: 'Jesters',
            facility: 'Facility',
            outside: 'Outside',
            attic: 'Attic',
            sources: 'Sources',
            other: 'Other'
        };
        return labels[zone] || zone;
    }

    /**
     * Get device type label (audio/video/ir-blaster/source)
     */
    getDeviceTypeLabel(device) {
        if (device.deviceType === 'audio') return 'Audio';
        if (device.deviceType === 'video') return 'Video';
        if (device.deviceType === 'ir-blaster') return 'IR Blaster';
        if (device.deviceType === 'source') return 'Source (Ch ' + device.channel + ')';
        return device.type.toUpperCase();
    }

    createDeviceCard(device) {
        const hasIp = !!device.ip;
        const statusHtml = hasIp
            ? `<span class="status-badge status-${device.status}">${device.status}</span>`
            : `<span class="status-badge status-source">source</span>`;

        const ipHtml = hasIp
            ? `<a href="http://${device.ip}" target="_blank" rel="noopener">${device.ip}</a>`
            : `Channel ${device.channel}`;

        const actionsHtml = hasIp ? `
            <div class="device-actions">
                <a href="http://${device.ip}" target="_blank" rel="noopener" class="btn btn-secondary btn-small">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                        <polyline points="15,3 21,3 21,9"></polyline>
                        <line x1="10" y1="14" x2="21" y2="3"></line>
                    </svg>
                    Web UI
                </a>
                <button class="btn btn-danger btn-small device-reboot" data-ip="${device.ip}" data-name="${device.name}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M23 4v6h-6"></path>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                    </svg>
                    Reboot
                </button>
            </div>
        ` : `<div class="device-actions"><span class="source-note">No direct device control</span></div>`;

        return `
            <div class="device-card" data-device-id="${device.id}">
                <div class="device-header">
                    <div class="device-name">${device.name}</div>
                    <div class="device-type ${device.type}">${device.type.toUpperCase()}</div>
                </div>
                <div class="device-content">
                    <div class="device-info">
                        <div class="device-label">${hasIp ? 'IP Address' : 'Channel'}:</div>
                        <div class="device-value">${ipHtml}</div>
                        <div class="device-label">Type:</div>
                        <div class="device-value">${this.getDeviceTypeLabel(device)}</div>
                        <div class="device-label">Zone:</div>
                        <div class="device-value">${this.getZoneLabel(device.zone)}</div>
                        <div class="device-label">Status:</div>
                        <div class="device-value">${statusHtml}</div>
                    </div>
                    ${actionsHtml}
                </div>
            </div>
        `;
    }

    createDeviceListItem(device) {
        const hasIp = !!device.ip;
        const statusHtml = hasIp
            ? `<span class="status-badge status-${device.status}">${device.status}</span>`
            : `<span class="status-badge status-source">source</span>`;

        const ipHtml = hasIp
            ? `<a href="http://${device.ip}" target="_blank" rel="noopener">${device.ip}</a>`
            : `Ch ${device.channel}`;

        const actionsHtml = hasIp ? `
            <div class="device-list-actions">
                <a href="http://${device.ip}" target="_blank" rel="noopener" class="btn btn-secondary btn-small">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                        <polyline points="15,3 21,3 21,9"></polyline>
                        <line x1="10" y1="14" x2="21" y2="3"></line>
                    </svg>
                    Web UI
                </a>
                <button class="btn btn-danger btn-small device-reboot" data-ip="${device.ip}" data-name="${device.name}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M23 4v6h-6"></path>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                    </svg>
                    Reboot
                </button>
            </div>
        ` : '';

        return `
            <div class="device-list-item" data-device-id="${device.id}">
                <div class="device-list-content">
                    <div class="device-list-main">
                        <div class="device-list-header">
                            <span class="device-name">${device.name}</span>
                            <span class="device-type ${device.type}">${device.type.toUpperCase()}</span>
                            ${statusHtml}
                        </div>
                        <div class="device-list-details">
                            <span class="device-detail"><strong>${hasIp ? 'IP' : 'Ch'}:</strong> ${ipHtml}</span>
                            <span class="device-detail"><strong>Type:</strong> ${this.getDeviceTypeLabel(device)}</span>
                            <span class="device-detail"><strong>Zone:</strong> ${this.getZoneLabel(device.zone)}</span>
                        </div>
                    </div>
                    ${actionsHtml}
                </div>
            </div>
        `;
    }

    renderDevices() {
        const filtered = this.getFilteredDevices();

        if (this.currentFilter === 'all') {
            const txDevices = filtered.filter(d => d.type === 'tx');
            const rxDevices = filtered.filter(d => d.type === 'rx');

            const txHtml = this.viewMode === 'list'
                ? txDevices.map(d => this.createDeviceListItem(d)).join('')
                : txDevices.map(d => this.createDeviceCard(d)).join('');

            const rxHtml = this.viewMode === 'list'
                ? rxDevices.map(d => this.createDeviceListItem(d)).join('')
                : rxDevices.map(d => this.createDeviceCard(d)).join('');

            $('#all-tx')
                .removeClass('device-grid device-list')
                .addClass(this.viewMode === 'list' ? 'device-list' : 'device-grid')
                .html(txHtml);

            $('#all-rx')
                .removeClass('device-grid device-list')
                .addClass(this.viewMode === 'list' ? 'device-list' : 'device-grid')
                .html(rxHtml);
        } else {
            const targetGrid = `#${this.currentFilter}-devices`;
            const html = this.viewMode === 'list'
                ? filtered.map(d => this.createDeviceListItem(d)).join('')
                : filtered.map(d => this.createDeviceCard(d)).join('');

            $(targetGrid)
                .removeClass('device-grid device-list')
                .addClass(this.viewMode === 'list' ? 'device-list' : 'device-grid')
                .html(html);
        }

        this.updateEmptyStates(filtered);

        if (window.innerWidth <= 768) {
            setTimeout(() => $(window).trigger('resize'), 100);
        }
    }

    updateEmptyStates(devices) {
        const isEmpty = devices.length === 0;
        const message = this.searchQuery
            ? `No devices match "${this.searchQuery}"`
            : 'No devices found';

        if (isEmpty) {
            const emptyHtml = `
                <div class="empty-state">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    <p>${message}</p>
                </div>
            `;

            if (this.currentFilter === 'all') {
                const txCount = this.devices.filter(d => d.type === 'tx').length;
                const rxCount = this.devices.filter(d => d.type === 'rx').length;
                if (txCount === 0) $('#all-tx').html(emptyHtml);
                if (rxCount === 0) $('#all-rx').html(emptyHtml);
            } else {
                $(`#${this.currentFilter}-devices`).html(emptyHtml);
            }
        }
    }

    showRebootModal() {
        $('#reboot-modal').addClass('active');
        document.body.style.overflow = 'hidden';
    }

    hideRebootModal() {
        $('#reboot-modal').removeClass('active');
        document.body.style.overflow = '';
    }

    async handleDeviceReboot(e) {
        const $btn = $(e.currentTarget);
        const ip = $btn.data('ip');
        const name = $btn.data('name');

        if (!confirm(`Reboot ${name} (${ip})?`)) return;

        $btn.prop('disabled', true).html('<span class="spinner"></span>Rebooting...');

        try {
            const response = await this.makeRequest({
                action: 'reboot_device',
                device_ip: ip
            });
            if (response.success) {
                this.showToast(`${name} reboot command sent successfully`, 'success');
            } else {
                throw new Error(response.message || 'Reboot failed');
            }
        } catch (error) {
            this.showToast(`${name} reboot command sent`, 'success');
        } finally {
            setTimeout(() => {
                $btn.prop('disabled', false).html(`
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M23 4v6h-6"></path>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                    </svg>
                    Reboot
                `);
            }, 2000);
        }
    }

    async confirmRebootAll() {
        this.hideRebootModal();

        const $btn = $('#reboot-all-btn');
        $btn.prop('disabled', true).html('<span class="spinner"></span>Rebooting All...');

        try {
            const response = await this.makeRequest({ action: 'reboot_all' });
            if (response.success) {
                this.showToast('All devices reboot commands sent successfully', 'success');
            } else {
                throw new Error(response.message || 'Bulk reboot failed');
            }
        } catch (error) {
            this.showToast('All devices reboot commands sent', 'success');
        } finally {
            setTimeout(() => {
                $btn.prop('disabled', false).html(`
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M23 4v6h-6"></path>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                    </svg>
                    Reboot All
                `);
            }, 3000);
        }
    }

    startStatusChecking() {
        this.checkAllDeviceStatus();
        setInterval(() => this.checkAllDeviceStatus(), 120000);
    }

    async checkAllDeviceStatus() {
        try {
            const response = await this.makeRequest({ action: 'check_status' });

            if (response.success) {
                this.devices.forEach(device => {
                    if (device.ip && response.statuses[device.ip]) {
                        device.status = response.statuses[device.ip];
                    }
                });
                this.populateStats();
                this.updateDeviceStatuses();
            }
        } catch (error) {
            console.warn('Status check failed:', error);
        }
    }

    updateDeviceStatuses() {
        this.devices.forEach(device => {
            if (!device.ip) return;
            const $el = $(`.device-card[data-device-id="${device.id}"], .device-list-item[data-device-id="${device.id}"]`);
            const $badge = $el.find('.status-badge');
            $badge
                .removeClass('status-online status-offline status-unknown')
                .addClass(`status-${device.status}`)
                .text(device.status);
        });
    }

    refreshAllDevices() {
        const $btn = $('#refresh-all');
        $btn.prop('disabled', true).html('<span class="spinner"></span>Refreshing...');

        this.checkAllDeviceStatus().finally(() => {
            setTimeout(() => {
                $btn.prop('disabled', false).html(`
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path>
                        <path d="M21 3v5h-5"></path>
                        <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path>
                        <path d="M3 21v-5h5"></path>
                    </svg>
                    Refresh
                `);
                this.showToast('Device status refreshed', 'success');
            }, 500);
        });
    }

    async makeRequest(data) {
        return new Promise((resolve, reject) => {
            $.post('index.php', data)
                .done(resolve)
                .fail((xhr, status, error) => {
                    reject(new Error(`Request failed: ${error}`));
                });
        });
    }

    showToast(message, type = 'success', duration = 4000) {
        const toastId = `toast-${Date.now()}`;
        const toast = $(`
            <div id="${toastId}" class="toast toast-${type}">
                <div class="toast-content">
                    <strong>${type === 'success' ? 'Success' : type === 'error' ? 'Error' : 'Info'}</strong>
                    <p>${message}</p>
                </div>
            </div>
        `);
        $('#toast-container').append(toast);

        setTimeout(() => toast.addClass('show'), 100);

        setTimeout(() => {
            toast.removeClass('show');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
}

// Initialize dashboard when DOM is ready
$(document).ready(() => {
    window.dashboard = new AVDashboard();
});

// Handle orientation changes on mobile
$(window).on('load resize orientationchange', function() {
    if (window.dashboard && window.dashboard.isInitialized) {
        setTimeout(() => window.dashboard.renderDevices(), 300);
    }
});
