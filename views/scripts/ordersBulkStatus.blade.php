<script>
(function () {
    const form = document.querySelector('[data-orders-bulk-form]');
    if (!form) return;
    const menu = document.querySelector('[data-orders-bulk-status]');
    const bar = document.querySelector('[data-orders-bulk-bar]');
    const message = document.querySelector('[data-orders-bulk-message]');
    const apply = form.querySelector('[type="submit"]');
    const selections = Array.from(document.querySelectorAll('[data-order-select]'));
    const radios = Array.from(form.querySelectorAll('[name="status"]'));
    let busy = false;
    const sync = function () {
        const hasSelection = selections.some(input => input.checked);
        apply.disabled = busy || bar.getAttribute('aria-busy') === 'true' || !hasSelection || !radios.some(input => input.checked);
        if (!hasSelection && !busy) {
            menu.open = false;
            form.reset();
            message.hidden = true;
        }
    };
    document.addEventListener('scommerce:order-selection-changed', sync);
    form.addEventListener('change', sync);
    form.querySelector('[data-bulk-status-cancel]').addEventListener('click', function () {
        if (busy) return;
        form.reset();
        menu.open = false;
        sync();
    });
    document.addEventListener('click', function (event) {
        if (!menu.contains(event.target) && !busy) menu.open = false;
    });
    menu.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !busy) {
            menu.open = false;
            menu.querySelector('summary').focus();
        }
    });
    const setBusy = function (value) {
        busy = value;
        bar.setAttribute('aria-busy', String(value));
        [...selections, ...radios, ...form.querySelectorAll('button'),
            document.querySelector('[data-orders-select-all]'),
            document.querySelector('[data-orders-selection-clear]'),
            document.querySelector('[data-orders-bulk-export]')].filter(Boolean)
            .forEach(input => { input.disabled = value; });
        sync();
    };
    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (busy || bar.getAttribute('aria-busy') === 'true') return;
        const ids = selections.filter(input => input.checked).map(input => input.value);
        const status = radios.find(input => input.checked);
        if (!ids.length || !status) return;
        const prompt = form.dataset.confirm.replace(':count', String(ids.length)).replace(':status', status.dataset.statusLabel);

        const body = new FormData(form);
        ids.forEach(id => body.append('ids[]', id));
        const token = document.querySelector('meta[name="csrf-token"]')?.content || body.get('_token');
        body.set('_token', token || '');
        const url = new URL(form.action, window.location.href);
        // Send only the current body token, never the token captured in an old URL.
        url.searchParams.delete('_token');
        setBusy(true);
        message.hidden = true;
        message.classList.remove('is-error');
        try {
            if (!window.alertify || typeof window.alertify.confirm !== 'function') {
                throw new Error(form.dataset.error);
            }
            const content = document.createElement('div');
            content.textContent = prompt;
            const confirmed = await new Promise(resolve => {
                window.alertify.confirm(form.dataset.confirmTitle, content,
                    function () { resolve(true); },
                    function () { resolve(false); }
                ).set({
                    labels: {ok: form.dataset.confirmOk, cancel: form.dataset.confirmCancel},
                    transition: 'zoom', movable: false, closableByDimmer: false, pinnable: false,
                    onclose: function () { resolve(false); }
                });
            });
            if (!confirmed) {
                setBusy(false);
                return;
            }
            message.hidden = false;
            message.textContent = form.dataset.pending;
            const response = await fetch(url.href, {
                method: 'POST', credentials: 'same-origin', body,
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            });
            let result;
            try { result = await response.json(); } catch (_) { result = null; }
            if (!response.ok || !result || result.success !== true) {
                throw new Error((result && result.message) || (response.status === 403 ? form.dataset.sessionError : form.dataset.error));
            }
            message.textContent = result.message;
            // The existing query retains filters, sort direction and page; the server recomputes rows and counters.
            window.location.reload();
        } catch (error) {
            message.hidden = false;
            message.classList.add('is-error');
            message.textContent = error instanceof TypeError ? form.dataset.error : error.message;
            setBusy(false);
        }
    });
    sync();
})();
</script>
