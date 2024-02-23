<div class="row-col col-12">
    <div class="row form-row">
        <div class="col-auto col-title">
            <label for="attributes{{$item->id}}">{{$item->pagetitle}}</label>
            @if(trim($item->helptext))<i class="fa fa-question-circle" data-tooltip="{{$item->helptext}}"></i>@endif
        </div>
        <div class="input-group col">
            <span class="input-group-text"><i class="fab fa-draft2digital"></i></span>
            <input type="number" id="attributes{{$item->id}}" name="attributes[{{$item->id}}]" class="form-control" value="" onchange="documentDirty=true;">
        </div>
    </div>
</div>