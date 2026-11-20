function initFormFeedback() {
    const errors = document.querySelector('[data-form-errors]');
    document.querySelectorAll('[data-add-to-lines]').forEach(select => {
        const target = document.getElementById(select.dataset.addToLines);
        if (!target) return;
        const refresh = () => {
            const chosen = new Set(target.value.split(/\r?\n/).map(value => value.trim().toLocaleLowerCase()).filter(Boolean));
            [...select.options].forEach(option => { option.disabled = option.value !== '' && chosen.has(option.value.toLocaleLowerCase()); });
        };
        select.addEventListener('change', () => {
            const value = select.value.trim();
            if (!value) return;
            const lines = target.value.split(/\r?\n/).map(line => line.trim()).filter(Boolean);
            if (!lines.some(line => line.toLocaleLowerCase() === value.toLocaleLowerCase())) lines.push(value);
            target.value = lines.join('\n');
            target.dispatchEvent(new Event('input', { bubbles: true }));
            select.value = '';
            refresh();
        });
        target.addEventListener('input', refresh);
        refresh();
    });
    function findField(key) {
        if (key === 'specialites' || key.startsWith('specialites.')) return document.getElementById('specialites-textarea');
        const name = key.replace(/\.([^.[\]]+)/g, '[$1]');
        const fields = [...document.querySelectorAll('input[name], select[name], textarea[name]')];
        const match = fields.find(field => field.name === name && !field.disabled)
            || fields.find(field => field.name.startsWith(key.split('.')[0] + '[') && !field.disabled);
        if (match?.type === 'hidden') {
            if (match.name === 'couleur_habillage') return match.form.querySelector('[data-color]');
            if (match.name.startsWith('presentateurs[')) return match.closest('[data-pres-row]')?.querySelector('select') || document.querySelector('#add-pres-jid');
            return match.parentElement.querySelector('input:not([type="hidden"]),select,textarea,button');
        }
        return match;
    }
    function focusField(control) {
        if (!control) { errors?.focus(); return; }
        document.dispatchEvent(new CustomEvent('form:focus-field', { detail: { control } }));
        control.scrollIntoView({ block: 'center' });
        control.focus({ preventScroll: true });
    }
    if (errors) {
        errors.querySelectorAll('[data-error-field]').forEach((button, index) => {
            const control = findField(button.dataset.errorField);
            button.id = `form-error-${index}`;
            if (control) {
                control.setAttribute('aria-invalid', 'true');
                control.setAttribute('aria-describedby', [control.getAttribute('aria-describedby'), button.id].filter(Boolean).join(' '));
                control.addEventListener('input', () => control.removeAttribute('aria-invalid'), { once: true });
            }
            button.addEventListener('click', () => focusField(findField(button.dataset.errorField)));
        });
        setTimeout(() => focusField(findField(errors.querySelector('[data-error-field]')?.dataset.errorField || '')), 0);
    }

    const lockedForms = new Map();
    document.addEventListener('submit', event => {
        const form = event.target;
        if (event.defaultPrevented || form.method.toLowerCase() !== 'post'
            || form.querySelector('[name="_method"][value="DELETE"]') || form.matches('[data-confirm], [data-confirm-delete]')) return;
        if (lockedForms.has(form)) { event.preventDefault(); return; }
        const button = event.submitter;
        let submittedValue;
        if (button?.name) {
            submittedValue = document.createElement('input'); submittedValue.type = 'hidden'; submittedValue.name = button.name; submittedValue.value = button.value; form.append(submittedValue);
        }
        const buttons = [...form.querySelectorAll('button[type="submit"], input[type="submit"]')];
        lockedForms.set(form, { submittedValue, buttons: buttons.map(element => ({ element, disabled: element.disabled })) });
        buttons.forEach(element => { element.disabled = true; });
        form.setAttribute('aria-busy', 'true');
    });
    window.addEventListener('pageshow', () => {
        lockedForms.forEach(({ buttons, submittedValue }, form) => {
            buttons.forEach(({ element, disabled }) => { element.disabled = disabled; });
            submittedValue?.remove(); form.removeAttribute('aria-busy');
        });
        lockedForms.clear();
    });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initFormFeedback, { once: true });
else initFormFeedback();
