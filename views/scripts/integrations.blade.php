<script>
    /** Clear the log container. */
    function widgetClearLog(root) {
        root.innerHTML = '';
    }

    /**
     * Safe Markdown → HTML using marked.js library (loaded in index.blade.php).
     * Supports: **bold**, _italic_, `code`, ~~strike~~, [text](url), \n → <br>.
     * @param {string} text   Markdown message
     * @param {'info'|'success'|'error'} [level='info']  Optional visual level
     */
    function widgetLogLine(root, text, level = 'info') {
        let s = String(text);

        // Use marked.js for Markdown processing if available, fallback to simple processing
        if (typeof marked !== 'undefined') {
            try {
                s = marked.parse(s);
            } catch (e) {
                console.warn('Marked.js parsing failed, using fallback:', e);
                // Fallback to simple processing
                s = s.replace(/`([^`]+)`/g,'<code>$1</code>');
                s = s.replace(/\[([^\]]+)]\((https?:\/\/[^\s)]+|\/[^\s)]+)\)/g,'<a href="$2" target="_blank" rel="noopener">$1</a>');
                s = s.replace(/\*\*([^*]+)\*\*/g,'<strong>$1</strong>').replace(/_([^_]+)_/g,'<em>$1</em>').replace(/~~([^~]+)~~/g,'<s>$1</s>');
                s = s.replace(/\n/g, '<br>');
            }
        } else {
            // Fallback if marked.js is not loaded
            s = s.replace(/`([^`]+)`/g,'<code>$1</code>');
            s = s.replace(/\[([^\]]+)]\((https?:\/\/[^\s)]+|\/[^\s)]+)\)/g,'<a href="$2" target="_blank" rel="noopener">$1</a>');
            s = s.replace(/\*\*([^*]+)\*\*/g,'<strong>$1</strong>').replace(/_([^_]+)_/g,'<em>$1</em>').replace(/~~([^~]+)~~/g,'<s>$1</s>');
            s = s.replace(/\n/g, '<br>');
        }

        root.insertAdjacentHTML('beforeend', '<div class="line-' + (level || 'info') + '">' + s + '</div>');
        root.scrollTop = root.scrollHeight;
    }

    /**
     * Get button IDs for a specific widget.
     *
     * @param {string} widgetKey - Widget key (e.g., 'simpexpcsv')
     * @returns {Array<string>} Array of button IDs for the widget, empty array if widget not found
     */
    function getWidgetButtons(widgetKey) {
        // Look for buttons within the widget container
        const widgetContainer = document.getElementById(`${widgetKey}Widget`);
        if (!widgetContainer) {
            // No fallback - if widget not found, return empty array (don't block any buttons)
            console.warn(`Widget container '${widgetKey}Widget' not found. No buttons will be blocked.`);
            return [];
        }

        // Find all buttons within the widget
        const buttons = widgetContainer.querySelectorAll('button, .btn, [role="button"]');
        return Array.from(buttons).map(button => button.id).filter(id => id);
    }

    /**
     * Disable buttons within a specific widget to prevent multiple simultaneous operations.
     *
     * @param {string} widgetKey - Widget key (e.g., 'simpexpcsv', 'mywidget')
     * @param {Array<string>} buttonIds - Array of button IDs to disable (optional, auto-detects if not provided)
     * @param {string} activeButtonId - ID of the button that triggered the action (will get spinner)
     *
     * @example
     * // Auto-detect buttons in widget
     * disableButtons('simpexpcsv');
     *
     * // Specify specific buttons with active button
     * disableButtons('mywidget', ['btnExport', 'btnImport'], 'btnExport');
     *
     * // Use with custom widget
     * disableButtons('customWidget', ['customExportBtn'], 'customExportBtn');
     */
    function disableButtons(widgetKey, buttonIds = null, activeButtonId = null) {
        const buttonsToDisable = buttonIds || getWidgetButtons(widgetKey);

        // If no buttons found, don't do anything
        if (buttonsToDisable.length === 0) {
            console.warn(`No buttons found for widget '${widgetKey}'. Nothing to disable.`);
            return;
        }

        buttonsToDisable.forEach(buttonId => {
            const button = document.getElementById(buttonId);
            if (button) {
                button.disabled = true;
                button.classList.add('disabled');

                // Store original content for restoration
                if (!button.dataset.originalContent) {
                    button.dataset.originalContent = button.innerHTML;
                }

                // Add spinner only to the active button (the one that triggered the action)
                if (activeButtonId && buttonId === activeButtonId) {
                    button.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> ' + button.textContent.trim();
                }
            }
        });
    }

    /**
     * Enable buttons within a specific widget after operation completion.
     *
     * @param {string} widgetKey - Widget key (e.g., 'simpexpcsv', 'mywidget')
     * @param {Array<string>} buttonIds - Array of button IDs to enable (optional, auto-detects if not provided)
     *
     * @example
     * // Auto-detect buttons in widget
     * enableButtons('simpexpcsv');
     *
     * // Specify specific buttons
     * enableButtons('mywidget', ['btnExport', 'btnImport', 'btnProcess']);
     *
     * // Use with custom widget
     * enableButtons('customWidget', ['customExportBtn']);
     */
    function enableButtons(widgetKey, buttonIds = null) {
        const buttonsToEnable = buttonIds || getWidgetButtons(widgetKey);

        // If no buttons found, don't do anything
        if (buttonsToEnable.length === 0) {
            console.warn(`No buttons found for widget '${widgetKey}'. Nothing to enable.`);
            return;
        }

        buttonsToEnable.forEach(buttonId => {
            const button = document.getElementById(buttonId);
            if (button) {
                button.disabled = false;
                button.classList.remove('disabled');

                // Restore original content
                if (button.dataset.originalContent) {
                    button.innerHTML = button.dataset.originalContent;
                }
            }
        });
    }

    /**
     * Update widget progress bar with given percentage and ETA.
     *
     * @param {string} key - Widget key for progress bar ID
     * @param {number} progress - Progress percentage (0-100)
     * @param {string} eta - Estimated time remaining (optional)
     */
    function widgetProgressBar(key, progress, eta = null) {
        const progressContainer = document.getElementById(`${key}Progress`);
        progressContainer.style.display = 'grid';

        if (progressContainer) {
            const progressBar = progressContainer.querySelector('.widget-progress__bar');
            const progressPct = progressContainer.querySelector('.widget-progress__pct');
            const progressEta = progressContainer.querySelector('.widget-progress__eta');
            const progressCap = progressContainer.querySelector('.widget-progress__cap');

            // Update progress bar width with smooth animation
            if (progressBar) {
                // Set transition duration based on progress change
                const currentWidth = parseFloat(progressBar.style.width) || 0;
                const change = Math.abs(progress - currentWidth);
                const duration = Math.min(Math.max(change * 8, 200), 800); // 200ms to 800ms

                progressBar.style.transition = `width ${duration}ms cubic-bezier(0.4, 0, 0.2, 1)`;
                progressBar.style.width = `${progress}%`;
            }

            // Update percentage text with smooth counting animation
            if (progressPct) {
                const currentPct = parseInt(progressPct.textContent) || 0;
                animateCounter(progressPct, currentPct, progress, 300);
            }

            // Update ETA text
            if (progressEta && eta !== null) {
                progressEta.textContent = eta;
            }

            // Update progress cap position
            if (progressCap) {
                const currentWidth = parseFloat(progressBar?.style.width) || 0;
                const duration = Math.min(Math.max(Math.abs(progress - currentWidth) * 8, 200), 800);
                progressCap.style.transition = `transform ${duration}ms cubic-bezier(0.4, 0, 0.2, 1)`;
                progressCap.style.transform = `translateX(${progress}%)`;
            }

            // Update ARIA attributes
            progressContainer.setAttribute('aria-valuenow', progress);
        }
    }

    /**
     * Animate counter from current value to target value.
     *
     * @param {HTMLElement} element - Element to update
     * @param {number} start - Starting value
     * @param {number} end - Target value
     * @param {number} duration - Animation duration in ms
     */
    function animateCounter(element, start, end, duration) {
        const startTime = performance.now();
        const difference = end - start;

        function updateCounter(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);

            // Easing function for smooth animation
            const easeOutCubic = 1 - Math.pow(1 - progress, 3);
            const current = Math.round(start + (difference * easeOutCubic));

            element.textContent = `${current}%`;

            if (progress < 1) {
                requestAnimationFrame(updateCounter);
            }
        }

        requestAnimationFrame(updateCounter);
    }

    /**
     * Adaptive short-poll watcher with dynamic intervals.
     *
     * Monitors task progress by polling an API endpoint with intelligent
     * interval adjustment based on response changes.
     *
     * Dynamic interval adjustment:
     * - If response is same as previous: increase delay by 100ms (up to 25s)
     * - If response changes in 3 consecutive requests: decrease delay by 100ms (down to 300ms)
     * - On API errors: exponential backoff (multiply by 1.5x)
     * - Range: 25ms to 25000ms (25s)
     *
     * Features:
     * - Prevents concurrent requests with inFlight flag
     * - Logs progress updates to the UI
     * - Auto-stops on task completion (finished/failed)
     * - Handles network and API errors gracefully
     *
     * @param {HTMLElement} root - Log container element for displaying progress
     * @param {string} url - API endpoint URL to poll for progress updates
     * @param {string} widgetKey - Widget key for button management (optional)
     * @returns {Function} stop() - Function to stop watching and cleanup timers
     */
    function widgetWatcher(root, url, widgetKey = null) {
        let since = 0;
        let stopped = false;
        let inFlight = false;
        let lastResponse = null;
        let lastMessage = null;
        let changeCount = 0;
        let timer = null;

        // Disable buttons when starting to watch
        // Use provided widgetKey or try to extract from URL
        const actualWidgetKey = widgetKey || (url.includes('simpexpcsv') ? 'simpexpcsv' : 'widget');
        // Note: No activeButtonId here since we don't know which button triggered the action
        disableButtons(actualWidgetKey);

        const MIN_DELAY = 25;       // 25ms minimum
        const MAX_DELAY = 25000;    // 25s maximum
        const CHANGE_THRESHOLD = 3; // 3 consecutive changes to decrease delay
        const DELTA_STEP = 100;     // 100ms step for adjustments
        let delay = MIN_DELAY;

        async function loop() {
            if (stopped) return;
            if (inFlight) {
                setTimeout(loop, Math.min(delay, 500));
                return;
            }

            inFlight = true;

            try {
                let result = await callApi(url, null, 'GET');

                if (result) {
                    // Check if response has changed
                    const currentResponse = JSON.stringify(result);
                    const hasChanged = lastResponse !== null && currentResponse !== lastResponse;

                    // Check if task is complete FIRST (regardless of success status)
                    if (result.status === 'finished' || result.status === 'failed') {
                        // Only log if message changed to avoid duplicates
                        const currentMessage = result.message || result.error || 'Unknown status';
                        if (currentMessage !== lastMessage) {
                            widgetLogLine(root, `${currentMessage}`, result.status === 'finished' ? 'success' : 'error');
                            lastMessage = currentMessage;
                        }

                        // Update progress bar if progress is available
                        if (typeof result.progress === 'number' && result.slug) {
                            widgetProgressBar(result.slug, result.progress, result.eta);
                        }

                        // Re-enable buttons after task completion
                        console.log(`[widgetWatcher] Task completed, re-enabling buttons for widget: ${actualWidgetKey}`);
                        enableButtons(actualWidgetKey);
                        stopped = true;
                        return;
                    }

                    if (hasChanged) {
                        // Response changed - increment change counter
                        changeCount++;

                        // If we've had 3 consecutive changes, decrease delay
                        if (changeCount >= CHANGE_THRESHOLD) {
                            delay = Math.max(MIN_DELAY, delay - DELTA_STEP);
                            changeCount = 0; // Reset counter
                            console.log(`[widgetWatcher] Response changing fast, decreasing delay to ${delay}ms`);
                        }

                        // Update progress bar if progress is available
                        if (typeof result.progress === 'number' && result.slug) {
                            widgetProgressBar(result.slug, result.progress, result.eta);
                        }

                        // Log the change based on success status (only if message changed)
                        const currentMessage = result.message || result.error || 'Unknown status';
                        if (currentMessage !== lastMessage) {
                            try {
                                if (result.success) {
                                    widgetLogLine(root, `${currentMessage}`, 'info');
                                } else {
                                    widgetLogLine(root, `${currentMessage}`, 'error');
                                }

                                // Verify that the message was actually added to the log
                                const logLines = root.querySelectorAll('.widget-log > div');
                                const lastLogLine = logLines[logLines.length - 1];
                                const messageDisplayed = lastLogLine && lastLogLine.textContent.includes(currentMessage);

                                if (messageDisplayed) {
                                    // Only update lastMessage if message was successfully displayed
                                    lastMessage = currentMessage;
                                    // Only update lastResponse if message was successfully logged
                                    lastResponse = currentResponse;
                                } else {
                                    console.warn(`[widgetWatcher] Message "${currentMessage}" may not have been displayed properly`);
                                }
                            } catch (e) {
                                console.error(`[widgetWatcher] Failed to log message: ${e.message}`);
                                // Don't update lastMessage or lastResponse if logging failed
                            }
                        } else {
                            // Message didn't change, but response did - still update lastResponse
                            lastResponse = currentResponse;
                        }
                    } else {
                        // Response unchanged - increase delay
                        delay = Math.min(MAX_DELAY, delay + DELTA_STEP);
                        changeCount = 0; // Reset counter since no change

                        if (delay > MIN_DELAY) {
                            console.log(`[widgetWatcher] No changes, increasing delay to ${delay}ms`);
                        }

                        // Update lastResponse even if no changes to keep tracking current state
                        lastResponse = currentResponse;
                    }

                    // Handle server errors (500) - stop polling
                    if (!result.success && result.code === 500) {
                        widgetLogLine(root, `${result.message}`, 'error');
                        stopped = true;
                        return;
                    }

                    // Handle non-success responses (but not as errors)
                    if (!result.success) {
                        // For 404 (progress file not found), keep polling but with longer intervals
                        if (result.code === 404) {
                            delay = Math.min(MAX_DELAY, delay + DELTA_STEP * 2); // Increase delay more for 404
                            console.log(`[widgetWatcher] Progress file not found, increasing delay to ${delay}ms`);
                        }
                    }
                } else {
                    // No result at all - treat as error
                    delay = Math.min(MAX_DELAY, Math.round(delay * 1.5));
                    widgetLogLine(root, `No response received`, 'error');
                }
            } catch (e) {
                console.log('[widgetWatcher] fetch/xhr error:', (e && e.message) || e);
                delay = Math.min(MAX_DELAY, Math.round(delay * 1.5));
                widgetLogLine(root, `Network error: ${e.message || 'Connection failed'}`, 'error');

                // Re-enable buttons on network error
                console.log(`[widgetWatcher] Network error, re-enabling buttons for widget: ${actualWidgetKey}`);
                enableButtons(actualWidgetKey);
                stopped = true;
            } finally {
                inFlight = false;
                if (!stopped) {
                    timer = setTimeout(loop, delay);
                }
            }
        }

        // Start the watcher
        setTimeout(loop, 0);

        // Return stop function
        return () => {
            stopped = true;
            if (timer) {
                clearTimeout(timer);
                timer = null;
            }
        };
    }

    /**
     * Upload file with automatic chunking for large files.
     *
     * This function provides a unified interface for file uploads with intelligent
     * method selection based on file size. It automatically:
     * - Fetches server limits from the API
     * - Validates file size against server constraints
     * - Chooses between direct upload (small files) or chunked upload (large files)
     * - Handles progress tracking and error management
     * - Manages button states during upload process
     *
     * @param {File} file - The file object to upload
     * @param {HTMLElement} root - Log container element for displaying progress and messages
     * @param {string} widgetKey - Widget identifier for button state management
     * @param {string} uploadUrl - API endpoint URL for file upload
     * @returns {Promise<string>} Resolves with uploaded filename when upload completes successfully
     * @throws {Error} Throws error if upload fails or file is too large
     *
     * @example
     * // Basic usage
     * await uploadFile(file, logElement, 'myWidget', '/api/upload');
     *
     * @example
     * // With custom URLs
     * await uploadFile(file, document.getElementById('log'), 'csvImport', '{{route('sCommerce.integrations.upload', ['key' => 'simpexpcsv'])}}');
     */
    async function uploadFile(file, root, widgetKey, uploadUrl) {
        try {
            // Get server limits
            const result = await callApi("{{route('sCommerce.integrations.serverLimits')}}", null, 'GET');
            const maxSize = result?.maxFileSize || 100 * 1024 * 1024;
            const chunkSize = result?.chunkSize || 1024 * 1024;
            const singleUploadLimit = result?.singleUploadLimit || 2 * 1024 * 1024;

            // Validate file size
            if (file.size > maxSize) {
                widgetLogLine(root, `**{{__('sCommerce::global.file_too_large')}}** (max: ${niceSize(maxSize)})`, 'error');
                enableButtons(widgetKey);
                widgetProgressBar(widgetKey, 0);
                throw new Error('File too large');
            }

            // Determine upload method based on file size
            if (file.size > singleUploadLimit) {
                // Chunked upload for large files
                const totalChunks = Math.ceil(file.size / chunkSize);
                const sessionId = 'upload_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

                widgetLogLine(root, `{{__('sCommerce::global.chunk_upload')}}: ${totalChunks} {{__('sCommerce::global.chunks')}}`);

                // Upload chunks
                for (let i = 0; i < totalChunks; i++) {
                    const start = i * chunkSize;
                    const end = Math.min(start + chunkSize, file.size);
                    const chunk = file.slice(start, end);

                    const formData = new FormData();
                    formData.append('file', chunk);
                    formData.append('chunk_index', i);
                    formData.append('total_chunks', totalChunks);
                    formData.append('session_id', sessionId);
                    formData.append('original_filename', file.name);

                    const response = await fetch(uploadUrl, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        }
                    });

                    const result = await response.json();

                    if (!result.success) {
                        widgetProgressBar(widgetKey, 0);
                        throw new Error(result.message || '{{__('sCommerce::global.upload_failed')}}');
                    }

                    // Update progress
                    const progress = Math.round(((i + 1) / totalChunks) * 100);
                    widgetProgressBar(widgetKey, progress, `{{__('sCommerce::global.uploading_file')}} ${progress}%`);

                    // If this is the last chunk, upload is complete
                    if (i === totalChunks - 1) {
                        widgetProgressBar(widgetKey, 100);
                        enableButtons(widgetKey);
                        return result;
                    }
                }
            } else {
                // Direct upload for smaller files
                const formData = new FormData();
                formData.append('file', file);

                const response = await fetch(uploadUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    }
                });

                const result = await response.json();

                if (result.success) {
                    widgetProgressBar(widgetKey, 100);
                    enableButtons(widgetKey);
                    return result;
                } else {
                    widgetProgressBar(widgetKey, 0);
                    throw new Error(result.message || '{{__('sCommerce::global.upload_failed')}}');
                }
            }
        } catch (error) {
            widgetLogLine(root, '**{{__('sCommerce::global.upload_failed')}}:** _' + error.message + '_', 'error');
            enableButtons(widgetKey);
            widgetProgressBar(widgetKey, 0);
        }
    }
</script>