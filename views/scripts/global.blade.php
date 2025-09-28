<script>
    async function callApi(url, data, method = 'POST', type = 'json') { // text, json, blob, formData, arrayBuffer
        try {
            // Prepare headers and body based on data type
            let headers = {
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            };

            let body;
            if (data instanceof FormData) {
                // FormData - no additional headers needed
                body = data;
            } else if (data && typeof data === 'object') {
                // JSON object - set content-type and stringify
                headers['Content-Type'] = 'application/json';
                body = JSON.stringify(data);
            } else if (typeof data === 'string') {
                // String data
                body = data;
            } else {
                // No data
                body = null;
            }

            const response = await fetch(url, {
                method: method,
                cache: "no-store",
                headers: headers,
                body: body
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

    /**
     * Format file size in human readable format.
     *
     * Converts bytes to appropriate unit (B, KB, MB, GB, TB) with proper rounding.
     *
     * @param {number} bytes - File size in bytes
     * @returns {string} Formatted file size with unit
     *
     * @example
     * niceSize(1024); // "1 KB"
     * niceSize(1048576); // "1 MB"
     * niceSize(1536); // "1.5 KB"
     */
    function niceSize(bytes) {
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        let unitIndex = 0;

        while (bytes >= 1024 && unitIndex < units.length - 1) {
            bytes /= 1024;
            unitIndex++;
        }

        return Math.round(bytes * 100) / 100 + ' ' + units[unitIndex];
    }
</script>