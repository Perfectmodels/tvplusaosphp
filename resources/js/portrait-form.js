function initPortraits() {
    document.querySelectorAll('[data-portrait]').forEach(root => {
        const file = root.querySelector('[data-portrait-file]');
        const url = root.querySelector('[data-portrait-url]');
        const preview = root.querySelector('[data-portrait-preview]');
        const remove = root.querySelector('[data-portrait-remove]');
        const status = root.querySelector('[data-portrait-status]');
        const initialPreview = preview.src;
        let objectUrl;

        function show(src) {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = null;
            preview.src = src || root.dataset.portraitFallback;
            preview.classList.remove('hidden');
            root.querySelector('[data-portrait-empty]')?.classList.add('hidden');
        }
        file.addEventListener('change', () => {
            const image = file.files[0];
            file.setCustomValidity('');
            status.textContent = '';
            if (!image) return;
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(image.type) || image.size > 2 * 1024 * 1024) {
                const message = 'Choisissez une image JPG, PNG ou WebP de 2 Mo maximum.';
                file.setCustomValidity(message);
                status.textContent = message;
                return;
            }
            show('');
            objectUrl = URL.createObjectURL(image);
            preview.src = objectUrl;
            url.value = '';
            remove.checked = false;
        });
        url.addEventListener('input', () => {
            file.value = '';
            file.setCustomValidity('');
            remove.checked = false;
            status.textContent = '';
            show(url.value.trim());
        });
        remove.addEventListener('change', () => {
            file.value = '';
            file.setCustomValidity('');
            status.textContent = '';
            if (remove.checked) { url.value = ''; show(''); }
        });
        file.form?.addEventListener('reset', () => setTimeout(() => {
            file.setCustomValidity('');
            status.textContent = '';
            show(initialPreview);
        }, 0));
    });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initPortraits, { once: true });
else initPortraits();
