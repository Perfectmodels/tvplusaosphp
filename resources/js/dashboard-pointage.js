const pointagePanel = document.querySelector('[data-dashboard-pointage]');
if (pointagePanel) {
    let busy = false;
    async function refreshPointage() {
        if (busy || document.hidden) return;
        busy = true;
        try {
            const response = await fetch(pointagePanel.dataset.url, {
                credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' },
            });
            if (!response.ok || response.redirected) throw new Error('Actualisation indisponible');
            const data = await response.json();
            const next = new DOMParser().parseFromString(data.html, 'text/html').querySelector('[data-dashboard-pointage]');
            if (!next) return;
            const openIds = new Set([...pointagePanel.querySelectorAll('details[open]')].map(element => element.id));
            const focusedId = pointagePanel.contains(document.activeElement) ? document.activeElement.id : null;
            const listScrollTop = pointagePanel.querySelector('[data-pointage-list]')?.scrollTop ?? 0;
            next.querySelectorAll('details').forEach(element => { element.open = openIds.has(element.id); });
            pointagePanel.replaceChildren(...next.childNodes);
            const list = pointagePanel.querySelector('[data-pointage-list]');
            if (list) list.scrollTop = listScrollTop;
            if (focusedId) document.getElementById(focusedId)?.focus({ preventScroll: true });
        } catch {
            const notice = pointagePanel.querySelector('[data-pointage-connection]');
            if (notice) notice.hidden = false;
        } finally {
            busy = false;
        }
    }
    window.setInterval(refreshPointage, 15000);
    document.addEventListener('visibilitychange', refreshPointage);
}
