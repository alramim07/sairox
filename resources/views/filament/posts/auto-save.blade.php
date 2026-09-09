<div id="auto-save-indicator" class="mt-2 text-xs text-gray-500">
    Changes auto-saved 5 seconds after you stop typing
</div>

<script>
    const initSairoxAutoSave = () => {
        if (window.__sairoxAutoSaveInit) {
            return;
        }

        window.__sairoxAutoSaveInit = true;

        let autoSaveTimeout;

        document.addEventListener('input', (event) => {
            if (!event.target.closest('[wire\\:submit]')) {
                return;
            }

            clearTimeout(autoSaveTimeout);

            autoSaveTimeout = setTimeout(() => {
                Livewire.dispatch('auto-save');
            }, 5000);
        }, true);

        Livewire.on('auto-saved', ({ time }) => {
            const indicator = document.getElementById('auto-save-indicator');

            if (indicator) {
                indicator.textContent = 'Auto-saved at ' + (time || '');
            }
        });
    };

    if (window.Livewire) {
        initSairoxAutoSave();
    } else {
        document.addEventListener('livewire:init', initSairoxAutoSave, { once: true });
    }
</script>
