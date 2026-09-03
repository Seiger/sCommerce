// Included inside the order editor's ready callback, after product synchronization.
let repeatBusy = false;
$('#form').on('submit', async function(event) {
    event.preventDefault();
    if (repeatBusy) return;
    const form = this;
    const feedback = form.querySelector('[data-repeat-feedback]');
    const body = new FormData(form);
    const token = document.querySelector('meta[name="csrf-token"]')?.content || body.get('_token');
    body.set('_token', token || '');
    const url = new URL(form.action, window.location.href);
    url.searchParams.delete('_token');
    const fallback = @js(__('sCommerce::global.repeat_error'));
    repeatBusy = true;
    form.setAttribute('aria-busy', 'true');
    feedback.hidden = true;
    try {
        const response = await fetch(url.href, {method: 'POST', body, credentials: 'same-origin', headers: {Accept: 'application/json'}});
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || fallback);
        const destination = new URL(result.url, window.location.href);
        if (destination.origin !== window.location.origin) throw new Error(fallback);
        if (result.message) {
            const safe = document.createElement('span');
            safe.textContent = result.message;
            if (window.alertify?.warning) window.alertify.warning(safe.innerHTML);
        }
        documentDirty = false;
        window.location.assign(destination.href);
    } catch (error) {
        feedback.textContent = error.message || fallback;
        feedback.hidden = false;
        const safe = document.createElement('span');
        safe.textContent = feedback.textContent;
        if (window.alertify?.error) window.alertify.error(safe.innerHTML);
        documentDirty = true;
        repeatBusy = false;
        form.removeAttribute('aria-busy');
    }
});
