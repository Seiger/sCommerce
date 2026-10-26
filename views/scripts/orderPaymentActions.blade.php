@push('scripts.bot')
<script>
(function () {
    const panel = document.querySelector('[data-order-payments]');
    if (!panel) return;
    const message = panel.querySelector('[data-order-payments-message]');
    let busy = false;
    const show = function (text, isError) {
        message.hidden = false;
        message.classList.toggle('is-error', Boolean(isError));
        message.textContent = text;
    };
    const confirmAction = function (title) {
        if (!window.alertify || typeof window.alertify.confirm !== 'function') {
            return Promise.resolve(window.confirm(title));
        }
        return new Promise(resolve => {
            const content = document.createElement('div');
            content.textContent = title;
            window.alertify.confirm(title, content, function () { resolve(true); }, function () { resolve(false); }).set({
                labels: {ok: panel.dataset.confirmOk, cancel: panel.dataset.confirmCancel},
                transition: 'zoom', movable: false, closableByDimmer: false, pinnable: false,
                onclose: function () { resolve(false); }
            });
        });
    };
    panel.addEventListener('click', async function (event) {
        const button = event.target.closest('[data-payment-action]');
        if (!button || busy) return;
        event.preventDefault();
        const row = button.closest('[data-payment]');
        // Amount and reason inputs belong to the action's own form; payment 0 is an order-level record.
        const fields = button.closest('[data-payment-form]');
        const amount = fields && fields.querySelector('[data-payment-amount]');
        const reason = fields && fields.querySelector('[data-payment-reason]');
        const body = new FormData();
        const form = document.getElementById('form');
        const token = document.querySelector('meta[name="csrf-token"]')?.content || (form && form.querySelector('[name="_token"]')?.value);
        body.set('_token', token || '');
        body.set('action', button.dataset.paymentAction);
        body.set('order', panel.dataset.order);
        body.set('payment', row.dataset.payment);
        body.set('nonce', button.dataset.nonce);
        if (amount) body.set('amount', amount.value.trim());
        if (reason) body.set('reason', reason.value.trim());
        const label = row.dataset.label ? ' ' + row.dataset.label : '';
        if (!await confirmAction(button.textContent.trim() + label + (body.get('amount') ? ': ' + body.get('amount') : '') + '?')) {
            return;
        }
        busy = true;
        panel.querySelectorAll('button, input').forEach(input => { input.disabled = true; });
        show(panel.dataset.pending, false);
        try {
            const response = await fetch(panel.dataset.url, {
                method: 'POST', credentials: 'same-origin', body,
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            });
            let result;
            try { result = await response.json(); } catch (_) { result = null; }
            if (!response.ok || !result || result.success !== true) {
                throw new Error((result && result.message) || (response.status === 403 ? panel.dataset.sessionError : panel.dataset.error));
            }
            show(result.message, false);
            window.setTimeout(function () { window.location.reload(); }, 1200);
        } catch (error) {
            show(error instanceof TypeError ? panel.dataset.error : error.message, true);
            busy = false;
            panel.querySelectorAll('button, input').forEach(input => { input.disabled = false; });
        }
    });
})();
</script>
@endpush
