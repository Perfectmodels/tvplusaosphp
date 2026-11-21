{{-- ============================================================
    DISCUSSION INTERNE DE L'ÉMISSION (chat entre les rôles)
    Bulle en bas à gauche + panneau de discussion.

    Un fil par conducteur d'émission : les rôles qui ont accès au
    conducteur (Vue Émission, Vue Rédaction, Antenne) échangent
    des messages court, persistés dans `emission_chat_messages`.

    Temps réel : rafraîchissement par polling léger (10 s) — source
    de vérité tant que le broadcasting n'est pas activé ; si Reverb
    est activé un jour, l'écoute du canal privé `conducteur.{id}`
    (événement `.chat.message`) livre les messages instantanément.

    Auto-suffixant : CSS + JS inline (pas de rebuild du bundle).
    ============================================================ --}}

@php
    $chatConfig = [
        'fetchUrl'      => route('conducteurs.emission.chat.index', ['conducteur' => $conducteur->id]),
        'sendUrl'       => route('conducteurs.emission.chat.store', ['conducteur' => $conducteur->id]),
        'channel'       => 'conducteur.'.$conducteur->id,
        'currentUserId' => auth()->id() ? (int) auth()->id() : null,
        'code'          => (string) ($conducteur->code_conducteur ?? ('#'.$conducteur->id)),
        'subtitle'      => optional($conducteur->emission)->nom,
    ];
@endphp

<style>
    .tv-chat-launch {
        position: fixed;
        left: max(20px, env(safe-area-inset-left));
        bottom: max(20px, env(safe-area-inset-bottom));
        z-index: 46;
        display: flex;
        align-items: center;
        gap: 9px;
        height: 46px;
        padding: 0 16px 0 14px;
        border: 0;
        border-radius: 9999px;
        background: #001a41;
        color: #fff;
        font-size: .875rem;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 10px 28px rgba(0, 26, 65, .30);
    }
    .tv-chat-launch:hover { background: #0a2f6e; }
    .tv-chat-launch i { font-size: 1.05rem; }
    .tv-chat-launch .tv-chat-badge {
        position: absolute;
        top: -5px;
        right: -2px;
        min-width: 19px;
        height: 19px;
        padding: 0 5px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 9999px;
        background: #ba1a1a;
        color: #fff;
        font-size: .68rem;
        font-weight: 700;
        border: 2px solid #fff;
    }
    .tv-chat-panel {
        position: fixed;
        left: max(14px, env(safe-area-inset-left));
        bottom: 78px;
        z-index: 47;
        width: min(380px, calc(100vw - 28px));
        height: min(520px, calc(100dvh - 150px));
        min-height: 320px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: #fff;
        border: 1px solid #dbe2ec;
        border-radius: 16px;
        box-shadow: 0 24px 60px rgba(8, 19, 64, .28);
    }
    .tv-chat-head {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        background: #001a41;
        color: #fff;
        flex: none;
    }
    .tv-chat-head .tv-chat-ic {
        width: 34px;
        height: 34px;
        flex: none;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        background: rgba(255, 255, 255, .12);
        font-size: .95rem;
    }
    .tv-chat-head h2 { font-size: .9rem; font-weight: 700; line-height: 1.2; }
    .tv-chat-head p  { font-size: .7rem; color: #b2c6f7; margin-top: 1px; }
    .tv-chat-close {
        margin-left: auto;
        width: 30px;
        height: 30px;
        flex: none;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: #fff;
        cursor: pointer;
    }
    .tv-chat-close:hover { background: rgba(255, 255, 255, .14); }
    .tv-chat-list {
        flex: 1;
        overflow-y: auto;
        padding: 12px 14px;
        background: #f7fafc;
        display: flex;
        flex-direction: column;
        gap: 9px;
        scroll-behavior: smooth;
    }
    .tv-chat-day {
        align-self: center;
        font-size: .66rem;
        font-weight: 600;
        color: #44474e;
        background: #e0e3e5;
        border-radius: 9999px;
        padding: 2px 10px;
        flex: none;
    }
    .tv-chat-row { display: flex; }
    .tv-chat-row.mine { justify-content: flex-end; }
    .tv-chat-bubble {
        max-width: 84%;
        padding: 7px 11px 6px;
        border-radius: 14px;
        background: #fff;
        border: 1px solid #dbe2ec;
        box-shadow: 0 1px 2px rgba(16, 24, 40, .05);
    }
    .tv-chat-row.mine .tv-chat-bubble {
        background: #001a41;
        border-color: #001a41;
        color: #fff;
    }
    .tv-chat-meta {
        display: flex;
        align-items: baseline;
        gap: 6px;
        font-size: .66rem;
        color: #5b6b81;
        margin-bottom: 2px;
    }
    .tv-chat-meta b { font-weight: 700; color: #181c1e; }
    .tv-chat-row.mine .tv-chat-meta { color: #b2c6f7; }
    .tv-chat-row.mine .tv-chat-meta b { color: #fff; }
    .tv-chat-text {
        font-size: .855rem;
        line-height: 1.45;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .tv-chat-time {
        text-align: right;
        font-size: .62rem;
        color: #8a93a2;
        margin-top: 2px;
    }
    .tv-chat-row.mine .tv-chat-time { color: #8fa9d8; }
    .tv-chat-empty {
        margin: auto;
        text-align: center;
        color: #5b6b81;
        font-size: .8rem;
        padding: 18px;
        line-height: 1.5;
    }
    .tv-chat-empty i { display: block; font-size: 1.5rem; color: #b2c6f7; margin-bottom: 8px; }
    .tv-chat-error {
        flex: none;
        padding: 7px 14px;
        font-size: .74rem;
        color: #ba1a1a;
        background: #fdecec;
        border-top: 1px solid #f5caca;
    }
    .tv-chat-foot {
        flex: none;
        display: flex;
        gap: 8px;
        padding: 10px 12px;
        background: #fff;
        border-top: 1px solid #e6eaf0;
    }
    .tv-chat-input {
        flex: 1;
        min-width: 0;
        border: 1px solid #c5c6cf;
        border-radius: 10px;
        padding: 9px 12px;
        font-size: .875rem;
        color: #181c1e;
        outline: none;
        background: #fff;
    }
    .tv-chat-input:focus { border-color: #001a41; box-shadow: 0 0 0 3px rgba(0, 26, 65, .12); }
    .tv-chat-send {
        flex: none;
        width: 42px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 10px;
        background: #001a41;
        color: #fff;
        cursor: pointer;
    }
    .tv-chat-send:hover { background: #0a2f6e; }
    .tv-chat-send:disabled { opacity: .45; cursor: default; }
    .tv-chat-spin { animation: tv-chat-spin 1s linear infinite; }
    @keyframes tv-chat-spin { to { transform: rotate(360deg); } }
    @media (max-width: 640px) {
        .tv-chat-launch { bottom: calc(76px + env(safe-area-inset-bottom)); }
        .tv-chat-panel { left: 8px; right: 8px; width: auto; bottom: 132px; height: min(460px, calc(100dvh - 180px)); }
    }
</style>

{{-- Configuration passée à Alpine via une variable JS (noms d'émission avec apostrophes OK) --}}
<script>
    window.__tvChatCfg_{{ $conducteur->id }} = @json($chatConfig);
    window.emissionChat = function (cfg) {
        return {
            cfg: cfg || {},
            open: false,
            sending: false,
            error: '',
            draft: '',
            messages: [],
            lastId: 0,
            unread: 0,
            booted: false,
            timer: null,
            busy: false,

            get mineId() { return this.cfg.currentUserId; },
            get canSend() { return this.draft.trim().length > 0 && !this.sending; },

            init() {
                this.$watch('open', (v) => {
                    if (v) {
                        this.unread = 0;
                        this.$nextTick(() => {
                            this.scrollDown(true);
                            if (this.$refs.input) this.$refs.input.focus();
                        });
                    }
                });
                this.load();
                this.timer = window.setInterval(() => this.load(), 10000);
                window.addEventListener('beforeunload', () => window.clearInterval(this.timer));

                // Temps réel (Reverb) si disponible : livraison instantanée.
                if (window.Echo && this.cfg.channel) {
                    try {
                        window.Echo.private(this.cfg.channel).listen('.chat.message', (e) => {
                            if (e && typeof e.id === 'number' && e.id > this.lastId) {
                                const msg = {
                                    id: e.id,
                                    user_id: e.user_id,
                                    name: e.name || 'Membre',
                                    role: '',
                                    message: e.message || '',
                                    time: e.created_at ? new Date(e.created_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '',
                                    day: e.created_at ? new Date(e.created_at).toLocaleDateString('fr-FR') : '',
                                    mine: e.user_id === this.mineId,
                                };
                                if (this.append(msg) && !this.open && !msg.mine) this.unread += 1;
                            }
                        });
                    } catch (err) { /* canal indisponible : le polling continue */ }
                }
            },

            headers() {
                return {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                };
            },

            async load() {
                if (this.busy) return;
                this.busy = true;
                try {
                    const url = this.cfg.fetchUrl + (this.lastId > 0 ? (this.cfg.fetchUrl.includes('?') ? '&' : '?') + 'after=' + this.lastId : '');
                    const resp = await fetch(url, { headers: this.headers() });
                    if (!resp.ok) throw new Error('http_' + resp.status);
                    const data = await resp.json();
                    let nouveaux = 0;
                    (Array.isArray(data.messages) ? data.messages : []).forEach((m) => {
                        if (this.append(m) && !this.open && m.user_id !== this.mineId) nouveaux += 1;
                    });
                    if (nouveaux > 0) this.unread += nouveaux;
                    if (!this.booted) { this.booted = true; if (this.open) this.$nextTick(() => this.scrollDown(true)); }
                    else if (nouveaux > 0 && this.open) this.$nextTick(() => this.scrollDown());
                } catch (err) {
                    // Silencieux : le badge se mettra à jour au prochain cycle.
                } finally {
                    this.busy = false;
                }
            },

            append(m) {
                if (!m || typeof m.id === 'undefined') return false;
                const id = Number(m.id);
                if (this.messages.some((x) => Number(x.id) === id)) return false;
                this.messages.push({
                    id: id,
                    user_id: m.user_id ?? null,
                    name: m.name || 'Membre',
                    role: m.role || '',
                    message: m.message || '',
                    time: m.time || '',
                    day: m.day || '',
                    mine: m.user_id !== undefined && m.user_id !== null && Number(m.user_id) === this.mineId,
                });
                if (id > this.lastId) this.lastId = id;
                return true;
            },

            async send() {
                const texte = this.draft.trim();
                if (!texte || this.sending) return;
                this.sending = true;
                this.error = '';
                try {
                    const body = new URLSearchParams();
                    body.append('message', texte);
                    const resp = await fetch(this.cfg.sendUrl, {
                        method: 'POST',
                        headers: this.headers(),
                        body: body,
                    });
                    if (resp.status === 422) {
                        const data = await resp.json().catch(() => ({}));
                        this.error = data?.errors?.message?.[0] || 'Message invalide.';
                        return;
                    }
                    if (resp.status === 419) {
                        this.error = 'Session expirée — rechargez la page.';
                        return;
                    }
                    if (!resp.ok) throw new Error('http_' + resp.status);
                    const data = await resp.json();
                    if (data?.message) {
                        this.append(data.message);
                        this.draft = '';
                        this.$nextTick(() => this.scrollDown(true));
                    }
                } catch (err) {
                    this.error = 'Envoi impossible — vérifiez votre connexion.';
                } finally {
                    this.sending = false;
                }
            },

            scrollDown(instante) {
                const list = this.$refs.list;
                if (!list) return;
                if (instante) list.style.scrollBehavior = 'auto';
                list.scrollTop = list.scrollHeight;
                if (instante) list.style.scrollBehavior = 'smooth';
            },

            initials(nom) {
                const parts = String(nom || '?').trim().split(/\s+/).slice(0, 2);
                return parts.map((p) => p.charAt(0).toUpperCase()).join('') || '?';
            },
        };
    };
</script>

{{-- Composant unique : bouton lanceur + panneau partagent le même état --}}
<div x-data="emissionChat(window.__tvChatCfg_{{ $conducteur->id }})"
     @tv-chat-open.window="open = true"
     x-init="$watch('open', (isOpen) => $nextTick(() => {
         if (isOpen) $refs.input?.focus();
         else $refs.launch?.focus();
     }))">

    {{-- Bouton lanceur (bas à gauche ; l'assistant IA est en bas à droite) --}}
    <button type="button"
            class="tv-chat-launch"
            x-ref="launch"
            @click="open = !open"
            aria-controls="tv-chat-panel-{{ $conducteur->id }}"
            aria-haspopup="dialog"
            :aria-expanded="open ? 'true' : 'false'"
            aria-label="Discussion de l'émission">
        <i class="fa-solid fa-comment-dots"></i>
        <span>Discussion</span>
        <span x-cloak x-show="unread > 0" class="tv-chat-badge" x-text="unread > 99 ? '99+' : unread"></span>
    </button>

    {{-- Panneau de discussion --}}
    <div x-show="open"
         id="tv-chat-panel-{{ $conducteur->id }}"
         x-cloak
         @keydown.escape.window="open = false"
         class="tv-chat-panel"
         role="dialog"
         aria-modal="false"
         aria-label="Discussion interne de l'émission">
        <header class="tv-chat-head">
            <span class="tv-chat-ic"><i class="fa-solid fa-comments"></i></span>
            <div class="min-w-0">
                <h2>Discussion · {{ $chatConfig['code'] }}</h2>
                <p class="truncate">{{ $chatConfig['subtitle'] ?: 'Édition en cours' }}</p>
            </div>
            <button type="button" class="tv-chat-close" @click="open = false" aria-label="Fermer la discussion">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>

        <div class="tv-chat-list" x-ref="list" role="log"
             aria-label="Messages de la discussion interne" aria-live="polite" aria-relevant="additions">
            <template x-if="messages.length === 0 && booted">
                <div class="tv-chat-empty">
                    <i class="fa-regular fa-comment"></i>
                    Aucun message pour le moment.<br>
                    Lancez la discussion entre les rôles de l'édition.
                </div>
            </template>
            <template x-for="(m, index) in messages" :key="m.id">
                <div>
                    <div x-show="index === 0 || messages[index - 1].day !== m.day" class="tv-chat-day" x-text="m.day"></div>
                    <div class="tv-chat-row" :class="m.mine ? 'mine' : ''">
                        <div class="tv-chat-bubble">
                            <div class="tv-chat-meta">
                                <b x-text="m.mine ? 'Vous' : m.name"></b>
                                <span x-show="m.role" x-text="m.role"></span>
                            </div>
                            <div class="tv-chat-text" x-text="m.message"></div>
                            <div class="tv-chat-time" x-text="m.time"></div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div x-cloak x-show="error" class="tv-chat-error" role="alert" aria-live="assertive" x-text="error"></div>

        <div class="tv-chat-foot">
            <input type="text"
                   class="tv-chat-input"
                   x-model="draft"
                   x-ref="input"
                   @keydown.enter.prevent="send()"
                   maxlength="2000"
                   placeholder="Écrire un message…"
                   aria-label="Votre message">
            <button type="button" class="tv-chat-send" @click="send()" :disabled="!canSend" aria-label="Envoyer">
                <i class="fa-solid fa-paper-plane" :class="sending ? 'tv-chat-spin' : ''"></i>
            </button>
        </div>
    </div>
</div>
