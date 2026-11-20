const normaliserTexte = (valeur) => String(valeur ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase('fr');

function filtrerEmissions(champRecherche) {
    const conteneur = champRecherche.closest('[data-emission-searchable]');
    const select = conteneur?.querySelector('[data-emission-select]');

    if (!select) {
        return;
    }

    const requete = normaliserTexte(champRecherche.value.trim());

    Array.from(select.options).forEach((option) => {
        const estOptionVide = option.value === '';
        const correspond = normaliserTexte(option.textContent).includes(requete);
        option.hidden = !estOptionVide && !correspond && !option.selected;
    });
}

document.addEventListener('input', (event) => {
    if (event.target instanceof HTMLInputElement && event.target.matches('[data-emission-search]')) {
        filtrerEmissions(event.target);
    }
});

document.addEventListener('change', (event) => {
    if (!(event.target instanceof HTMLSelectElement) || !event.target.matches('[data-emission-select]')) {
        return;
    }

    const champRecherche = event.target.closest('[data-emission-searchable]')?.querySelector('[data-emission-search]');
    if (champRecherche) {
        champRecherche.value = '';
        filtrerEmissions(champRecherche);
    }
});

document.addEventListener('reset', (event) => {
    const formulaire = event.target;
    if (!(formulaire instanceof HTMLFormElement)) {
        return;
    }

    window.requestAnimationFrame(() => {
        formulaire.querySelectorAll('[data-emission-search]').forEach((champRecherche) => {
            champRecherche.value = '';
            filtrerEmissions(champRecherche);
        });
    });
});
