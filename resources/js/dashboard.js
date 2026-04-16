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

document.addEventListener('DOMContentLoaded', initScanPolling);
