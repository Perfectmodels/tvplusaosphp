const tabs = Array.from(document.querySelectorAll('[data-guide-tab]'));
const panels = Array.from(document.querySelectorAll('[data-guide-panel]'));
function select(tab, moveFocus = false) {
    for (const item of tabs) {
        const active = item === tab;
        item.setAttribute('aria-selected', String(active));
        item.tabIndex = active ? 0 : -1;
    }
    for (const panel of panels) panel.hidden = panel.dataset.guidePanel !== tab.dataset.guideTab;
    if (moveFocus) tab.focus({preventScroll:true});
}
for (const tab of tabs) {
    tab.addEventListener('click', () => select(tab));
    tab.addEventListener('keydown', event => {
        const index = tabs.indexOf(tab);
        let next;
        if (['ArrowRight', 'ArrowDown'].includes(event.key)) next = (index + 1) % tabs.length;
        else if (['ArrowLeft', 'ArrowUp'].includes(event.key)) next = (index + tabs.length - 1) % tabs.length;
        else if (event.key === 'Home') next = 0;
        else if (event.key === 'End') next = tabs.length - 1;
        else return;
        event.preventDefault(); select(tabs[next], true);
    });
}
