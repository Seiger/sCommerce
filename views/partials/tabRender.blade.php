<div class="tab-page {{$tabId}}Tab" id="{{$tabId}}Tab">
    <h2 class="tab">
        <a onclick="javascript:tabSave('{!!$saveUri!!}')" href="{!!$fullUrl!!}">
            <span><i class="{{$tabIcon}}" data-tooltip="{!!$tabHelp!!}"></i> {{$tabName}}</span>
        </a>
    </h2>
    <script>tpResources.addTabPage(document.getElementById('{{$tabId}}Tab'));</script>
    @if($tabTpl && $get == $tabId)@include($tabTpl)@endif
</div>