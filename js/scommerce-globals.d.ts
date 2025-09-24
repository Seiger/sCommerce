/**
 * Global declarations for sCommerce JS utilities.
 * This file provides IDE typings for functions defined inside Blade/vendor views,
 * so PhpStorm can resolve and typecheck calls across Blade includes.
 */

/*
|--------------------------------------------------------------------------
| views/scripts/integrations.blade.php
|--------------------------------------------------------------------------
*/

/**
 * Clear the log container.
 * @param root The element that contains the log output.
 */
declare function widgetClearLog(root: HTMLElement): void;

/** Visual level for widget log line. */
type WidgetLogLevel = 'info' | 'success' | 'error';

/**
 * Append one Markdown-like line into the log container.
 * Escapes HTML, supports `code`, **bold**, _italic_, ~~strike~~, [link](url).
 */
declare function widgetLogLine(root: HTMLElement, text: string, level?: WidgetLogLevel): void;

/**
 * Adaptive short-poll watcher with dynamic intervals.
 *
 * Polls a progress endpoint and updates a log UI, adjusting the interval based on changes:
 * - If response is unchanged → delay += 100ms (up to 25_000ms)
 * - If response changes 3 times consecutively → delay -= 100ms (down to 300ms)
 * - On API/network errors → exponential backoff (delay *= 1.5, capped at 25_000ms)
 *
 * Features:
 * - Prevents concurrent requests via in-flight guard
 * - Logs progress updates (delegates to `widgetLogLine`)
 * - Auto-stops on completion (status: finished|failed)
 * - Gracefully handles HTTP/network failures
 *
 * Accessibility:
 * - Intended to be used with a progressbar UI placed above the log container
 * - Works well with “no-store” cache policy on the endpoint
 *
 * @param root  Log container element where updates are appended.
 * @param url   Progress endpoint URL (expected to return JSON with status/message/progress).
 * @returns     `stop()` function to terminate polling and clear timers.
 *
 * @example
 * const stop = widgetWatcher(document.getElementById('pcsvLog')!, `/scommerce/integrations/tasks/${id}/changes?since=0`);
 * // later on completion/cancel:
 * stop();
 */
declare function widgetWatcher(root: HTMLElement, url: string): () => void;

/*
|--------------------------------------------------------------------------
| views/scripts/global.blade.php
|--------------------------------------------------------------------------
*/

/** Allowed HTTP methods (uppercase). */
type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE' | 'HEAD' | 'OPTIONS';

/**
 * Lightweight fetch wrapper with typed response by `type`.
 * - Default behavior in your implementation maps to 'json'.
 * - Returns `null` when request fails (caught error or non-OK mapped error).
 *
 * @param url    Request URL (string | URL | Request)
 * @param form   Request body (FormData / Blob / string / URLSearchParams / etc.)
 * @param method HTTP method (default 'POST')
 * @param type   Desired response type (default 'json')
 */
declare function callApi(url: string | URL | Request, form?: BodyInit | null, method?: HttpMethod, type: 'text'): Promise<string | null>;
declare function callApi<T = any>(url: string | URL | Request, form?: BodyInit | null, method?: HttpMethod, type: 'json'): Promise<T | null>;
declare function callApi(url: string | URL | Request, form?: BodyInit | null, method?: HttpMethod, type: 'blob'): Promise<Blob | null>;
declare function callApi(url: string | URL | Request, form?: BodyInit | null, method?: HttpMethod, type: 'formData'): Promise<FormData | null>;
declare function callApi(url: string | URL | Request, form?: BodyInit | null, method?: HttpMethod, type: 'arrayBuffer'): Promise<ArrayBuffer | null>;
declare function callApi<T = any>(url: string | URL | Request, form?: BodyInit | null, method?: HttpMethod, type?: undefined): Promise<T | null>;
