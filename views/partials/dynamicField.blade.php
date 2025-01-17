@if(trim($field['label'] ?? ''))
    <div class="col-auto col-title">
        <label>{{$field['label'] ?? ''}}</label>
        @if(trim($field['helptext'] ?? ''))<i class="fa fa-question-circle" data-tooltip="{{$field['helptext'] ?? ''}}"></i>@endif
    </div>
@endif
<div class="col container-{{$field['key']}}">
    @foreach($field['values'] ?? [] as $value)
        <div class="row-col col-12">
            <div class="row form-row">
                @php($idx = $loop->index)
                @foreach($field['fields'] ?? [] as $kk => $ff)
                    @php($ff['key'] = $kk)
                    @php($ff['name'] = str_replace('[idx]', '[' . $idx . ']', $ff['name'] ?? ''))
                    @php($ff['value'] = $value[$kk])
                    @include('sCommerce::partials.' . ($ff['type'] ?? '') . 'Field', ['field' => $ff])
                @endforeach
                <div class="col-auto">
                    <i title="@lang('global.remove')" onclick="this.closest('.row-col').remove()" class="fa fa-trash-alt text-danger b-btn-del"></i>
                </div>
            </div>
        </div>
    @endforeach
</div>

@push('scripts.bot')
    <div class="draft-{{$field['key']}} hidden">
        <div class="row-col col-12">
            <div class="row form-row">
                @foreach($field['fields'] ?? [] as $ff)
                    @include('sCommerce::partials.' . ($ff['type'] ?? '') . 'Field', ['field' => $ff])
                @endforeach
                <div class="col-auto">
                    <i title="@lang('global.remove')" onclick="this.closest('.row-col').remove()" class="fa fa-trash-alt text-danger b-btn-del"></i>
                </div>
            </div>
        </div>
    </div>
    <script>
        function add{{$field['key']}}() {
            let idx = document.querySelector('.container-{{$field['key']}}').childNodes.length + 1;
            let elmnt = document.querySelector('.draft-{{$field['key']}}').innerHTML.replaceAll('_idx', '_' + idx).replaceAll('[idx]', '[' + idx + ']');
            document.querySelector('.container-{{$field['key']}}').insertAdjacentHTML("beforeend", elmnt)
        }
    </script>
@endpush