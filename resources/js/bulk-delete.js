function initBulkDeletion() {
    document.querySelectorAll('[data-bulk-delete-form]').forEach(form => {
        const selected = new Set();
        const allBoxes = Array.from(document.querySelectorAll(`[data-bulk-all][form="${CSS.escape(form.id)}"]`));
        const fallback = form.querySelector('[data-bulk-all]');
        if (allBoxes.length === 0 && fallback) allBoxes.push(fallback);
        const bar = form.querySelector('[data-bulk-bar]');
        const count = form.querySelector('[data-bulk-count]');
        const submit = form.querySelector('[data-bulk-submit]');
        const clear = form.querySelector('[data-bulk-clear]');
        const payload = form.querySelector('[data-bulk-ids]');
        let lastPayload = '';
        const boxes = () => [...document.querySelectorAll(`[data-bulk-item][form="${CSS.escape(form.id)}"]`)];
        const available = () => boxes().filter(box => !box.disabled && box.getClientRects().length > 0);
        function refresh() {
            const visible = available();
            const ids = new Set(visible.map(box => box.value));
            [...selected].forEach(id => { if (!ids.has(id)) selected.delete(id); });
            boxes().forEach(box => { box.checked = selected.has(box.value) && !box.disabled && box.getClientRects().length > 0; });
            const total = selected.size;
            const text = `${total} sélectionné${total > 1 ? 's' : ''} sur ${ids.size}${total > 100 ? ' — maximum 100 par opération' : ''}`;
            if (count.textContent !== text) count.textContent = text;
            if (submit.disabled !== (total === 0 || total > 100)) submit.disabled = total === 0 || total > 100;
            if (clear.disabled !== (total === 0)) clear.disabled = total === 0;
            allBoxes.forEach((all) => {
                if (all.disabled !== (ids.size === 0)) all.disabled = ids.size === 0;
                all.checked = ids.size > 0 && total === ids.size;
                all.indeterminate = total > 0 && total < ids.size;
            });
            if (bar) bar.hidden = total === 0;
            form.dataset.confirmDelete = `${form.dataset.bulkAction} ${total} élément${total > 1 ? 's' : ''} sélectionné${total > 1 ? 's' : ''} ?`;
            const serialized = [...selected].sort().join(',');
            if (serialized !== lastPayload) {
                payload.replaceChildren(...[...selected].map(id => {
                    const field = document.createElement('input');
                    field.type = 'hidden'; field.name = 'ids[]'; field.value = id;
                    return field;
                }));
                lastPayload = serialized;
            }
        }
        allBoxes.forEach((all) => all.addEventListener('change', () => {
            selected.clear();
            if (all.checked) available().forEach(box => selected.add(box.value));
            refresh();
        }));
        clear.addEventListener('click', () => { selected.clear(); refresh(); });
        document.addEventListener('change', event => {
            const box = event.target;
            if (box.matches?.('[data-bulk-item]') && box.getAttribute('form') === form.id) {
                box.checked ? selected.add(box.value) : selected.delete(box.value);
                refresh();
            }
        });
        const root = document.querySelector(`[data-bulk-list="${CSS.escape(form.id)}"]`);
        if (root) new MutationObserver(refresh).observe(root, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled'] });
        window.addEventListener('resize', refresh);
        document.addEventListener('click', event => {
            if (event.target.closest('button, [role="tab"], [data-tab], summary')) requestAnimationFrame(refresh);
        });
        window.addEventListener('pageshow', () => { selected.clear(); refresh(); });
        refresh();
    });
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initBulkDeletion, { once: true });
else initBulkDeletion();
