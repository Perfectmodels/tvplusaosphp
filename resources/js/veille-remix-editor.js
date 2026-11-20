import { Editor } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Image from '@tiptap/extension-image'
import Placeholder from '@tiptap/extension-placeholder'
import Underline from '@tiptap/extension-underline'
import TextAlign from '@tiptap/extension-text-align'
import Alpine from 'alpinejs'

Alpine.data('veilleRemixEditor', () => ({

    init() {
        const mount = document.getElementById('tiptap-editor');
        const content = mount ? mount.innerHTML : '';
        const existingRid = document.getElementById('remix-id')?.value;
        this.editor = new Editor({
            element: mount,
            extensions: [
                StarterKit.configure({ heading: { levels:[1,2,3] } }),
                Link.configure({ openOnClick:false, autolink:true }),
                Image,
                Placeholder.configure({ placeholder: 'Le contenu généré par IA apparaîtra ici… Tu peux aussi écrire directement.' }),
                Underline,
                TextAlign.configure({ types:['heading','paragraph'] }),
            ],
            content: content || '',
            onUpdate: () => this.onContentChange(),
            onCreate: () => this.addMenuBar(this.editor, mount),
        });
        this.bindAiButtons();
        if (existingRid) { this._remixSaved = true; this._dirty = false; }
        this.$nextTick(() => this.initAutosave());
        if (window._remixInitialVersion) this.pushVersion(window._remixInitialVersion);
    },

    addMenuBar(editor, mount) {
        const bar = document.createElement('div');
        bar.className = 'sticky top-0 z-10 flex flex-wrap gap-1.5 items-center p-3 mb-3 rounded-xl bg-surface-container border border-outline-variant';
        const grp = (html) => { const d = document.createElement('div'); d.className='flex gap-1 border-r border-outline-variant pr-2 mr-1'; d.innerHTML = html; return d; };
        bar.appendChild(grp(`<button type=button title=Gras class="tb-bold rounded-lg border border-outline px-2.5 py-1.5 text-sm bg-surface hover:bg-surface-container"><i class="fa-solid fa-bold"></i></button>
            <button type=button title=Italique class="tb-italic rounded-lg border border-outline px-2.5 py-1.5 text-sm bg-surface hover:bg-surface-container"><i class="fa-solid fa-italic"></i></button>
            <button type=button title=Souligne class="tb-underline rounded-lg border border-outline px-2.5 py-1.5 text-sm bg-surface hover:bg-surface-container"><i class="fa-solid fa-underline"></i></button>
            <button type=button title=Barré class="tb-strike rounded-lg border border-outline px-2.5 py-1.5 text-sm bg-surface hover:bg-surface-container"><i class="fa-solid fa-strikethrough"></i></button>`));
        bar.appendChild(grp(`<button type=button title="Titre 1" class="tb-h1 rounded-lg border border-outline px-2.5 py-1.5 text-sm bg-surface hover:bg-surface-container font-bold">H1</button>
            <button type=button title="Titre 2" class="tb-h2 rounded-lg border border-outline px-2.5 py-1.5 text-sm bg-surface hover:bg-surface-container font-bold">H2</button>
            <button type=button title="Paragraphe" class="tb-paragraph rounded-lg border border-outline px-2.5 py-1.5 text-sm bg-surface hover:bg-surface-container">¶</button>`));
        bar.appendChild(grp(`<button type=button title="Liste à puces" class="tb-bulletList rounded-lg border border-outline px-2.5 py-1.5 text-sm bg-surface hover:bg-surface-container"><i class="fa-solid fa-list-ul"></i></button>
            <button type=button title="Liste ordonnée" class="tb-orderedList rounded-lg border border-outline px-2.5 py-1.5 text-sm bg-surface hover:bg-surface-container"><i class="fa-solid fa-list-ol"></i></button>
            <button type=button title=Citations class="tb-blockquote rounded-lg border border-outline px-2.5 py-1.5 text-sm bg-surface hover:bg-surface-container"><i class="fa-solid fa-quote-left"></i></button>
            <button type=button title=Séparateur class="tb-horizontalRule rounded-lg border border-outline px-2.5 py-1.5 text-sm bg-surface hover:bg-surface-container"><i class="fa-solid fa-minus"></i></button>`));
        bar.appendChild(grp(`<button type=button title="Aligner à gauche" class="tb-left rounded-lg border border-outline px-2.5 py-1.5 text-sm bg-surface hover:bg-surface-container"><i class="fa-solid fa-align-left"></i></button>
            <button type=button title=Centrer class="tb-center rounded-lg border border-outline px-2.5 py-1.5 text-sm bg-surface hover:bg-surface-container"><i class="fa-solid fa-align-center"></i></button>
            <button type=button title="Aligner à droite" class="tb-right rounded-lg border border-outline px-2.5 py-1.5 text-sm bg-surface hover:bg-surface-container"><i class="fa-solid fa-align-right"></i></button>`));
        bar.appendChild(grp(`<button type=button id=btn-link title="Insérer lien" class="rounded-lg border border-outline px-2.5 py-1.5 text-sm bg-surface hover:bg-surface-container"><i class="fa-solid fa-link"></i></button>
            <button type=button id=btn-img title="Insérer image" class="rounded-lg border border-outline px-2.5 py-1.5 text-sm bg-surface hover:bg-surface-container"><i class="fa-regular fa-image"></i></button>
            <button type=button title="Annuler" class="tb-undo rounded-lg border border-outline px-2.5 py-1.5 text-sm bg-surface hover:bg-surface-container"><i class="fa-solid fa-rotate-left"></i></button>
            <button type=button title="Rétablir" class="tb-redo rounded-lg border border-outline px-2.5 py-1.5 text-sm bg-surface hover:bg-surface-container"><i class="fa-solid fa-rotate-right"></i></button>`));
        editor.on('selectionUpdate', () => {
            const sel = { bold: editor.isActive('bold'), italic: editor.isActive('italic'),
                underline: editor.isActive('underline'), strike: editor.isActive('strike'),
                h1: editor.isActive('heading',{level:1}), h2: editor.isActive('heading',{level:2}),
                paragraph: editor.isActive('paragraph'), bulletList: editor.isActive('bulletList'),
                orderedList: editor.isActive('orderedList'), blockquote: editor.isActive('blockquote'),
                left: editor.isActive({textAlign:'left'}), center: editor.isActive({textAlign:'center'}), right: editor.isActive({textAlign:'right'}) };
            bar.querySelectorAll('.tb-bold,.tb-italic,.tb-underline,.tb-strike,.tb-h1,.tb-h2,.tb-paragraph,.tb-bulletList,.tb-orderedList,.tb-blockquote,.tb-horizontalRule,.tb-left,.tb-center,.tb-right,.tb-undo,.tb-redo').forEach(b => {
                const k = b.className.match(/tb-([A-Za-z]+)/)?.[1] || '';
                const on = !!sel[k];
                b.classList.toggle('bg-primary/20', on);
                b.classList.toggle('text-primary', on);
            });
        });
        const bind = (q, fn) => { const b = bar.querySelector(q); if (b) b.addEventListener('click', fn); };
        bind('.tb-bold',        () => editor.chain().focus().toggleBold().run());
        bind('.tb-italic',      () => editor.chain().focus().toggleItalic().run());
        bind('.tb-underline',   () => editor.chain().focus().toggleUnderline().run());
        bind('.tb-strike',      () => editor.chain().focus().toggleStrike().run());
        bind('.tb-h1',          () => editor.chain().focus().toggleHeading({level:1}).run());
        bind('.tb-h2',          () => editor.chain().focus().toggleHeading({level:2}).run());
        bind('.tb-paragraph',   () => editor.chain().focus().setParagraph().run());
        bind('.tb-bulletList',  () => editor.chain().focus().toggleBulletList().run());
        bind('.tb-orderedList', () => editor.chain().focus().toggleOrderedList().run());
        bind('.tb-blockquote',  () => editor.chain().focus().toggleBlockquote().run());
        bind('.tb-horizontalRule', () => editor.chain().focus().setHorizontalRule().run());
        bind('.tb-left',        () => editor.chain().focus().setTextAlign('left').run());
        bind('.tb-center',      () => editor.chain().focus().setTextAlign('center').run());
        bind('.tb-right',       () => editor.chain().focus().setTextAlign('right').run());
        bind('.tb-undo',        () => editor.chain().focus().undo().run());
        bind('.tb-redo',        () => editor.chain().focus().redo().run());
        const linkBtn = document.getElementById('btn-link');
        if (linkBtn) linkBtn.addEventListener('click', () => {
            const u = prompt('URL du lien :', 'https://');
            if (u && /^https?:\/\//i.test(u)) editor.chain().focus().extendMarkRange('link').setLink({href:u}).run();
        });
        const imgBtn = document.getElementById('btn-img');
        if (imgBtn) imgBtn.addEventListener('click', () => {
            const u = prompt('URL de l\'image :', 'https://');
            if (u && /^https?:\/\//i.test(u)) editor.chain().focus().setImage({src:u}).run();
        });
        mount.parentNode.insertBefore(bar, mount);
    },

    bindAiButtons() {
        const btns = document.querySelectorAll('[data-ai-action]');
        btns.forEach(b => b.addEventListener('click', () => this.runAi(b.dataset.aiAction)));
        const saveBtn = document.getElementById('btn-save-draft');
        if (saveBtn) {
            saveBtn.addEventListener('click', (ev) => {
                ev.preventDefault(); ev.stopPropagation();
                console.log('[SAUV] Clic bouton 💾 Sauvegarder');
                this.save(true);
            });
        }
        const valBtn = document.getElementById('btn-validate');
        if (valBtn) {
            valBtn.addEventListener('click', (ev) => {
                ev.preventDefault(); ev.stopPropagation();
                console.log('[SAUV] Clic bouton ✅ Valider et publier');
                this.validate();
            });
        }
        console.log('[SAUV] Boutons header bindés : save=' + !!saveBtn + ' validate=' + !!valBtn);
    },

    _autosaveTimer: null, _dirty: false, _saving: false,
    onContentChange() { this._dirty = true; },
    initAutosave() { setInterval(() => { if (this._dirty && !this._saving) this.save(false); }, 10000); },

    _baseOr(meta, fallback) {
        const node = document.getElementById(meta);
        return (node && node.value) ? node.value : fallback;
    },

    async save(manual) {
        if (!this.editor) return;
        const sid  = this._baseOr('subject-id', '');
        const rid  = this._baseOr('remix-id', '');
        const tok  = this._baseOr('save-token', '');
        const last = document.getElementById('last-saved');
        const title= this._baseOr('remix-title', 'Sans titre');
        const type = this._baseOr('remix-type', 'angle');
        const tone = this._baseOr('remix-tone', 'journaliste');
        const lang = this._baseOr('remix-lang', 'fr');
        const content = this.editor.getHTML();
        const url = rid ? this._baseOr('update-url', '') : this._baseOr('store-url', '');
        if (!sid || !url) return;

        const badge = document.getElementById('remix-status-text');
        this._saving = true;
        badge.textContent = manual ? 'Sauvegarde manuelle…' : 'Sauvegarde auto…';
        const body = new FormData();
        body.append('_token', tok);
        body.append('_method', rid ? 'PUT' : 'POST');
        body.append('subject_id', sid);
        body.append('title', title);
        body.append('type',  type);
        body.append('tone',  tone);
        body.append('language', lang);
        body.append('content', content);
        body.append('version[source]', this._lastAiSource || 'manual');

        const urlBase   = this._baseOr('app-url', '');
        const redirectBack = this._baseOr('redirect-back-url', '/veille/sujets');

        try {
            const r = await fetch(url, { method:'POST', body, credentials:'same-origin', redirect:'manual' });
            const ct = r.headers.get('content-type') || '';
            if (ct.includes('application/json')) {
                const j = await r.json();
                if (j.success) {
                    if (!rid && j.remix_id) {
                        document.getElementById('remix-id').value = j.remix_id;
                        document.getElementById('update-url').value   = urlBase + `/veille/remixes/${j.remix_id}`;
                        document.getElementById('validate-url').value = urlBase + `/veille/remixes/${j.remix_id}/validate`;
                        const el = document.getElementById('redirect-back-url');
                        if (el && el.value.startsWith('/veille/sujets/')) el.value = redirectBack;
                    }
                    badge.textContent = j.status === 'validated' ? 'Validé' : 'Brouillon';
                    const now = new Date();
                    last.textContent = `Dernière sauvegarde ${String(now.getDate()).padStart(2,'0')}/${String(now.getMonth()+1).padStart(2,'0')} ${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}`;
                    if (j.versions_count != null) document.getElementById('versions-count').textContent = j.versions_count + ' version(s)';
                    this._dirty = false;
                    this._remixSaved = true;
                    this.pushVersion({ ts: now.toISOString(), title, content, source: this._lastAiSource || 'manual', auto: !manual });
                }
            }
        } catch(e) { console.warn(e); badge.textContent = 'Erreur sauvegarde'; }
        finally { this._saving = false; }
    },

    async validate() {
        console.log('[SAUV] validate() appelée. _remixSaved=' + this._remixSaved + ' rid=' + this._baseOr('remix-id',''));
        try {
            const rid0 = this._baseOr('remix-id', '');
            if (!this._remixSaved || rid0 === '') {
                console.log('[SAUV] validate() → sauvegarde préalable requise');
                await this.save(true);
            }
            const rid = this._baseOr('remix-id', '');
            const tok = this._baseOr('save-token', '');
            const url = this._baseOr('validate-url', '');
            const redirectBack = this._baseOr('redirect-back-url', '/veille/sujets');
            console.log('[SAUV] validate() params rid=' + rid + ' url=' + url + ' redirect=' + redirectBack);
            if (!rid || !url) { alert('Sauvegarde d\'abord ce remix.'); return; }
            const body = new FormData();
            body.append('_token', tok);
            body.append('_method', 'PATCH');
            console.log('[SAUV] validate() → fetch PATCH ' + url);
            const r = await fetch(url, { method:'POST', body, credentials:'same-origin', redirect:'manual' });
            console.log('[SAUV] validate() → réponse status=' + r.status + ' ok=' + r.ok + ' type=' + r.type);
            const isRedirectSuccess = (r.type === 'opaqueredirect') || (r.status === 0) || (r.status === 302) || (r.status === 200) || r.ok;
            if (isRedirectSuccess) {
                console.log('[SAUV] validate() → SUCCES → redirect vers ' + redirectBack);
                const badge = document.getElementById('remix-status-text');
                if (badge) badge.textContent = 'Validé';
                location.href = redirectBack;
            } else {
                console.error('[SAUV] validate() → statut invalide', r);
                let detail = 'status ' + r.status;
                try { const t = await r.text(); if (t) detail += ' | ' + t.slice(0, 200); } catch(_) {}
                alert('Erreur lors de la validation (' + detail + ')');
            }
        } catch (err) {
            console.error('[SAUV] validate() ERREUR:', err);
            alert('Erreur lors de la validation : ' + (err?.message || err));
        }
    },

    versions: [],
    pushVersion(v) {
        if (!v) return;
        this.versions.push(v);
        const list = document.getElementById('versions-list');
        if (this.versions.length === 1 && list.querySelector('span.italic')) list.innerHTML = '';
        const pill = document.createElement('button');
        pill.type = 'button';
        pill.className = 'inline-flex items-center gap-1.5 rounded-full bg-surface-container border border-outline-variant hover:bg-surface-container-highest px-3 py-1.5 text-xs text-on-surface font-medium transition';
        const d = new Date(v.ts);
        const label = (v.source && v.source !== 'manual') ? `🤖 v${this.versions.length}` : `✏️ v${this.versions.length}`;
        pill.innerHTML = `${label} <span class="text-on-surface-variant">${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}</span>`;
        pill.addEventListener('click', async () => {
            const confirmation = window.TvAlert?.confirm
                ? await window.TvAlert.confirm({
                    title: 'Restaurer cette version ?',
                    text: 'Le contenu actuel de l\'éditeur sera remplacé.',
                    confirmText: 'Restaurer',
                })
                : { isConfirmed: confirm('Restaurer cette version ?') };
            if (! confirmation.isConfirmed) return;
            document.getElementById('remix-title').value = v.title || '';
            if (this.editor) this.editor.commands.setContent(v.content || '');
            this._dirty = true;
        });
        list.appendChild(pill);
    },

    versions: [],
    busy: false,
    _lastAiSource: null,
    _aiContentTimer: null,
    _aiPending: '',
    _autosaveTimer: null,
    _dirty: false,
    _saving: false,
    _remixSaved: false,
    _aiStreamActive: false,
    _aiTwTimer: null,
    _aiTwQueue: '',
    _aiTwActive: false,
    _aiTwTargetLen: 0,
    _aiStreamDone: false,
    _aiFinalFlushed: false,
    _aiSnapTimer: null,

    _toPlainHtml(txt) {
        if (!txt) return '';
        const esc = txt.replace(/[&<>]/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;' }[c]));
        return esc.split('\n').map(p => p ? `<p>${p}</p>` : '<p><br></p>').join('');
    },
    _setEditorHtml(html) {
        const editor = this.editor;
        if (!editor) return false;
        const targetHtml = html || '<p></p>';
        let ok = false;
        try {
            ok = editor.commands.setContent(targetHtml, { emitUpdate: true, preserveWhitespace: 'full' });
            if (!ok && editor.state) {
                try {
                    const slice = editor.schema.parse(targetHtml);
                    const tr = editor.state.tr.replaceWith(0, editor.state.doc.content.size, slice);
                    editor.view.dispatch(tr);
                    ok = true;
                } catch (_tr) {}
            }
        } catch (e1) {
            try {
                const slice = editor.schema.parse(targetHtml);
                const tr = editor.state.tr.replaceWith(0, editor.state.doc.content.size, slice);
                editor.view.dispatch(tr);
                ok = true;
            } catch (_tr) {}
        }
        if (!ok) {
            try {
                const pm = document.querySelector('.ProseMirror[contenteditable="true"]');
                if (pm) { pm.innerHTML = targetHtml; ok = true; }
            } catch (_dom) {}
        }
        return ok;
    },
    _aiLastRenderedLen: 0,
    _aiDebounce(action, initialExisting, generated) {
        if (!this.editor) return;
        this._aiPending = generated;
        this._aiTypewriterAppend(generated);
    },
    _aiResetRendered() {
        this._aiLastRenderedLen = 0;
        clearTimeout(this._aiContentTimer);
        this._aiContentTimer = null;
        this._aiTwQueue = '';
        this._aiTwTargetLen = 0;
        this._aiTwActive = false;
        this._aiStreamDone = false;
        this._aiFinalFlushed = false;
        if (this._aiTwTimer) { clearInterval(this._aiTwTimer); this._aiTwTimer = null; }
    },
    _aiTypewriterStart() {
        if (this._aiTwActive || this._aiTwTimer) return;
        this._aiTwActive = true;
        const CHARS_PER_STEP = 11;
        const STEP_MS = 28;
        this._aiTwTimer = setInterval(() => {
            try {
                const queueEmpty = !this._aiTwQueue;
                const fullyRendered = this._aiLastRenderedLen >= this._aiTwTargetLen;
                if (queueEmpty && fullyRendered) {
                    if (this._aiStreamDone && !this._aiFinalFlushed) {
                        this._aiFinalFlushed = true;
                        if (this._aiTwTimer) { clearInterval(this._aiTwTimer); this._aiTwTimer = null; }
                        this._aiTwActive = false;
                        const full = this._aiPending || '';
                        const html = this._toPlainHtml(full);
                        try { this._setEditorHtml(html); } catch(_) {}
                        setTimeout(() => { try { this._setEditorHtml(html); } catch(_) {} }, 60);
                        setTimeout(() => { try { this._setEditorHtml(html); } catch(_) {} }, 260);
                        const status = document.getElementById('ai-status');
                        const prog = document.getElementById('stream-progress');
                        const wc = (this._aiPending || '').trim().split(/\s+/).filter(Boolean).length;
                        if (status) status.innerHTML = '<i class="fa-regular fa-circle-check mr-1.5"></i> Terminé · ' + wc + ' mots générés';
                        if (prog) setTimeout(() => { prog.classList.remove('flex'); prog.classList.add('hidden'); }, 1200);
                        try { this.save(false); } catch(_) {}
                        return;
                    }
                    if (!this.busy && !this._aiStreamActive) {
                        if (this._aiTwTimer) { clearInterval(this._aiTwTimer); this._aiTwTimer = null; }
                        this._aiTwActive = false;
                    }
                    return;
                }
                let chunk = '';
                if (this._aiTwQueue) {
                    const take = Math.min(CHARS_PER_STEP, this._aiTwQueue.length);
                    chunk = this._aiTwQueue.slice(0, take);
                    this._aiTwQueue = this._aiTwQueue.slice(take);
                }
                if (!chunk && this.busy && !queueEmpty) return;
                const editor = this.editor;
                if (!editor) return;
                if (this._aiLastRenderedLen === 0) {
                    const base = (this._aiPending || '').slice(0, Math.max(14, CHARS_PER_STEP));
                    if (base) {
                        const html = this._toPlainHtml(base);
                        this._setEditorHtml(html);
                        this._aiLastRenderedLen = base.length;
                    }
                    return;
                }
                if (!chunk) return;
                try {
                    let done = false;
                    try {
                        const endPos = editor.state.doc.content.size;
                        const tr = editor.state.tr.insertText(chunk, endPos);
                        tr.setMeta('addToHistory', false);
                        editor.view.dispatch(tr);
                        done = true;
                    } catch (_) { done = false; }
                    if (!done) {
                        const full = (this._aiPending || '').slice(0, this._aiLastRenderedLen + chunk.length);
                        const html = this._toPlainHtml(full);
                        this._setEditorHtml(html);
                    }
                    try { editor.commands.focus('end'); } catch(_) {}
                } catch (e) {
                    try {
                        const full = (this._aiPending || '');
                        const html = this._toPlainHtml(full);
                        this._setEditorHtml(html);
                    } catch(_) {}
                }
                this._aiLastRenderedLen += chunk.length;
            } catch (_err) {
                try {
                    const fallbackLen = Math.min(this._aiTwTargetLen || (this._aiPending||'').length, this._aiLastRenderedLen + 50);
                    const full = (this._aiPending || '').slice(0, fallbackLen);
                    const html = this._toPlainHtml(full);
                    try { this._setEditorHtml(html); } catch(_) {}
                    this._aiLastRenderedLen = fallbackLen;
                } catch(_2) {}
            }
        }, STEP_MS);
    },
    _aiTypewriterAppend(fullText) {
        const curTarget = this._aiTwTargetLen | 0;
        const newText = fullText.slice(curTarget);
        this._aiTwTargetLen = fullText.length;
        if (newText) this._aiTwQueue += newText;
        this._aiTypewriterStart();
    },
    _aiTypewriterFinish() {
        let guards = 0;
        while ((this._aiTwQueue || this._aiLastRenderedLen < this._aiTwTargetLen) && guards < 2000) {
            const step = 200;
            const take = Math.min(step, this._aiTwQueue.length || Math.max(0, this._aiTwTargetLen - this._aiLastRenderedLen));
            if (take <= 0) break;
            let chunk = '';
            if (this._aiTwQueue) { chunk = this._aiTwQueue.slice(0, take); this._aiTwQueue = this._aiTwQueue.slice(take); }
            else {
                const base = this._aiPending || '';
                chunk = base.slice(this._aiLastRenderedLen, this._aiLastRenderedLen + take);
            }
            if (!chunk) break;
            try {
                const editor = this.editor;
                if (editor) {
                    try {
                        const endPos = editor.state.doc.content.size;
                        const tr = editor.state.tr.insertText(chunk, endPos);
                        tr.setMeta('addToHistory', false);
                        editor.view.dispatch(tr);
                    } catch (_) {
                        const full = this._aiPending || '';
                        this._setEditorHtml(this._toPlainHtml(full));
                    }
                    try { editor.commands.focus('end'); } catch(_) {}
                }
            } catch(_) {}
            this._aiLastRenderedLen += chunk.length;
            guards++;
        }
        if (this._aiTwTimer) { clearInterval(this._aiTwTimer); this._aiTwTimer = null; }
        this._aiTwActive = false;
    },

    async runAi(action) {
        if (this.busy) return;
        const sid    = this._baseOr('subject-id', '');
        const type   = this._baseOr('remix-type', 'angle');
        const tone   = this._baseOr('remix-tone', 'journaliste');
        const lang   = this._baseOr('remix-lang', 'fr');
        const tok    = this._baseOr('save-token', '');
        const streamUrl = this._baseOr('ai-stream-url', '');
        const status= document.getElementById('ai-status');
        const prog  = document.getElementById('stream-progress');
        const count = document.getElementById('stream-count');
        const existing = (action === 'generate' || action === 'titles') ? '' : (this.editor ? this.editor.getText() : '');
        if (['fix','translate','expand','rephrase'].includes(action) && !existing.trim()) {
            alert('Sélectionne du contenu avant d\'utiliser cette action.');
            return;
        }

        this.busy = true;
        status.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-1.5 text-primary"></i> Appel Groq IA en cours…';
        prog.classList.remove('hidden'); prog.classList.add('flex');
        count.textContent = '0 mots';

        const params = new URLSearchParams({ subject_id:sid, action, type, tone, language:lang });
        const body = new FormData();
        body.append('_token', tok);
        body.append('subject_id', sid);
        body.append('action', action);
        body.append('type', type);
        body.append('tone', tone);
        body.append('language', lang);
        body.append('existing_text', existing);

        this._lastAiSource = action;
        let generated = '';

        if (action === 'generate' || action === 'titles') {
            this._aiPending = '';
            this._aiResetRendered();
            try { if (this.editor) this.editor.commands.setContent('<p></p>'); } catch(_) {}
        } else {
            this._aiResetRendered();
            this._aiLastRenderedLen = (existing || '').length;
            this._aiPending = existing;
        }

        try {
            console.log('[RUNAI] POST → streamUrl=', streamUrl, 'sid=', sid, 'action=', action);
            const r = await fetch(streamUrl + '?' + params.toString(), {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Accept':'text/event-stream', 'X-CSRF-TOKEN': tok, 'X-Requested-With':'XMLHttpRequest' },
                body,
            });
            console.log('[RUNAI] Response status=', r.status, 'ok=', r.ok);
            if (!r.ok) {
                let msg = 'Erreur réseau HTTP ' + r.status;
                try {
                    const ct = r.headers.get('content-type') ?? '';
                    if (ct.includes('application/json')) {
                        const j = await r.json();
                        if (j?.message) msg += ' — ' + j.message;
                        else if (j?.error) msg += ' — ' + j.error;
                    } else {
                        const txt = (await r.text()).slice(0, 200);
                        if (txt) msg += ' — extrait : ' + txt.replace(/\s+/g,' ');
                    }
                } catch(_) {}
                throw new Error(msg);
            }
            if (!r.body) throw new Error('Pas de corps de réponse (stream vide)');
            const ct = r.headers.get('content-type') ?? '';
            if (!ct.includes('text/event-stream')) {
                let extra = '';
                try {
                    const txt = await r.clone().text();
                    const m = txt.match(/<title>([^<]+)<\/title>[\s\S]*?(?:<h1[^>]*>([^<]*))?/);
                    const head = m ? (m[2] ? m[2].trim() + ' — ' : '') + (m[1] ? m[1].trim() : '') : '';
                    extra = ' — ' + (head ? head : ('extrait: ' + txt.slice(0, 280).replace(/\s+/g,' ')));
                } catch(_) {}
                throw new Error('Réponse inattendue (type="' + ct.slice(0,50) + '")' + extra);
            }

            const reader = r.body.getReader();
            const dec = new TextDecoder('utf-8');
            let buf = '', wc = 0, lastEvent = '';
            while (true) {
                const {done, value} = await reader.read();
                if (done) break;
                buf += dec.decode(value, {stream:true});
                const lines = buf.split('\n');
                const last  = lines.pop();
                buf = last ?? '';
                for (const line of lines) {
                    const t = line.trim();
                    if (!t) continue;
                    if (t.startsWith('event:')) { lastEvent = t.slice(6).trim(); continue; }
                    if (!t.startsWith('data:')) continue;
                    const payload = t.slice(5).trim();
                    let json; try { json = JSON.parse(payload); } catch { lastEvent=''; continue; }
                    if (lastEvent === 'error' || json.message) {
                        const m = json.message || 'erreur inconnue';
                        lastEvent = '';
                        throw new Error(m);
                    }
                    if (typeof json.text === 'string' && json.text !== '') {
                        generated += json.text;
                        wc = generated.trim().split(/\s+/).filter(Boolean).length;
                        count.textContent = wc + ' mots';
                        if (this.busy) this._aiDebounce(action, existing, generated);
                    } else if (json.full_text) {
                        generated = json.full_text;
                    }
                    lastEvent = '';
                }
            }
            console.log('[RUNAI] terminé wc=', wc);
            clearTimeout(this._aiContentTimer);
            this._aiContentTimer = null;
            if (generated !== '') {
                this._aiPending = generated;
                this._aiTwTargetLen = generated.length;
                this._aiStreamDone = true;
                this._aiTypewriterAppend(generated);
            }
            status.innerHTML = '<i class="fa-solid fa-keyboard mr-1.5 text-primary"></i> Écriture en cours… ' + wc + ' mots';
        } catch(e) {
            console.error('[RUNAI] ERREUR:', e);
            const mm = (e.message || String(e));
            status.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-error mr-1.5"></i> Erreur : ' + mm;
            status.title = mm;
            prog.classList.remove('flex'); prog.classList.add('hidden');
        } finally { this.busy = false; }
    },

}));


