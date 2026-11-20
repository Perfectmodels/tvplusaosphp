function initMobileTables() {
    document.querySelectorAll('table[data-mobile-cards]').forEach(table => {
        const headers = [...(table.tHead?.rows[0]?.cells || [])].map(cell => cell.textContent.trim().replace(/\s+/g, ' '));
        if (!headers.length) return;
        function prepare() {
            table.querySelectorAll('tbody > tr').forEach(row => {
                [...row.cells].forEach((cell, index) => {
                    if (cell.colSpan > 1 || index >= headers.length) return;
                    const title = headers[index];
                    if (cell.dataset.label !== title) cell.dataset.label = title;
                    const normalized = title.toLocaleLowerCase('fr').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                    const wide = /nom|utilisateur|titre|sujet|url|contact|actions|ponctualite|detail|message|description/.test(normalized);
                    if (cell.dataset.cardWide !== String(wide)) cell.dataset.cardWide = String(wide);
                    const media = normalized === 'photo' || normalized === 'selection' || normalized === '#';
                    if (cell.dataset.cardMedia !== String(media)) cell.dataset.cardMedia = String(media);
                });
            });
        }
        prepare();
        new MutationObserver(prepare).observe(table, { childList: true, subtree: true });
    });
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initMobileTables, { once: true });
else initMobileTables();
