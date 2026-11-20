const intro = document.querySelector('#tvos-welcome-intro');
if (intro) {
    const audio = intro.querySelector('#tvos-intro-audio');
    const enter = intro.querySelector('#tvos-intro-enter');
    const status = intro.querySelector('#tvos-intro-status');
    const progress = intro.querySelector('#tvos-intro-progress');
    const clock = intro.querySelector('#tvos-intro-time');
    const playback = intro.querySelector('.tvos-intro__playback');
    const eye = intro.querySelector('[data-regard]');
    let attempt = 0;
    let timeout = 0;
    let completed = false;
    let resumeWhenVisible = document.hidden;
    const seconds = value => `${Math.floor(value / 60)}:${String(Math.floor(value % 60)).padStart(2, '0')}`;
    function state(value, message) {
        intro.dataset.state = value;
        status.textContent = message;
        enter.hidden = !['blocked', 'paused', 'error'].includes(value);
        enter.disabled = enter.hidden;
        enter.querySelector('[data-play-icon]').textContent = value === 'blocked' ? 'volume_up' : 'play_arrow';
        enter.querySelector('[data-play-label]').textContent = value === 'paused' ? 'Reprendre l’introduction' : value === 'error' ? 'Relancer l’introduction' : 'Activer le son';
        eye?.classList.toggle('is-speaking', value === 'playing');
    }
    function playing() {
        if (completed) return;
        if (document.hidden) { resumeWhenVisible = true; audio.pause(); return; }
        clearTimeout(timeout); if (playback) playback.hidden = false;
        state('playing', 'Bienvenue dans TV+A OS. La connexion s’ouvrira à la fin de l’introduction.');
    }
    function fail() {
        if (completed) return;
        attempt++; clearTimeout(timeout); audio.pause();
        state('error', 'La voix n’a pas pu être chargée. Vérifiez votre connexion puis relancez l’introduction.');
    }
    async function play() {
        if (completed) return;
        if (document.hidden) { resumeWhenVisible = true; return; }
        if (!audio.paused) { playing(); return; }
        if (intro.dataset.state === 'error') { audio.load(); try { audio.currentTime = 0; } catch { /* Playback starts when metadata arrives. */ } }
        const current = ++attempt;
        audio.muted = false; audio.volume = 1;
        state('loading', 'L’introduction sonore démarre. La connexion s’ouvrira à la fin.');
        clearTimeout(timeout); timeout = setTimeout(() => { if (current === attempt && intro.dataset.state === 'loading') fail(); }, 10000);
        try {
            await audio.play();
            if (current !== attempt || completed) return;
            playing();
        } catch (error) {
            if (current !== attempt) return;
            clearTimeout(timeout);
            if (error.name === 'NotAllowedError') {
                state('blocked', 'Touchez l’écran pour démarrer l’introduction sonore.');
                // Overlay plein écran pour capter le premier geste utilisateur
                if (!document.getElementById('tvos-intro-tap-overlay')) {
                    const overlay = document.createElement('div');
                    overlay.id = 'tvos-intro-tap-overlay';
                    overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.4);cursor:pointer;';
                    overlay.innerHTML = '<div style="text-align:center;color:#fff;font-size:1.2rem;font-weight:600;padding:2rem;"><i class="fa-solid fa-volume-high" style="font-size:2rem;display:block;margin-bottom:1rem;"></i>Touchez l’écran pour démarrer</div>';
                    overlay.addEventListener('click', () => { overlay.remove(); void play(); }, { once: true });
                    overlay.addEventListener('touchstart', () => { overlay.remove(); void play(); }, { once: true });
                    document.body.appendChild(overlay);
                }
            } else { fail(); }
        }
    }
    enter.addEventListener('click', play);
    audio.addEventListener('playing', playing);
    audio.addEventListener('timeupdate', () => {
        const duration = Number.isFinite(audio.duration) ? audio.duration : 0;
        if (progress) { progress.max = duration || 1; progress.value = audio.currentTime; }
        if (clock) clock.textContent = duration ? `${seconds(audio.currentTime)} / ${seconds(duration)}` : seconds(audio.currentTime);
    });
    audio.addEventListener('error', fail, true);
    audio.addEventListener('pause', () => {
        if (!completed && !audio.ended && intro.dataset.state === 'playing') state('paused', 'L’introduction est en pause. Reprenez-la pour accéder à la connexion.');
    });
    audio.addEventListener('ended', () => {
        if (completed || !audio.ended) return;
        completed = true; clearTimeout(timeout);
        state('complete', 'Introduction terminée. Ouverture de votre espace de connexion…');
        intro.classList.add('is-closing');
        setTimeout(() => location.assign(intro.dataset.destination), matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 350);
    });
    document.addEventListener('visibilitychange', () => {
        if (completed) return;
        if (document.hidden && !audio.paused) { resumeWhenVisible = true; audio.pause(); }
        else if (!document.hidden && resumeWhenVisible) { resumeWhenVisible = false; void play(); }
    });
    addEventListener('pagehide', () => { attempt++; clearTimeout(timeout); audio.pause(); });
    addEventListener('pageshow', event => {
        if (!event.persisted) return;
        completed = false; intro.classList.remove('is-closing');
        try { audio.currentTime = 0; } catch { /* The first click loads the recording. */ }
        if (progress) progress.value = 0;
        if (playback) playback.hidden = true;
        state('loading', 'L’introduction sonore démarre. La connexion s’ouvrira à la fin.');
        void play();
    });
    document.documentElement.classList.add('tvos-intro-active');
    void play();
}
