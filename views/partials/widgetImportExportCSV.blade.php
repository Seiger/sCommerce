<div id="{{$key}}Widget" class="card-body pt-2 pb-2">
    <div class="row g-2 align-items-center">
        <div class="col-auto">
            <span id="{{$key}}Export" class="btn btn-primary">
                <i class="fa fa-file-export me-1"></i> @lang('sCommerce::global.export_products_csv')
            </span>
        </div>
        <div class="col-auto">
            <div class="btn-group">
                <span id="{{$key}}Import" class="btn btn-success">
                    <i class="fa fa-file-import me-1"></i> @lang('sCommerce::global.import_products_csv')
                </span>
                <input type="file" id="{{$key}}FileInput" accept=".csv" style="display:none;">
            </div>
        </div>
    </div>
</div>

<div id="{{$key}}Progress" class="widget-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
    <span class="widget-progress__bar"></span>
    <span class="widget-progress__cap"></span>
    <span class="widget-progress__meta">
    <b class="widget-progress__pct">0%</b>
    <i class="widget-progress__eta">—</i>
  </span>
</div>

<div id="{{$key}}Log" class="widget-log widget-drag-zone" aria-live="polite">
    <div class="line-info">@lang('sCommerce::global.click_to_export_products_to_csv')</div>
    <div class="line-info">@lang('sCommerce::global.click_to_import_products_to_csv')</div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('{{$key}}Export')?.addEventListener('click', async function() {
            let root = document.getElementById('{{$key}}Log');

            widgetClearLog(root);
            widgetLogLine(root, '**{{__('sCommerce::global.export_products_csv')}}:** _{{__('sCommerce::global.i_launch')}}…_');

            // Disable buttons immediately when starting, with this button as active
            disableButtons('{{$key}}', null, '{{$key}}Export');

            let result = await callApi("{{route('sCommerce.integrations.task.start', ['key' => $key, 'action' => 'export'])}}");

            if (result.success == true) {
                widgetLogLine(root, result?.message||'');
                widgetWatcher(root, "{{route('sCommerce.integrations.progress', ['id' => '__ID__'])}}".replace('__ID__', result?.id||0), '{{$key}}');
            } else {
                widgetLogLine(root, '**{{__('sCommerce::global.error_at_startup')}}. _' + (result?.message || '') + '_**', 'error');
                enableButtons('{{$key}}');
            }
        });

        // Import button click handler
        document.getElementById('{{$key}}Import')?.addEventListener('click', function() {
            document.getElementById('{{$key}}FileInput')?.click();
        });

        // File input change handler
        document.getElementById('{{$key}}FileInput')?.addEventListener('change', async function(event) {
            const file = event.target.files[0];
            if (!file) return;

            let root = document.getElementById('{{$key}}Log');

            widgetClearLog(root);
            widgetLogLine(root, '**{{__('sCommerce::global.import_products_csv')}}:** _{{__('sCommerce::global.i_launch')}}…_');
            widgetLogLine(root, `{{__('sCommerce::global.received_file')}} **${file.name} (${niceSize(file.size)})** _{{__('sCommerce::global.uploading')}}…_`);
            disableButtons('{{$key}}', null, '{{$key}}Import');

            // Upload file with automatic chunking and server limits validation
            try {
                const upload = await uploadFile(file, root, '{{$key}}', '{{route('sCommerce.integrations.upload', ['key' => $key])}}');

                if (upload && upload.success == true) {
                    widgetLogLine(root, upload.message);

                    let result = await callApi("{{route('sCommerce.integrations.task.start', ['key' => $key, 'action' => 'import'])}}", {filename: upload.result});

                    if (result && result.success && result.id && result.id > 0) {
                        widgetLogLine(root, result.message);
                        widgetWatcher(root, "{{route('sCommerce.integrations.progress', ['id' => '__ID__'])}}".replace('__ID__', result?.id||0), '{{$key}}');
                    } else {
                        throw new Error(result?.message || '{{__('sCommerce::global.import_failed')}}');
                    }
                }
            } catch (error) {
                widgetLogLine(root, '**{{__('sCommerce::global.upload_failed')}}:** _' + error.message + '_', 'error');
                enableButtons('{{$key}}');
                widgetProgressBar('{{$key}}', 0);
            }

            // Clear file input
            event.target.value = '';
        });

        // Drag and drop support for log area
        const logArea = document.getElementById('{{$key}}Log');
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
                    document.getElementById('{{$key}}FileInput').files = files;
                    document.getElementById('{{$key}}FileInput').dispatchEvent(new Event('change'));
                }
            });
        }
    });
</script>
