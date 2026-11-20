function initAssistant() {
    const root = document.querySelector('[data-assistant]');
    if (!root) return;

    const dialog = root.querySelector('dialog');
    const launcher = root.querySelector('[data-assistant-open]');
    const form = root.querySelector('[data-assistant-form]');
    const input = form.querySelector('textarea');
    const send = root.querySelector('[data-assistant-send]');
    const reset = root.querySelector('[data-assistant-reset]');
    const messages = root.querySelector('[data-assistant-messages]');
    const status = root.querySelector('[data-assistant-status]');
    const suggestions = root.querySelector('[data-assistant-suggestions]');
    let history = [];
    let pending = false;

    const scroll = () => { messages.scrollTop = messages.scrollHeight; };
    function addMessage(role, content) {
        const item = document.createElement('div');
        item.className = `tv-assistant-message${role === 'user' ? ' tv-assistant-message-user' : ''}`;
        const label = document.createElement('strong');
        label.textContent = role === 'user' ? 'Vous' : 'Assistant';
        const text = document.createElement('p');
        text.textContent = content;
        item.append(label, text);
        messages.append(item);
        scroll();
        return item;
    }

    launcher.addEventListener('click', () => {
        dialog.showModal();
        launcher.setAttribute('aria-expanded', 'true');
        input.focus();
        scroll();
    });
    root.querySelector('[data-assistant-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
        launcher.setAttribute('aria-expanded', 'false');
        launcher.focus();
    });
    dialog.addEventListener('click', (event) => {
        if (event.target !== dialog) return;
        const rect = dialog.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
    });

    reset.addEventListener('click', () => {
        if (pending) return;
        history = [];
        messages.querySelectorAll('.tv-assistant-message:not([data-assistant-greeting])').forEach(item => item.remove());
        suggestions.hidden = false;
        status.textContent = '';
        input.value = '';
        input.focus();
    });

    root.querySelectorAll('[data-assistant-question]').forEach(button => {
        button.addEventListener('click', () => {
            if (pending) return;
            input.value = button.dataset.assistantQuestion;
            form.requestSubmit();
        });
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            if (!pending) form.requestSubmit();
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const question = input.value.trim();
        if (pending || !question) return;

        pending = true;
        send.disabled = true;
        reset.disabled = true;
        input.readOnly = true;
        suggestions.hidden = true;
        messages.setAttribute('aria-busy', 'true');
        status.textContent = 'L’assistant prépare sa réponse…';
        const questionItem = addMessage('user', question);
        input.value = '';
        const controller = new AbortController();
        const timer = window.setTimeout(() => controller.abort(), 65000);

        try {
            const response = await fetch(root.dataset.endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                credentials: 'same-origin',
                signal: controller.signal,
                body: JSON.stringify({ message: question, history: history.slice(-6) }),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                if (response.status === 401 || response.status === 419) throw new Error('Votre session a expiré. Rechargez la page et reconnectez-vous.');
                const validation = data.errors ? Object.values(data.errors).flat()[0] : null;
                throw new Error(response.status >= 500 && response.status !== 503
                    ? 'L’assistant est momentanément indisponible. Réessayez dans quelques instants.'
                    : validation || data.message || 'La question n’a pas pu être envoyée. Réessayez.');
            }
            if (typeof data.answer !== 'string' || !data.answer.trim()) throw new Error('L’assistant n’a pas renvoyé de réponse. Réessayez.');
            addMessage('assistant', data.answer);
            history = [...history, { role: 'user', content: question }, { role: 'assistant', content: data.answer }].slice(-6);
            status.textContent = '';
        } catch (error) {
            questionItem.remove();
            input.value = question;
            status.textContent = error.name === 'AbortError' ? 'La réponse prend trop de temps. Réessayez.'
                : error instanceof TypeError ? 'Connexion interrompue. Vérifiez votre connexion et réessayez.' : error.message;
            suggestions.hidden = history.length > 0;
        } finally {
            window.clearTimeout(timer);
            pending = false;
            send.disabled = false;
            reset.disabled = false;
            input.readOnly = false;
            messages.setAttribute('aria-busy', 'false');
            if (dialog.open) input.focus();
        }
    });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAssistant, { once: true });
else initAssistant();
