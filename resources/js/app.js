import './dashboard-pointage';
import './assistant';
import './portrait-form';
import './emission-form';
import './form-feedback';
import './bulk-delete';
import './mobile-tables';
import './landing';
import './veille-live';
import './bootstrap';
import './emission-search';
import Alpine from 'alpinejs';
window.Alpine = window.Alpine || Alpine;

async function demarrerApplication() {
    const composants = Array.from(document.querySelectorAll('[x-data]'))
        .map((element) => element.getAttribute('x-data') || '');
    const utilise = (nom) => composants.some((expression) => expression.trim().startsWith(`${nom}(`));
    const modules = [];

    if (utilise('conducteurRedaction')) {
        modules.push(import('./conducteur-redaction'));
    }
    if (utilise('conducteurRedactionVrai') || utilise('presencePanel')) {
        modules.push(import('./conducteur-redaction-vrai'));
    }
    if (utilise('conducteurPresentateur')) {
        modules.push(import('./conducteur-presentateur'));
    }
    if (utilise('chefAntennePanel')) {
        modules.push(import('./chef-antenne-panel'));
    }
    if (utilise('veilleRemixEditor')) {
        modules.push(import('./veille-remix-editor'));
    }

    await Promise.all(modules);
    if (!window.Alpine?._started) {
        window.Alpine.start();
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', demarrerApplication, { once: true });
} else {
    demarrerApplication();
}
