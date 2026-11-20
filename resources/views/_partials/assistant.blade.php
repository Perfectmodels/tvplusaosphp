<div data-assistant data-endpoint="{{ route('assistant.messages') }}">
    <button type="button" class="tv-assistant-launch" data-assistant-open aria-haspopup="dialog" aria-controls="tv-assistant-dialog" aria-expanded="false">
        <span class="material-symbols-outlined" aria-hidden="true">chat_bubble</span>
        <span>Assistant</span>
    </button>

    <dialog id="tv-assistant-dialog" class="tv-assistant-dialog" aria-labelledby="tv-assistant-title">
        <div class="tv-assistant-panel">
            <header class="tv-assistant-header">
                <div>
                    <h2 id="tv-assistant-title">Assistant TV+ Afrique</h2>
                    <p>Comment puis-je vous aider ?</p>
                </div>
                <button type="button" data-assistant-close class="tv-assistant-icon" aria-label="Fermer l’assistant">
                    <span class="material-symbols-outlined" aria-hidden="true">close</span>
                </button>
            </header>

            <div class="tv-assistant-messages" data-assistant-messages role="log" aria-label="Conversation avec l’assistant" aria-live="polite" aria-relevant="additions text">
                <div class="tv-assistant-message" data-assistant-greeting>
                    <strong>Assistant</strong>
                    <p>Bonjour ! Je peux vous guider dans le site ou vous aider à préparer un texte. Posez-moi votre question.</p>
                </div>
            </div>

            <div class="tv-assistant-suggestions" data-assistant-suggestions>
                <button type="button" data-assistant-question="Comment retrouver un conducteur d’hier ?">Retrouver un conducteur</button>
                <button type="button" data-assistant-question="Comment marquer les présences à la conférence ?">Marquer les présences</button>
                <button type="button" data-assistant-question="Comment fonctionne la veille gabonaise ?">Utiliser la veille</button>
            </div>

            <footer class="tv-assistant-footer">
                <p class="tv-assistant-status" data-assistant-status role="status"></p>
                <form data-assistant-form>
                    <label class="sr-only" for="tv-assistant-question">Votre question</label>
                    <textarea id="tv-assistant-question" name="message" rows="2" maxlength="2000" required placeholder="Écrivez votre question…" aria-describedby="tv-assistant-help"></textarea>
                    <button type="submit" data-assistant-send aria-label="Envoyer la question">
                        <span class="material-symbols-outlined" aria-hidden="true">send</span>
                    </button>
                </form>
                <div class="tv-assistant-bottom">
                    <p id="tv-assistant-help">Réponses générées par IA, à vérifier.</p>
                    <button type="button" data-assistant-reset>Nouvelle discussion</button>
                </div>
            </footer>
        </div>
    </dialog>
</div>
