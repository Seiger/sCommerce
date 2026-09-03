<script>
(function () {
    const button = document.querySelector('[data-orders-bulk-export]');
    if (!button) return;
    const bar = document.querySelector('[data-orders-bulk-bar]');
    const message = document.querySelector('[data-orders-bulk-message]');
    const selections = Array.from(document.querySelectorAll('[data-order-select]'));
    let busy = false;
    button.addEventListener('click', async function () {
        if (busy || bar.getAttribute('aria-busy') === 'true') return;
        const ids = selections.filter(input => input.checked).map(input => input.value);
        if (!ids.length) return;
        const body = new FormData();
        ids.forEach(id => body.append('ids[]', id));
        body.set('_token', document.querySelector('meta[name="csrf-token"]')?.content
            || document.querySelector('[data-orders-bulk-form] [name="_token"]')?.value || '');
        const url = new URL(button.dataset.url, window.location.href);
        url.searchParams.delete('_token');
        busy = true;
        bar.setAttribute('aria-busy', 'true');
        const controls = [...selections, ...bar.querySelectorAll('input, button'), document.querySelector('[data-orders-select-all]')].filter(Boolean);
        const disabled = controls.map(control => control.disabled);
        controls.forEach(control => { control.disabled = true; });
        const menu = document.querySelector('[data-orders-bulk-status]');
        if (menu) menu.open = false;
        message.hidden = false;
        message.classList.remove('is-error');
        message.textContent = button.dataset.pending;
        try {
            const response = await fetch(url.href, {
                method: 'POST', credentials: 'same-origin', body,
                headers: {'Accept': 'text/csv, application/json', 'X-Requested-With': 'XMLHttpRequest'}
            });
            if (!response.ok || response.headers.get('Content-Type')?.split(';')[0].trim().toLowerCase() !== 'text/csv') {
                let result;
                try { result = await response.json(); } catch (_) { result = null; }
                throw new Error(result?.message || (response.status === 403 ? button.dataset.sessionError : button.dataset.error));
            }
            const blob = await response.blob();
            if (!blob.size) throw new Error(button.dataset.error);
            const objectUrl = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = objectUrl;
            const filename = /filename="(sCommerce_orders_[0-9_-]+\.csv)"/.exec(response.headers.get('Content-Disposition') || '');
            link.download = filename ? filename[1] : 'sCommerce_orders.csv';
            try {
                document.body.appendChild(link);
                link.click();
            } finally {
                link.remove();
                // Give the browser time to consume the download before releasing the private blob.
                window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
            }
            message.textContent = button.dataset.success;
        } catch (error) {
            message.classList.add('is-error');
            message.textContent = error instanceof TypeError ? button.dataset.error : error.message;
        } finally {
            controls.forEach((control, index) => { control.disabled = disabled[index]; });
            busy = false;
            bar.setAttribute('aria-busy', 'false');
            // Recalculate status availability without resetting selection or filters.
            document.dispatchEvent(new CustomEvent('scommerce:order-selection-changed'));
        }
    });
})();
</script>
