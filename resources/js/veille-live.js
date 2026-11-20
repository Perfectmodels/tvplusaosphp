const root = document.querySelector('[data-veille-live]');
if (root) {
    let revision = root.dataset.revision;
    let busy = false;
    const endpoint = root.dataset.statusUrl;
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    async function refresh() {
        if (busy || document.hidden) return;
        busy = true;
        try {
            const response = await fetch(endpoint, { headers, credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok || response.redirected) return;
            const status = await response.json();
            const panel = document.getElementById('veille-automation-panel');
            if (panel && status.panel_html) panel.outerHTML = status.panel_html;
            if (status.revision === revision) return;
            const pageResponse = await fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' });
            if (!pageResponse.ok || pageResponse.redirected) return;
            const next = new DOMParser().parseFromString(await pageResponse.text(), 'text/html');
            // Do not interrupt a confirmation, an open editor or keyboard navigation.
            if (document.querySelector('[data-modal-backdrop]:not(.hidden), .swal2-container') || document.activeElement?.closest('[data-veille-refresh-region]')) return;
            document.querySelectorAll('[data-veille-refresh-region]').forEach(region => {
                const updated = next.getElementById(region.id);
                if (updated) region.replaceChildren(...updated.childNodes);
            });
            document.querySelectorAll('select[data-veille-refresh-options]').forEach(select => {
                if (select === document.activeElement) return;
                const updated = next.querySelector(`select[data-veille-refresh-options="${select.dataset.veilleRefreshOptions}"]`);
                if (updated) {
                    const selected = select.value;
                    select.replaceChildren(...updated.childNodes);
                    select.value = selected;
                }
            });
            revision = next.querySelector('[data-veille-live]')?.dataset.revision ?? status.revision;
        } catch {
            // A temporary network failure does not erase the current articles.
        } finally {
            busy = false;
        }
    }
    window.setInterval(refresh, 30000);
    document.addEventListener('visibilitychange', refresh);
    window.addEventListener('pageshow', refresh);
}
