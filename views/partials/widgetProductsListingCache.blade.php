@php $task = Seiger\sCommerce\Models\sIntegrationTask::forSlug($key ?? '')->open()->orderByDesc('updated_at')->first(); @endphp
<div id="{{$key}}Progress" class="widget-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
    <span class="widget-progress__bar"></span>
    <span class="widget-progress__cap"></span>
    <span class="widget-progress__meta">
    <b class="widget-progress__pct">0%</b>
    <i class="widget-progress__eta">—</i>
  </span>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        @if($task && (int)$task->id > 0)
            widgetWatcher("", "{{route('sCommerce.integrations.progress', ['id' => $task->id])}}", '{{$key}}');
        @endif
    });
</script>
