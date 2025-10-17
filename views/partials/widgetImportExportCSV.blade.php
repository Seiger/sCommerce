<style>
    .worker-widget {
        background: white;
        border: 2px solid #2563eb;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: all 0.2s ease;
        overflow: hidden;
    }
    .worker-widget:hover {
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
        transform: translateY(-1px);
    }
    .worker-widget.active {
        border-color: #2563eb;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
    }
    .worker-widget:not(.active) {
        background: #f9fafb;
        border-color: #d1d5db;
        opacity: 0.7;
    }
    .worker-header {
        padding: 0.75rem 1.25rem;
        border-bottom: 1px solid #e1e5e9;
        display: flex;
        justify-content: between;
        align-items: center;
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        color: white;
        border-radius: 8px 8px 0 0;
    }
    .worker-header.active {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        color: white;
    }
    .worker-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex: 1;
    }
    .worker-icon {
        font-size: 1.5rem;
        color: white;
        min-width: 2rem;
    }
    .worker-header.active .worker-icon {
        color: white;
    }
    .worker-title {
        font-weight: 600;
        font-size: 1.1rem;
        margin: 0;
        color: white;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .worker-header.active .worker-title {
        color: white;
    }
    .worker-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .settings-icon {
        width: 20px;
        height: 20px;
        color: rgba(255,255,255,0.8);
        cursor: pointer;
        transition: color 0.2s ease;
    }
    .settings-icon:hover {
        color: white;
    }
    .worker-header.active .settings-icon {
        color: rgba(255,255,255,0.8);
    }
    .worker-header.active .settings-icon:hover {
        color: white;
    }
    .status-dot {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .status-dot.active {
        background: #10b981;
    }
    .status-dot.inactive {
        background: #9ca3af;
    }
    .worker-body {
        padding: 1.25rem;
    }
    .btn.disabled {opacity:0.6;cursor:not-allowed;pointer-events:none;}
    .btn.disabled:hover {opacity:0.6;}
    .widget-log {
        height:150px;overflow-y:auto;background:#f1f1f1;border:1px solid #e1e1e1;border-radius:.5rem;
        margin:.1rem .9rem .6rem .9rem;padding:.6rem .9rem .6rem .9rem;white-space:normal;line-height:1.15;
        font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial;font-size:.8rem;
        transition: all 0.3s ease;cursor: pointer;
    }
    .widget-log.widget-drag-zone:hover {border-color:#198754;background-color:#f8fff8;}
    .widget-log.widget-drag-zone.drag-over {
        border-color:#198754;background-color:#e8f5e8;transform:scale(1.01);
        box-shadow:0 4px 12px rgba(25, 135, 84, 0.15);
    }
    .widget-log .line-info {color:inherit;}
    .widget-log .line-success {color:#198754;}
    .widget-log .line-error {color:#dc3545;}
    .widget-log p {margin:0;padding:0;}
    .widget-log .line-info,
    .widget-log .line-success,
    .widget-log .line-error {display:block;margin-bottom:0.25rem;}
    .widget-progress {
        position:relative;display:none;grid-template-columns:1fr auto;align-items:center;
        gap:.5rem;height:14px;margin:.1rem .9rem .1rem .9rem;color:#111827;background:#e9eef3;
        border-radius:999px;overflow:clip;
    }
    .widget-progress .widget-progress__bar {
        grid-column:1/2;height:100%;width:0%;display:block;
        background-image:
                linear-gradient(90deg, #2563eb 0%, #60a5fa 100%),
                repeating-linear-gradient(45deg, rgba(255,255,255,.12) 0 8px, rgba(255,255,255,.06) 8px 16px);
        background-size: 100% 100%, 24px 100%;
        border-radius:999px;transition:width .6s linear;will-change:width;
        animation: wgShine 1.2s linear infinite;
    }
    .widget-progress .widget-progress__cap {
        position:absolute;left:0;top:0;height:100%;width:6px;border-radius:999px;
        background:radial-gradient(120% 100% at 100% 50%, rgba(17,24,39,.05) 0 60%, transparent 70%);
        pointer-events:none;transform:translateX(0);transition:transform .16s linear;
    }
    .widget-progress .widget-progress__meta {
        grid-column:2/3;display:inline-flex;align-items:baseline;gap:.4rem;font-size:.75rem;line-height:1;
        user-select:none;margin-right:.5rem;
    }
    .widget-progress .widget-progress__pct {font-variant-numeric:tabular-nums;}
    .widget-progress .widget-progress__eta {opacity:.75;font-style:normal;}
    .widget-progress.is-indeterminate .widget-progress__bar {width:35%;animation:wgIndet 1.1s ease-in-out infinite;}
    @keyframes wgShine {to {background-position: 0 0, 24px 0;} }
    @keyframes wgIndet {0% {transform:translateX(-40%);} 50% {transform: translateX(30%);} 100% {transform: translateX(110%);} }
    @keyframes wgPulse {0%, 100%{filter: saturate(1);} 50% {filter: saturate(1.25);} }
    @media (prefers-reduced-motion: reduce){
        .widget-progress .widget-progress__bar {animation: none !important; transition: none;}
        .widget-progress.is-error .widget-progress__bar {animation: none !important;}
    }
    /* Dark mode */
    body.darkness .worker-widget {
        background: #1f2937;
        border-color: #374151;
    }
    body.darkness .worker-header {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        border-color: #4b5563;
    }
    body.darkness .worker-header.active {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    }
    .btn {
        padding: 0.5rem 1rem;
        border-radius: 6px;
        font-size: 0.875rem;
        font-weight: 500;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    .btn-primary {
        background: #3b82f6;
        color: white;
    }
    .btn-primary:hover {
        background: #2563eb;
        transform: translateY(-1px);
    }
    .btn-success {
        background: #10b981;
        color: white;
    }
    .btn-success:hover {
        background: #059669;
        transform: translateY(-1px);
    }
    .btn-danger {
        background: #ef4444;
        color: white;
    }
    .btn-danger:hover {
        background: #dc2626;
        transform: translateY(-1px);
    }
    .btn-secondary {
        background: #6b7280;
        color: white;
    }
    .btn-secondary:hover {
        background: #4b5563;
        transform: translateY(-1px);
    }
</style>

<div id="{{$identifier ?? ''}}Widget">
    <div style="padding: 1rem;">
        <div class="row g-2 align-items-center">
            <div class="col-auto">
                <span id="{{$identifier ?? ''}}Export" class="btn btn-primary">
                    <i class="fa fa-file-export me-1"></i> @lang('sCommerce::global.export_products_csv')
                </span>
                <span id="{{$identifier ?? ''}}Import" class="btn btn-success">
                    <i class="fa fa-file-import me-1"></i> @lang('sCommerce::global.import_products_csv')
                </span>
                <input type="file" id="{{$identifier ?? ''}}FileInput" accept=".csv" style="display:none;">
            </div>
        </div>
    </div>
</div>

<div id="{{$identifier ?? ''}}Progress" class="widget-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
    <span class="widget-progress__bar"></span>
    <span class="widget-progress__cap"></span>
    <span class="widget-progress__meta">
    <b class="widget-progress__pct">0%</b>
    <i class="widget-progress__eta">—</i>
  </span>
</div>

<div id="{{$identifier ?? ''}}Log" class="widget-log widget-drag-zone" aria-live="polite">
    <div class="line-info">@lang('sCommerce::global.click_to_export_products_to_csv')</div>
    <div class="line-info">@lang('sCommerce::global.click_to_import_products_to_csv')</div>
</div>

@php $task = Seiger\sTask\Models\sTaskModel::byIdentifier($identifier ?? '')->incomplete()->orderByDesc('updated_at')->first(); @endphp
<script>
    document.addEventListener('DOMContentLoaded', function() {
        @if($task && (int)$task->id > 0)
        let root = document.getElementById('{{$identifier ?? ''}}Log');
        widgetClearLog(root);
        @if($task->action == 'import')
        widgetLogLine(root, '_{{__('sCommerce::global.import_products_csv')}}..._');
        @elseif($task->action == 'export')
        widgetLogLine(root, '_{{__('sCommerce::global.export_products_csv')}}..._');
        @endif
        widgetWatcher(root, "{{route('sTask.task.progress', ['id' => $task->id])}}", '{{$identifier ?? ''}}');
        @endif
        document.getElementById('{{$identifier ?? ''}}Export')?.addEventListener('click', async function() {
            let root = document.getElementById('{{$identifier ?? ''}}Log');

            widgetClearLog(root);
            widgetLogLine(root, '**{{__('sCommerce::global.export_products_csv')}}:** _{{__('sCommerce::global.i_launch')}}…_');

            // Disable buttons immediately when starting, with this button as active
            disableButtons('{{$identifier ?? ''}}', null, '{{$identifier ?? ''}}Export');

            let result = await callApi("{{route('sTask.worker.task.run', ['identifier' => ($identifier ?? ''), 'action' => 'export'])}}");

            if (result.success == true) {
                // Показуємо прогрес-бар одразу
                widgetProgressBar('{{$identifier ?? ''}}', 0);
                // Не показуємо дублікат повідомлення - widgetWatcher сам покаже прогрес
                widgetWatcher(root, "{{route('sTask.task.progress', ['id' => '__ID__'])}}".replace('__ID__', result?.id||0), '{{$identifier ?? ''}}');
            } else {
                widgetLogLine(root, '**{{__('sCommerce::global.error_at_startup')}}. _' + (result?.message || '') + '_**', 'error');
                enableButtons('{{$identifier ?? ''}}');
            }
        });

        // Import button click handler
        document.getElementById('{{$identifier ?? ''}}Import')?.addEventListener('click', function() {
            document.getElementById('{{$identifier ?? ''}}FileInput')?.click();
        });

        // File input change handler
        document.getElementById('{{$identifier ?? ''}}FileInput')?.addEventListener('change', async function(event) {
            const file = event.target.files[0];
            if (!file) return;

            let root = document.getElementById('{{$identifier ?? ''}}Log');

            widgetClearLog(root);
            widgetLogLine(root, '**{{__('sCommerce::global.import_products_csv')}}:** _{{__('sCommerce::global.i_launch')}}…_');
            widgetLogLine(root, `{{__('sCommerce::global.received_file')}} **${file.name} (${niceSize(file.size)})** _{{__('sCommerce::global.uploading')}}…_`);
            disableButtons('{{$identifier ?? ''}}', null, '{{$identifier ?? ''}}Import');

            // Upload file with automatic chunking and server limits validation
            try {
                const upload = await uploadFile(file, root, '{{$identifier ?? ''}}', '{{route('sTask.worker.upload', ['identifier' => $identifier ?? ''])}}');

                if (upload && upload.success == true) {
                    widgetLogLine(root, upload.message);

                    let result = await callApi("{{route('sTask.worker.task.run', ['identifier' => ($identifier ?? ''), 'action' => 'import'])}}", {filename: upload.result});

                    if (result && result.success && result.id && result.id > 0) {
                        disableButtons('{{$identifier ?? ''}}', null, '{{$identifier ?? ''}}Import');
                        widgetLogLine(root, result.message);
                        widgetWatcher(root, "{{route('sTask.task.progress', ['id' => '__ID__'])}}".replace('__ID__', result?.id||0), '{{$identifier ?? ''}}');
                    } else {
                        throw new Error(result?.message || '{{__('sCommerce::global.import_failed')}}');
                    }
                }
            } catch (error) {
                widgetLogLine(root, '**{{__('sCommerce::global.import_products_csv')}}:** _' + error.message + '_', 'error');
                enableButtons('{{$identifier ?? ''}}');
                widgetProgressBar('{{$identifier ?? ''}}', 0);
            }

            // Clear file input
            event.target.value = '';
        });

        // Drag and drop support for log area
        const logArea = document.getElementById('{{$identifier ?? ''}}Log');
        if (logArea) {
            logArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                logArea.classList.add('drag-over');
            });

            logArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                logArea.classList.remove('drag-over');
            });

            logArea.addEventListener('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                logArea.classList.remove('drag-over');

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    document.getElementById('{{$identifier ?? ''}}FileInput').files = files;
                    document.getElementById('{{$identifier ?? ''}}FileInput').dispatchEvent(new Event('change'));
                }
            });
        }
    });
</script>

{{-- Include sTask scripts for progress bar functionality --}}
@include('sTask::scripts.task')
