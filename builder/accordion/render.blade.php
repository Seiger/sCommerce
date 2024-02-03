@if (is_array($value ?? []) && is_array($value['title'] ?? []) && count($value['title'] ?? []))
    <section class="accordion">
        @foreach ($value['title'] as $key => $title)
            <div class="accordion-item">
                <button id="accordion-button-{{$key}}" aria-expanded="false">
                    @if (trim($value['icon'][$key] ?? ''))
                        <span class="icon-img"><img src="{{$value['icon'][$key]}}" alt="" loading="lazy"/></span>
                    @endif
                    <span class="accordion-title">{{$title}}</span>
                    <span class="icon" aria-hidden="true"></span>
                </button>
                <div class="accordion-content">
                    {!!$value['richtext'][$key]!!}
                </div>
            </div>
        @endforeach
    </section>
@endif
