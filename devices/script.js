/**
 * AV System Dashboard
 * Clean, scannable device management interface
 *
 * @author Seth Morrow
 * @version 4.1.0
 * @copyright 2025-2026
 */

class AVDashboard {
    constructor() {
        this.devices = [];
        this.activeFilter = 'all';
        this.searchQuery = '';
        this.viewMode = 'list';
        this.pendingReboot = null;
        this.init();
    }

    // ── Bootstrap ──────────────────────────────────────────────────────

    init() {
        this.loadDevices();
        this.bind();
        this.render();
        this.checkStatus();
        setInterval(() => this.checkStatus(), 120000);
    }

    loadDevices() {
        this.devices = (window.DEVICE_DATA || []).map((d, i) => ({
            ip: d.ip || null,
            name: d.name,
            type: d.type,
            deviceType: d.deviceType || '',
            channel: d.channel || null,
            status: 'checking',
            zone: d.zone || 'other',
            id: (d.type === 'tx' ? 'TX' : 'RX') + '-' + (d.ip ? d.ip.split('.').pop() : 'ch' + (d.channel || i))
        }));
    }

    // ── Event binding ──────────────────────────────────────────────────

    bind() {
        const self = this;

        // Search
        $('#search').on('input', function () {
            self.searchQuery = this.value.toLowerCase();
            self.render();
            $('#search-clear').toggle(self.searchQuery.length > 0);
        });
        $('#search-clear').on('click', () => {
            $('#search').val('').trigger('input').focus();
        });

        // Filter chips
        $('#filters').on('click', '.chip', function () {
            $('#filters .chip').removeClass('active');
            $(this).addClass('active');
            self.activeFilter = $(this).data('filter');
            self.render();
        });

        // View toggle
        $('.vt-btn').on('click', function () {
            const mode = $(this).data('view');
            if (mode === self.viewMode) return;
            self.viewMode = mode;
            $('.vt-btn').removeClass('active');
            $(this).addClass('active');
            self.render();
        });

        // Refresh
        $('#refresh-btn').on('click', () => this.refreshAll());

        // Reboot-all flow
        $('#reboot-all-btn').on('click', () => this.showModal('reboot-modal'));
        $('#confirm-reboot').on('click', () => this.rebootAll());
        $('#cancel-reboot, #modal-close').on('click', () => this.hideModal('reboot-modal'));
        $('#reboot-modal').on('click', (e) => {
            if (e.target.id === 'reboot-modal') this.hideModal('reboot-modal');
        });

        // Row/card click → open device Web UI (delegated)
        $(document).on('click', '.row[data-ip], .card[data-ip]', function (e) {
            // Don't navigate if they clicked a link or button inside
            if ($(e.target).closest('a, button').length) return;
            const ip = $(this).data('ip');
            if (ip) window.open('http://' + ip, '_blank');
        });

        // Single reboot flow (delegated)
        $(document).on('click', '.row-reboot, .card-reboot', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const $el = $(e.currentTarget);
            this.pendingReboot = { ip: $el.data('ip'), name: $el.data('name') };
            $('#single-reboot-name').text(this.pendingReboot.name);
            $('#single-reboot-ip').text(this.pendingReboot.ip);
            this.showModal('single-reboot-modal');
        });
        $('#single-reboot-confirm').on('click', () => this.rebootSingle());
        $('.single-modal-close').on('click', () => this.hideModal('single-reboot-modal'));
        $('#single-reboot-modal').on('click', (e) => {
            if (e.target.id === 'single-reboot-modal') this.hideModal('single-reboot-modal');
        });

        // Escape to close modals
        $(document).on('keydown', (e) => {
            if (e.key === 'Escape') {
                this.hideModal('reboot-modal');
                this.hideModal('single-reboot-modal');
            }
        });
    }

    // ── Filtering ──────────────────────────────────────────────────────

    filtered() {
        let list = [...this.devices];

        if (this.activeFilter === 'tx') {
            list = list.filter(d => d.type === 'tx');
        } else if (this.activeFilter === 'rx') {
            list = list.filter(d => d.type === 'rx');
        } else if (this.activeFilter !== 'all') {
            list = list.filter(d => d.zone === this.activeFilter);
        }

        if (this.searchQuery) {
            list = list.filter(d =>
                d.name.toLowerCase().includes(this.searchQuery) ||
                (d.ip && d.ip.includes(this.searchQuery)) ||
                d.zone.toLowerCase().includes(this.searchQuery)
            );
        }

        return list;
    }

    // ── Rendering ──────────────────────────────────────────────────────

    render() {
        const list = this.filtered();
        const $c = $('#device-container');

        const label = list.length === 1 ? '1 device' : list.length + ' devices';
        $('#result-count').text(label);

        if (list.length === 0) {
            $c.empty();
            const msg = this.searchQuery
                ? 'No devices match "' + this.searchQuery + '"'
                : 'No devices in this category';
            $('#empty-message').text(msg);
            $('#empty-state').show();
            return;
        }
        $('#empty-state').hide();

        if (this.viewMode === 'list') {
            $c.removeClass('device-grid').addClass('device-list');
            $c.html(list.map(d => this.rowHtml(d)).join(''));
        } else {
            $c.removeClass('device-list').addClass('device-grid');
            $c.html(list.map(d => this.cardHtml(d)).join(''));
        }
    }

    zoneLabel(z) {
        return { bowling: 'Bowling', bowlingbar: 'Bowling Bar', rink: 'Rink', jesters: 'Jesters', facility: 'Facility', outside: 'Outside', attic: 'Attic', other: 'Other' }[z] || z;
    }

    // ── List row ───────────────────────────────────────────────────────

    rowHtml(d) {
        const hasIp = !!d.ip;
        const statusCls = hasIp ? 'dot-' + d.status : 'dot-source';
        const statusLabel = hasIp ? d.status : 'source';
        const typeBadge = d.type === 'tx'
            ? '<span class="badge badge-tx">TX</span>'
            : '<span class="badge badge-rx">RX</span>';
        const mediaBadge = d.deviceType === 'audio'
            ? '<span class="badge badge-audio">Audio</span>'
            : '';
        const channelBadge = d.channel
            ? '<span class="badge badge-channel">Ch ' + d.channel + '</span>'
            : '';

        const actions = hasIp ? '<div class="row-actions">' +
            '<a href="http://' + d.ip + '" target="_blank" rel="noopener" class="row-action row-webui" title="Open Web UI">' +
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15,3 21,3 21,9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>' +
            '</a>' +
            '<button class="row-action row-reboot" data-ip="' + d.ip + '" data-name="' + d.name + '" title="Reboot device">' +
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.36 6.64A9 9 0 1 1 5.64 6.64"/><line x1="12" y1="2" x2="12" y2="12"/></svg>' +
            '</button>' +
        '</div>' : '<div class="row-actions"></div>';

        const ipDisplay = hasIp
            ? '<a href="http://' + d.ip + '" target="_blank" rel="noopener" class="row-ip">' + d.ip + '</a>'
            : '';

        return '<div class="row' + (hasIp ? ' clickable' : '') + '" data-id="' + d.id + '"' + (hasIp ? ' data-ip="' + d.ip + '"' : '') + '>' +
            '<span class="row-status status-dot ' + statusCls + '" title="' + statusLabel + '"></span>' +
            '<div class="row-info">' +
                '<span class="row-name">' + d.name + '</span>' +
                '<span class="row-sub">' + ipDisplay + '</span>' +
            '</div>' +
            '<div class="row-badges">' +
                typeBadge + channelBadge + mediaBadge +
                '<span class="badge badge-zone">' + this.zoneLabel(d.zone) + '</span>' +
            '</div>' +
            actions +
        '</div>';
    }

    // ── Grid card ──────────────────────────────────────────────────────

    cardHtml(d) {
        const hasIp = !!d.ip;
        const statusCls = hasIp ? 'dot-' + d.status : 'dot-source';
        const statusLabel = hasIp ? d.status : 'source';
        const typeBadge = d.type === 'tx'
            ? '<span class="badge badge-tx">TX</span>'
            : '<span class="badge badge-rx">RX</span>';
        const mediaBadge = d.deviceType === 'audio'
            ? '<span class="badge badge-audio">Audio</span>'
            : '';
        const channelBadge = d.channel
            ? '<span class="badge badge-channel">Ch ' + d.channel + '</span>'
            : '';

        const actions = hasIp
            ? '<div class="card-actions">' +
                '<a href="http://' + d.ip + '" target="_blank" rel="noopener" class="btn btn-ghost btn-sm card-webui">Web UI</a>' +
                '<button class="btn btn-danger-outline btn-sm card-reboot" data-ip="' + d.ip + '" data-name="' + d.name + '">Reboot</button>' +
              '</div>'
            : '<div class="card-actions"><span class="card-source-label">No IP assigned</span></div>';

        const ipDisplay = hasIp
            ? '<a href="http://' + d.ip + '" target="_blank" rel="noopener" class="card-ip">' + d.ip + '</a>'
            : '';

        return '<div class="card' + (hasIp ? ' clickable' : '') + '" data-id="' + d.id + '"' + (hasIp ? ' data-ip="' + d.ip + '"' : '') + '>' +
            '<div class="card-top">' +
                '<div class="card-title">' +
                    '<span class="status-dot ' + statusCls + '" title="' + statusLabel + '"></span>' +
                    '<span class="card-name">' + d.name + '</span>' +
                '</div>' +
                '<div class="card-badges">' + typeBadge + channelBadge + mediaBadge + '</div>' +
            '</div>' +
            '<div class="card-meta">' +
                ipDisplay +
                '<span class="badge badge-zone">' + this.zoneLabel(d.zone) + '</span>' +
            '</div>' +
            actions +
        '</div>';
    }

    // ── Status checking ────────────────────────────────────────────────

    async checkStatus() {
        this.updateStatusSummary('checking');
        try {
            const res = await this.post({ action: 'check_status' });
            if (res.success) {
                let online = 0, offline = 0;
                this.devices.forEach(d => {
                    if (d.ip && res.statuses[d.ip]) {
                        d.status = res.statuses[d.ip];
                        if (d.status === 'online') online++;
                        else offline++;
                    }
                });
                this.updateStatusSummary('done', online, offline);
                this.updateDots();
            }
        } catch (e) {
            this.updateStatusSummary('error');
        }
    }

    updateStatusSummary(state, online, offline) {
        const $dot = $('#status-summary .status-dot');
        const $text = $('#status-text');

        $dot.removeClass('dot-checking dot-online dot-offline dot-error');

        if (state === 'checking') {
            $dot.addClass('dot-checking');
            $text.text('Checking devices...');
        } else if (state === 'error') {
            $dot.addClass('dot-error');
            $text.text('Status check failed');
        } else {
            if (offline === 0) {
                $dot.addClass('dot-online');
                $text.text(online + ' online');
            } else {
                $dot.addClass('dot-offline');
                $text.text(online + ' online, ' + offline + ' offline');
            }
        }
    }

    updateDots() {
        this.devices.forEach(d => {
            if (!d.ip) return;
            const sel = '.row[data-id="' + d.id + '"] .row-status, .card[data-id="' + d.id + '"] .status-dot';
            $(sel).removeClass('dot-checking dot-online dot-offline dot-unknown')
                  .addClass('dot-' + d.status)
                  .attr('title', d.status);
        });
    }

    // ── Refresh ────────────────────────────────────────────────────────

    refreshAll() {
        const $btn = $('#refresh-btn');
        $btn.addClass('spinning');
        this.checkStatus().finally(() => {
            setTimeout(() => {
                $btn.removeClass('spinning');
                this.render();
                this.toast('Device status refreshed');
            }, 300);
        });
    }

    // ── Reboot ─────────────────────────────────────────────────────────

    async rebootAll() {
        this.hideModal('reboot-modal');
        const $btn = $('#reboot-all-btn');
        $btn.prop('disabled', true);

        try {
            await this.post({ action: 'reboot_all' });
            this.toast('Reboot commands sent to all devices');
        } catch (e) {
            this.toast('Reboot commands sent to all devices');
        } finally {
            setTimeout(() => $btn.prop('disabled', false), 3000);
        }
    }

    async rebootSingle() {
        if (!this.pendingReboot) return;
        const { ip, name } = this.pendingReboot;
        this.hideModal('single-reboot-modal');

        try {
            await this.post({ action: 'reboot_device', device_ip: ip });
            this.toast(name + ' reboot command sent');
        } catch (e) {
            this.toast(name + ' reboot command sent');
        }
        this.pendingReboot = null;
    }

    // ── Modal helpers ──────────────────────────────────────────────────

    showModal(id) {
        $('#' + id).addClass('open');
        document.body.style.overflow = 'hidden';
    }

    hideModal(id) {
        $('#' + id).removeClass('open');
        document.body.style.overflow = '';
    }

    // ── Network ────────────────────────────────────────────────────────

    post(data) {
        return new Promise((resolve, reject) => {
            $.post('index.php', data).done(resolve).fail((_, __, err) => reject(new Error(err)));
        });
    }

    // ── Toast ──────────────────────────────────────────────────────────

    toast(msg, type = 'success', ms = 4000) {
        const id = 't' + Date.now();
        const icon = type === 'success'
            ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>'
            : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
        const $t = $('<div id="' + id + '" class="toast toast-' + type + '">' + icon + '<span>' + msg + '</span></div>');
        $('#toasts').append($t);
        requestAnimationFrame(() => $t.addClass('show'));
        setTimeout(() => {
            $t.removeClass('show');
            setTimeout(() => $t.remove(), 300);
        }, ms);
    }
}

$(document).ready(() => { window.dashboard = new AVDashboard(); });
