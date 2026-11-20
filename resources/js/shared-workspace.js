const dossier = document.querySelector('[data-workspace-case]');
const count = document.querySelector('[data-workspace-unread]');
let busy = false;
let dirty = false;
let halted = false;
if (dossier) {
    dossier.addEventListener('input', (event) => { if (event.target.closest('form')) dirty = true; });
    dossier.addEventListener('change', (event) => {
        if (event.target.closest('form')) dirty = true;
        if (event.target.matches('[data-task-status]')) {
            const field = event.target.form.querySelector('[data-blocked-field]');
            const blocked = event.target.value === 'blocked';
            field.hidden = !blocked;
            field.querySelector('textarea').required = blocked;
        }
    });
    dossier.addEventListener('submit', (event) => {
        const button = event.submitter;
        if (button) { button.disabled = true; button.textContent = 'Enregistrement…'; }
    });
}
async function refresh() {
    if (busy || document.hidden || halted) return;
    busy = true;
    try {
        if (count) {
            const response = await fetch(count.dataset.countUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (response.ok) {
                const data = await response.json();
                count.textContent = String(data.unread);
                count.hidden = data.unread === 0;
            }
        }
        if (!dossier) return;
        const message = dossier.querySelector('[data-workspace-live]');
        const editing = dirty || !!document.activeElement?.closest('[data-workspace-case] form');
        const url = new URL(dossier.dataset.stateUrl, location.href);
        for (const [key, value] of new URLSearchParams(location.search)) url.searchParams.set(key, value);
        url.searchParams.set('revision', dossier.dataset.revision);
        const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
        if ([401, 403, 404].includes(response.status)) {
            halted = true;
            dossier.replaceChildren();
            const notice = document.createElement('p');
            notice.textContent = 'Ce dossier n’est plus accessible avec votre compte. Revenez à l’espace métier pour consulter vos accès actuels.';
            notice.setAttribute('role', 'alert');
            dossier.append(notice);
            return;
        }
        if (!response.ok) throw new Error('refresh');
        const data = await response.json();
        if (editing) {
            message.textContent = data.redirect ? 'Ce dossier a été regroupé. Copiez votre saisie avant de rouvrir le dossier depuis l’espace métier.' : 'La saisie en cours est conservée. L’actualisation reprendra après enregistrement.';
            return;
        }
        if (data.redirect) { location.assign(data.redirect); return; }
        if (data.html) {
            dossier.querySelector('[data-workspace-panels]').innerHTML = data.html;
            dossier.dataset.revision = String(data.revision);
        }
        message.textContent = 'Dossier actualisé à ' + new Intl.DateTimeFormat('fr', { hour: '2-digit', minute: '2-digit' }).format(new Date());
    } catch {
        const message = dossier?.querySelector('[data-workspace-live]');
        if (message) message.textContent = 'Actualisation momentanément indisponible. Nouvelle tentative automatique dans 30 secondes.';
    } finally { busy = false; }
}
if (dossier || count) {
    setInterval(refresh, 30000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
}
