/**
 * WAVe-Lite: poll /scans/{id}/progress for running scans (Phase 2).
 * Stops polling when status is completed or failed, then refreshes the page once.
 */

function updateRowFromPayload(row, data) {
    const bar = row.querySelector('[data-scan-progress-bar]');
    const phaseEl = row.querySelector('[data-scan-phase]');
    const statusEl = row.querySelector('[data-scan-status-badge]');

    if (bar) {
        bar.style.width = `${Number(data.progress) || 0}%`;
    }
    if (phaseEl) {
        phaseEl.textContent = data.phase ?? '—';
    }
    if (statusEl && data.status) {
        statusEl.textContent = data.status;
        statusEl.classList.remove(
            'bg-blue-100',
            'text-blue-800',
            'bg-green-100',
            'text-green-800',
            'bg-red-100',
            'text-red-800',
            'bg-gray-100',
            'text-gray-800'
        );
        if (data.status === 'running') {
            statusEl.classList.add('bg-blue-100', 'text-blue-800');
        } else if (data.status === 'completed') {
            statusEl.classList.add('bg-green-100', 'text-green-800');
        } else if (data.status === 'failed') {
            statusEl.classList.add('bg-red-100', 'text-red-800');
        } else {
            statusEl.classList.add('bg-gray-100', 'text-gray-800');
        }
    }

    const terminal = data.status === 'completed' || data.status === 'failed';
    if (terminal) {
        row.removeAttribute('data-poll-url');
        window.setTimeout(() => {
            window.location.reload();
        }, 800);
    }
}

async function pollRow(row) {
    const url = row.getAttribute('data-poll-url');
    if (!url) {
        return;
    }

    try {
        const res = await fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) {
            return;
        }
        const data = await res.json();
        updateRowFromPayload(row, data);
    } catch (e) {
        console.warn('WAVe-Lite: progress poll failed', e);
    }
}

function initScanPolling() {
    const intervalMs = 3000;

    window.setInterval(() => {
        document.querySelectorAll('[data-scan-row][data-poll-url]').forEach((row) => {
            pollRow(row);
        });
    }, intervalMs);

    document.querySelectorAll('[data-scan-row][data-poll-url]').forEach((row) => {
        pollRow(row);
    });
}

function initRiskChart() {
    const pie = document.querySelector('[data-risk-pie]');
    const bars = document.querySelector('[data-risk-bars]');
    const hover = document.querySelector('[data-risk-hover]');
    const toggles = document.querySelectorAll('[data-risk-toggle]');
    const items = document.querySelectorAll('[data-risk-item]');

    if (!pie || !bars || !hover || toggles.length === 0 || items.length === 0) {
        return;
    }

    const setMode = (mode) => {
        const pieMode = mode === 'pie';
        pie.classList.toggle('hidden', !pieMode);
        bars.classList.toggle('hidden', pieMode);

        toggles.forEach((btn) => {
            const selected = btn.getAttribute('data-risk-toggle') === mode;
            btn.classList.toggle('bg-white', selected);
            btn.classList.toggle('shadow-sm', selected);
            btn.classList.toggle('text-gray-800', selected);
            btn.classList.toggle('text-gray-600', !selected);
        });
    };

    const renderHoverText = (item) => {
        const label = item.getAttribute('data-risk-label') ?? 'Unknown';
        const count = Number(item.getAttribute('data-risk-count') ?? 0);
        const total = Math.max(1, Number(item.getAttribute('data-risk-total') ?? 1));
        const pct = ((count / total) * 100).toFixed(1);
        hover.textContent = `${label}: ${count} finding(s), ${pct}% of total.`;
    };

    items.forEach((item) => {
        item.addEventListener('mouseenter', () => renderHoverText(item));
        item.addEventListener('focus', () => renderHoverText(item));
        item.addEventListener('click', () => renderHoverText(item));
    });

    toggles.forEach((btn) => {
        btn.addEventListener('click', () => {
            setMode(btn.getAttribute('data-risk-toggle') ?? 'pie');
        });
    });

    setMode('pie');
}

document.addEventListener('DOMContentLoaded', () => {
    initScanPolling();
    initRiskChart();
});
