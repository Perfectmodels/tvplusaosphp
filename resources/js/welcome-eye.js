const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
const eyes = new Map();
let frameId = 0;
let previousFrame = 0;
let lastPointer = 0;
const introVisible = () => !!document.querySelector('#tvos-welcome-intro[data-intro-active]');
function schedule() {
    if (!frameId && !document.hidden && !reducedMotion.matches && [...eyes.values()].some(state => state.visible)) frameId = requestAnimationFrame(frame);
}
function frame(now) {
    frameId = 0;
    if (document.hidden || reducedMotion.matches) return;
    const delta = Math.min((now - previousFrame) / 16.67, 3) || 1;
    previousFrame = now;
    const welcomeActive = introVisible();
    let anyActive = false;
    for (const [root, state] of eyes) {
        if (!root.isConnected) { eyes.delete(root); continue; }
        const active = state.visible && state.width > 0 && (!welcomeActive || !!root.closest('#tvos-welcome-intro'));
        root.toggleAttribute('data-regard-paused', !active);
        if (!active) continue;
        anyActive = true;
        if (now - lastPointer > 2500 && now - state.lastWander > 1800) {
            state.lastWander = now;
            const angle = Math.random() * Math.PI * 2;
            const distance = Math.random() * .8;
            state.eye.style.setProperty('--ix', (Math.cos(angle) * distance).toFixed(3));
            state.eye.style.setProperty('--iy', (Math.sin(angle) * distance).toFixed(3));
        }
        const { context, width, height } = state;
        if (!context || now - state.lastPaint < 30) continue;
        state.lastPaint = now;
        context.clearRect(0, 0, width, height);
        context.globalCompositeOperation = 'lighter';
        for (const particle of state.particles) {
            particle.angle += particle.speed * delta * 2;
            const x = width / 2 + Math.cos(particle.angle) * particle.radius * width;
            const y = height / 2 + Math.sin(particle.angle) * particle.radius * height * .98;
            const alpha = .35 + .65 * Math.abs(Math.sin(now * .002 + particle.phase));
            context.fillStyle = `rgba(120,210,255,${alpha.toFixed(2)})`;
            context.beginPath(); context.arc(x, y, particle.size * width / 900, 0, Math.PI * 2); context.fill();
        }
    }
    if (anyActive) schedule();
}
const intersection = 'IntersectionObserver' in window ? new IntersectionObserver(entries => {
    for (const entry of entries) { const state = eyes.get(entry.target); if (state) state.visible = entry.isIntersecting; }
    schedule();
}) : null;
function boot() {
    for (const root of document.querySelectorAll('[data-regard]')) {
        if (eyes.has(root)) continue;
        const eye = root.querySelector('[data-regard-eye]');
        const canvas = root.querySelector('canvas');
        if (!eye || !canvas) continue;
        let context = null;
        try { context = canvas.getContext('2d'); } catch { /* The CSS eye also works without a canvas. */ }
        const state = { eye, context, width:0, height:0, visible:!intersection, lastWander:0, lastPaint:0, particles:Array.from({length:root.classList.contains('tvos-regard--compact') ? 24 : 70}, () => ({angle:Math.random()*Math.PI*2,radius:.4+Math.random()*.2,speed:(Math.random()*.0035+.0008)*(Math.random()<.5?-1:1),size:Math.random()*1.6+.4,phase:Math.random()*Math.PI*2})) };
        eyes.set(root, state);
        const size = () => {
            const rect = canvas.getBoundingClientRect();
            const ratio = Math.min(devicePixelRatio || 1, 1.5);
            state.width = canvas.width = Math.min(960, Math.round(rect.width * ratio));
            state.height = canvas.height = Math.min(960, Math.round(rect.height * ratio));
            schedule();
        };
        if ('ResizeObserver' in window) new ResizeObserver(size).observe(root); else addEventListener('resize', size, {passive:true});
        size(); intersection?.observe(root);
    }
    schedule();
}
addEventListener('pointermove', event => {
    if (reducedMotion.matches) return;
    lastPointer = performance.now();
    for (const [root, state] of eyes) {
        if (!state.visible || (introVisible() && !root.closest('#tvos-welcome-intro'))) continue;
        const rect = state.eye.getBoundingClientRect();
        if (!rect.width || !rect.height) continue;
        const dx = (event.clientX - rect.left - rect.width / 2) / (rect.width / 2);
        const dy = (event.clientY - rect.top - rect.height / 2) / (rect.height / 2);
        const length = Math.hypot(dx, dy) || 1;
        const clamp = Math.min(length, 1) / length;
        state.eye.style.setProperty('--ix', (dx * clamp).toFixed(3));
        state.eye.style.setProperty('--iy', (dy * clamp).toFixed(3));
    }
}, {passive:true});
document.addEventListener('visibilitychange', () => { if (document.hidden) { cancelAnimationFrame(frameId); frameId=0; } else schedule(); });
reducedMotion.addEventListener('change', () => { for (const [root] of eyes) root.toggleAttribute('data-regard-paused', reducedMotion.matches); if (reducedMotion.matches) { cancelAnimationFrame(frameId); frameId=0; } else schedule(); });
document.addEventListener('tvos:welcome-visibility', schedule);
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, {once:true}); else boot();
