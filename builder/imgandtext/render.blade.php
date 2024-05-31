<section class="iwt__section">
    @if ((trim($value['align'] ?? '') != "right"))
        <div class="iwt__section-box">
            @if (trim($value['link'] ?? ''))
                <a href="{{$value['link'] ?? ''}}">
            @endif
                <img src="{{$value['src'] ?? ''}}" alt="{{$value['alt'] ?? ''}}" class="article__figure-img" loading="lazy"/>
                @if (trim($value['title'] ?? ''))
                    <span class="article__figure-text">{{$value['title']}}</span>
                @endif
            @if (trim($value['link'] ?? ''))
                </a>
            @endif
        </div>
    @endif
    <div class="iwt__section-box">{!!$value['text']!!}</div>
    @if ((trim($value['align'] ?? '') == "right"))
        <div class="iwt__section-box">
            @if (trim($value['link'] ?? ''))
                <a href="{{$value['link'] ?? ''}}">
            @endif
                <img src="{{$value['src'] ?? ''}}" alt="{{$value['alt'] ?? ''}}" class="article__figure-img" loading="lazy"/>
                @if (trim($value['title'] ?? ''))
                    <span class="article__figure-text">{{$value['title']}}</span>
                @endif
            @if (trim($value['link'] ?? ''))
                </a>
            @endif
        </div>
    @endif
</section>
