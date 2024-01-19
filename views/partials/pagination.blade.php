@if ($paginator->hasPages())
    {{-- Full link generate --}}
    @php
        $fullUrl = sCommerce::moduleUrl() . '&get='.request()->get('get');
        $fullUrl .= (request()->has('search') ? '&search=' . request()->search : '');
        $fullUrl .= (request()->has('order') ? '&order=' . request()->order : '');
        $fullUrl .= (request()->has('direc') ? '&direc=' . request()->direc : '');
        $paginator->withPath($fullUrl);
    @endphp
    <style>.dark #translatePagination a {color: #444}</style>
    <nav role="navigation" aria-label="{{__('Pagination Navigation')}}" id="translatePagination">
        <ul class="pagination justify-content-center">
            {{-- Previous Page Link --}}
            @if (!$paginator->onFirstPage())
                <li class="page-item">
                    <a class="page-link" href="{{$paginator->url(1)}}" aria-label="First">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="{{$paginator->previousPageUrl()}}" aria-label="Previous">
                        <span aria-hidden="true">&lsaquo;</span>
                    </a>
                </li>
            @endif
            {{-- Pagination Elements --}}
            @foreach ($elements as $element)
                {{-- "Three Dots" Separator --}}
                @if (is_string($element))
                    <li class="page-item"><a class="page-link">{{$element}}</a></li>
                @endif

                {{-- Array Of Links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item"><a class="page-link active dark">{{$page}}</a></li>
                        @else
                            <li class="page-item"><a class="page-link" href="{{$paginator->url($page)}}">{{$page}}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach
            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link" href="{{$paginator->nextPageUrl()}}" aria-label="Next">
                        <span aria-hidden="true">&rsaquo;</span>
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="{{$paginator->url($paginator->lastPage())}}" aria-label="Last">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            @endif
        </ul>
    </nav>
@endif
