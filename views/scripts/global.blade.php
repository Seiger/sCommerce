<script>
    async function callApi(url, form, method = 'POST', type = 'json') { // text, json, blob, formData, arrayBuffer
        try {
            const response = await fetch(url, {
                method: method,
                cache: "no-store",
                headers: {"X-Requested-With": "XMLHttpRequest"},
                body: form
            });

            if (!response.ok) {
                if (response.status === 404) throw new Error('404, Not found');
                if (response.status === 500) throw new Error('500, Internal server error');
                throw new Error(`HTTP error: ${response.status}`);
            }

            switch (type) {
                case 'text':
                    return await response.text();
                case 'json':
                    return await response.json();
                case 'blob':
                    return await response.blob();
                case 'formData':
                    return await response.formData();
                case 'arrayBuffer':
                    return await response.arrayBuffer();
                default:
                    throw new Error('Unsupported response type');
            }
        } catch (error) {
            console.error('Request failed:', error);
            return null;
        }
    }
</script>