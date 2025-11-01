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
