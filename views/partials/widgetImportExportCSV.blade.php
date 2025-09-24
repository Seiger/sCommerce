<div id="{{$key}}Widget" class="card-body pt-2 pb-2">
    <div class="row g-2 align-items-center">
        <div class="col-auto">
            <span id="pcsvExport" class="btn btn-primary">
                <i class="fa fa-file-export me-1"></i> @lang('sCommerce::global.export_products_csv')
            </span>
        </div>
        <div class="col-auto">
            <span id="pcsvImport" class="btn btn-success">
                <i class="fa fa-file-import"></i> @lang('sCommerce::global.products') @lang('sCommerce::global.import') CSV
            </span>
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

<div id="{{$key}}Log" class="widget-log" aria-live="polite">
    <span class="line-info">@lang('sCommerce::global.click_to_export_products_to_csv')</span><br>
    Натисніть <strong>Імпорт CSV</strong> — щоб імпортувати з CSV, або перетягніть файл CSV у це поле.
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('pcsvExport')?.addEventListener('click', async function() {
            let root = document.getElementById('{{$key}}Log');

            widgetClearLog(root);
            widgetLogLine(root, '**{{__('sCommerce::global.export_products_csv')}}:** _{{__('sCommerce::global.i_launch')}}…_');

            // Disable buttons immediately when starting, with this button as active
            disableButtons('{{$key}}', null, 'pcsvExport');

            let result = await callApi("{{$exportUrl}}");

            if (result.success == true) {
                widgetLogLine(root, result?.message||'');
                widgetWatcher(root, "{{route('sCommerce.integrations.progress', ['id' => '__ID__'])}}".replace('__ID__', result?.task||0), '{{$key}}');
            } else {
                widgetLogLine(root, '**{{__('sCommerce::global.error_at_startup')}}. _' + (result?.message || '') + '_**', 'error');
                // Re-enable buttons on startup error
                enableButtons('{{$key}}');
            }
        });

        document.getElementById('pcsvImport')?.addEventListener('click', async function() {
            let root = document.getElementById('{{$key}}Log');

            widgetClearLog(root);
            widgetLogLine(root, '**{{__('sCommerce::global.import')}} CSV:** _{{__('sCommerce::global.i_launch')}}…_');

            // Disable buttons immediately when starting, with this button as active
            disableButtons('{{$key}}', null, 'pcsvImport');

            // TODO: Implement import functionality
            widgetLogLine(root, '**{{__('sCommerce::global.import')}} функціональність в розробці...**', 'info');
            
            // Re-enable buttons after showing message
            setTimeout(() => {
                enableButtons('{{$key}}');
            }, 5000);
        });
    });
</script>
