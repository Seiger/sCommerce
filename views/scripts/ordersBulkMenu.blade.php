<script>
(function () {
    const menu = document.querySelector('[data-orders-bulk-menu]');
    if (!menu) return;
    const bar = document.querySelector('[data-orders-bulk-bar]');
    const message = document.querySelector('[data-orders-bulk-message]');
    const rows = Array.from(document.querySelectorAll('[data-order-select]'));
    const locked = () => bar.getAttribute('aria-busy') === 'true';
    const summary = menu.querySelector('summary');
    summary.addEventListener('click', function (event) {
        if (locked()) { event.preventDefault(); return; }
        document.querySelectorAll('.scom-orders-filter').forEach(other => { if (other !== menu) other.open = false; });
    });
    document.addEventListener('click', event => { if (!menu.contains(event.target)) menu.open = false; });
    document.addEventListener('scommerce:order-selection-changed', () => { if (!rows.some(row => row.checked)) menu.open = false; });
    menu.addEventListener('keydown', event => { if (event.key === 'Escape') { menu.open = false; summary.focus(); } });
    menu.querySelectorAll('[data-bulk-action]').forEach(button => button.addEventListener('click', async function () {
        if (locked()) return;
        const ids = rows.filter(row => row.checked).map(row => row.value);
        if (!ids.length) return;
        const action = button.dataset.bulkAction;
        const controls = [...rows, ...bar.querySelectorAll('input, button'), document.querySelector('[data-orders-select-all]')].filter(Boolean);
        const disabled = controls.map(control => control.disabled);
        bar.setAttribute('aria-busy', 'true');
        controls.forEach(control => { control.disabled = true; });
        menu.open = false;
        const statusMenu = document.querySelector('[data-orders-bulk-status]');
        if (statusMenu) statusMenu.open = false;
        let popup = null;
        message.hidden = true;
        message.classList.remove('is-error');
        try {
            if (action === 'print') {
                // Open synchronously from the user's click, before awaiting the server.
                popup = window.open('', '_blank');
                if (!popup) throw new Error(menu.dataset.popupError);
                popup.opener = null;
                popup.document.title = button.textContent.trim();
                popup.document.body.textContent = menu.dataset.pending;
            } else {
                if (!window.alertify || typeof window.alertify.confirm !== 'function') throw new Error(menu.dataset.error);
                const content = document.createElement('div');
                content.textContent = button.dataset.confirm.replace(':count', String(ids.length));
                const confirmed = await new Promise(resolve => {
                    window.alertify.confirm(button.textContent.trim(), content, () => resolve(true), () => resolve(false)).set({
                        labels: {ok: button.textContent.trim(), cancel: menu.dataset.cancel}, transition: 'zoom',
                        movable: false, closableByDimmer: false, pinnable: false, onclose: () => resolve(false)
                    });
                });
                if (!confirmed) return;
            }
            const body = new FormData();
            body.set('action', action);
            ids.forEach(id => body.append('ids[]', id));
            body.set('_token', document.querySelector('meta[name="csrf-token"]')?.content
                || document.querySelector('[data-orders-bulk-form] [name="_token"]')?.value || '');
            const url = new URL(menu.dataset.url, window.location.href);
            url.searchParams.delete('_token');
            message.hidden = false;
            message.textContent = menu.dataset.pending;
            const response = await fetch(url.href, {method: 'POST', credentials: 'same-origin', body,
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
            let result;
            try { result = await response.json(); } catch (_) { result = null; }
            if (!response.ok || result?.success !== true) {
                throw new Error(result?.message || (response.status === 403 ? menu.dataset.sessionError : menu.dataset.error));
            }
            if (action === 'print') {
                if (!result.html || popup.closed) throw new Error(menu.dataset.error);
                // HTML comes only from the escaped order print template, never from client-supplied markup.
                popup.document.open(); popup.document.write(result.html); popup.document.close();
                popup.focus();
                message.hidden = true;
            } else {
                message.textContent = result.message;
                window.location.reload();
            }
        } catch (error) {
            if (popup && !popup.closed) popup.close();
            message.hidden = false;
            message.classList.add('is-error');
            message.textContent = error instanceof TypeError ? menu.dataset.error : error.message;
        } finally {
            controls.forEach((control, index) => { control.disabled = disabled[index]; });
            bar.setAttribute('aria-busy', 'false');
            document.dispatchEvent(new CustomEvent('scommerce:order-selection-changed'));
        }
    }));
})();
</script>
