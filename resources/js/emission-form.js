function initEmissionForm() {
    const form = document.querySelector('[data-emission-form]');
    if (!form) return;
    const panels = [...form.querySelectorAll('[data-panel]')];
    const tabs = [...form.querySelectorAll('[data-wizard-tabs] [data-step]')];
    const previous = form.querySelector('[data-prev]');
    const next = form.querySelector('[data-next]');
    const submit = form.querySelector('[data-submit]');
    const list = form.querySelector('#presentateurs-list');
    const presenter = form.querySelector('#add-pres-jid');
    const role = form.querySelector('#add-pres-role');
    const assignment = form.querySelector('#add-pres-statut');
    const addPresenter = form.querySelector('#btn-add-presentateur');
    const languages = form.querySelector('[data-langues-wrap]');
    const languageInput = languages.querySelector('[data-langue-add]');
    const languageSelect = form.querySelector('[data-langue-select]');
    const start = form.elements.heure_debut;
    const end = form.elements.heure_fin;
    const duration = form.elements.duree_minutes;
    let current = 1;

    function go(step) {
        current = Math.max(1, Math.min(4, step));
        panels.forEach(panel => panel.classList.toggle('hidden', Number(panel.dataset.panel) !== current));
        tabs.forEach(tab => {
            const selected = Number(tab.dataset.step) === current;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
        });
        previous.disabled = current === 1;
        next.classList.toggle('hidden', current === 4);
        submit.classList.toggle('hidden', current !== 4);
        submit.classList.toggle('flex', current === 4);
    }
    function validate(elements) {
        const invalid = [...elements].find(field => field.willValidate && !field.validity.valid);
        if (!invalid) return true;
        const panel = invalid.closest('[data-panel]');
        if (panel) go(Number(panel.dataset.panel));
        invalid.reportValidity();
        invalid.focus();
        return false;
    }
    function updateDuration() {
        const valid = value => /^(?:[01]\d|2[0-3]):[0-5]\d$/.test(value);
        end.setCustomValidity('');
        if (!valid(start.value) || !valid(end.value)) { duration.value = ''; return; }
        const minutes = value => Number(value.slice(0, 2)) * 60 + Number(value.slice(3, 5));
        const total = (minutes(end.value) - minutes(start.value) + 1440) % 1440;
        duration.value = total;
        if (total === 0 || total > 480) end.setCustomValidity('L’émission doit durer entre 1 et 480 minutes. Vérifiez les horaires.');
    }
    function summary() {
        const set = (selector, value) => { const element = form.querySelector(selector); if (element) element.textContent = value; };
        set('[data-resume-nom]', form.elements.nom.value.trim() || 'Nom de l’émission');
        set('[data-resume-subtitle]', form.elements.sous_titre.value.trim());
        set('[data-resume-horaire]', start.value && end.value ? `${start.value} → ${end.value}${end.value < start.value ? ' (lendemain)' : ''}` : 'Horaires à renseigner');
        set('[data-resume-duree]', duration.value ? `${duration.value} min` : '—');
        set('[data-resume-jours]', [...form.querySelectorAll('[name="jours_diffusion[]"]:checked')].map(field => field.closest('label').textContent.trim()).join(' · ') || 'Aucun jour régulier');
        set('[data-resume-pres-count]', `${list.querySelectorAll('[data-pres-row]').length} présentateur(s)`);
    }
    function updatePresenters() {
        const rows = [...list.querySelectorAll('[data-pres-row]')];
        rows.forEach((row, index) => {
            row.querySelectorAll('[name]').forEach(field => { field.name = field.name.replace(/presentateurs\[[^\]]+\]/, `presentateurs[${index}]`); });
            row.querySelector('.pres-ordre').value = index + 1;
        });
        const ids = new Set(rows.map(row => row.querySelector('[name$="[journaliste_id]"]').value));
        [...presenter.options].forEach(option => { option.disabled = ids.has(option.value); });
        if (ids.has(presenter.value)) presenter.value = '';
        addPresenter.disabled = !presenter.value;
        let empty = list.querySelector('#pres-empty');
        if (rows.length) empty?.remove();
        else if (!empty) {
            empty = document.createElement('p'); empty.id = 'pres-empty'; empty.className = 'p-5 text-sm text-slate-500';
            empty.textContent = 'Aucun présentateur affecté. Vous pourrez en ajouter ultérieurement.'; list.append(empty);
        }
        summary();
    }
    addPresenter.addEventListener('click', () => {
        const id = presenter.value;
        if (!id || [...list.querySelectorAll('[name$="[journaliste_id]"]')].some(field => field.value === id)) return;
        const row = document.createElement('div');
        row.dataset.presRow = '';
        row.className = 'px-5 py-3.5 grid grid-cols-1 sm:grid-cols-12 gap-3 items-center';
        row.innerHTML = '<div class="sm:col-span-5"><strong data-presenter-name class="text-sm"></strong><input type="hidden" name="presentateurs[0][journaliste_id]"><input type="hidden" class="pres-ordre" name="presentateurs[0][ordre]"></div><div class="sm:col-span-3"><select name="presentateurs[0][role]" class="input-field" aria-label="Rôle du présentateur"></select></div><div class="sm:col-span-3"><select name="presentateurs[0][statut_affectation]" class="input-field" aria-label="Confirmation du présentateur"></select></div><div class="sm:col-span-1"><button type="button" data-pres-remove class="min-h-[44px] px-2 text-error" aria-label="Retirer ce présentateur">×</button></div>';
        row.querySelector('[data-presenter-name]').textContent = presenter.selectedOptions[0].textContent.split(' — ')[0];
        row.querySelector('[name$="[journaliste_id]"]').value = id;
        const roleField = row.querySelector('[name$="[role]"]');
        roleField.replaceChildren(...[...role.options].map(option => option.cloneNode(true))); roleField.value = role.value;
        const assignmentField = row.querySelector('[name$="[statut_affectation]"]');
        assignmentField.replaceChildren(...[...assignment.options].map(option => option.cloneNode(true))); assignmentField.value = assignment.value;
        list.append(row); presenter.value = ''; updatePresenters();
    });
    presenter.addEventListener('change', updatePresenters);
    list.addEventListener('click', event => {
        const button = event.target.closest('[data-pres-remove]');
        if (button) { button.closest('[data-pres-row]').remove(); updatePresenters(); }
    });
    function refreshLanguages() {
        const chosen = new Set([...languages.querySelectorAll('[name="langues[]"]')].map(field => field.value.toLocaleLowerCase()));
        [...languageSelect.options].forEach(option => { option.disabled = chosen.has(option.value.toLocaleLowerCase()); });
    }
    function addLanguage() {
        const value = languageInput.value.trim();
        if (!value) return;
        if (value.length > 40) { languageInput.setCustomValidity('40 caractères maximum.'); languageInput.reportValidity(); return; }
        if ([...languages.querySelectorAll('[name="langues[]"]')].some(field => field.value.toLocaleLowerCase() === value.toLocaleLowerCase())) { languageInput.value = ''; return; }
        const tag = document.createElement('span');
        tag.className = 'badge-blue text-xs px-2.5 py-1 rounded-md inline-flex items-center gap-2';
        const label = document.createElement('span'); label.textContent = value;
        const hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = 'langues[]'; hidden.value = value;
        const remove = document.createElement('button'); remove.type = 'button'; remove.dataset.langueRemove = ''; remove.textContent = '×'; remove.setAttribute('aria-label', `Retirer ${value}`);
        tag.append(label, hidden, remove); languages.insertBefore(tag, languageInput); languageInput.value = '';
        refreshLanguages();
    }
    languageSelect.addEventListener('change', () => { languageInput.value = languageSelect.value; addLanguage(); languageSelect.value = ''; });
    languageInput.addEventListener('input', () => languageInput.setCustomValidity(''));
    languageInput.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); addLanguage(); } });
    languages.addEventListener('click', event => { const remove = event.target.closest('[data-langue-remove]'); if (remove) { remove.closest('span').remove(); refreshLanguages(); } });
    form.querySelectorAll('[data-color]').forEach(button => button.addEventListener('click', () => {
        form.elements.couleur_habillage.value = button.dataset.color;
        form.querySelectorAll('[data-color]').forEach(item => item.removeAttribute('aria-invalid'));
        form.querySelectorAll('[data-color]').forEach(item => { item.classList.toggle('ring-primary', item === button); item.classList.toggle('ring-slate-200', item !== button); });
    }));
    previous.addEventListener('click', () => go(current - 1));
    next.addEventListener('click', () => { addLanguage(); if (validate(panels.find(panel => Number(panel.dataset.panel) === current).querySelectorAll('input,select,textarea'))) { go(current + 1); summary(); } });
    tabs.forEach(tab => tab.addEventListener('click', () => {
        const target = Number(tab.dataset.step);
        if (target <= current || validate(panels.filter(panel => Number(panel.dataset.panel) < target).flatMap(panel => [...panel.querySelectorAll('input,select,textarea')]))) { go(target); summary(); }
    }));
    form.addEventListener('input', () => { updateDuration(); summary(); });
    form.addEventListener('change', () => { updateDuration(); summary(); });
    form.addEventListener('submit', event => { addLanguage(); updateDuration(); if (!validate(form.elements)) event.preventDefault(); });
    document.addEventListener('form:focus-field', event => { const panel = event.detail.control?.closest('[data-panel]'); if (panel && form.contains(panel)) go(Number(panel.dataset.panel)); });
    updateDuration(); updatePresenters(); refreshLanguages(); go(1);
    const errors = JSON.parse(form.dataset.errorFields || '[]');
    const firstError = [...form.elements].find(field => errors.some(key => field.name === key || field.name.startsWith(key.split('.')[0] + '[')));
    if (firstError?.closest('[data-panel]')) go(Number(firstError.closest('[data-panel]').dataset.panel));
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initEmissionForm, { once: true });
else initEmissionForm();
