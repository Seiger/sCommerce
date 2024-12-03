<div class="col-sm-12">
    <div class="card">
        <input type="hidden" name="basic__available_currencies[]" value="{{$alpha ?? ''}}">
        <div class="card-header" style="background: #CECECF;">
            <i style="cursor:pointer;" class="fas fa-sort"></i>&emsp; {{$alpha ?? ''}} ({!!$symbol ?? ''!!}) - {{$name ?? ''}}
            <span class="close-icon" onclick="deleteItem(this.closest('.card'))"><i class="fa fa-times"></i></span>
        </div>
        <div class="card-block">
            <div class="userstable">
                <div class="card-body">
                    <div class="row form-row">
                        <div class="col-auto col-title-6">
                            <label class="warning">@lang('sCommerce::global.currency_name')</label>
                            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.currency_name_help')"></i>
                        </div>
                        <div class="col">
                            <input type="text" class="form-control" name="currencies__{{$alpha ?? ''}}[name]" value="{{$name ?? ''}}" onchange="documentDirty=true;">
                        </div>
                    </div>
                    <div class="row form-row">
                        <div class="col-auto col-title-6">
                            <label>@lang('sCommerce::global.symbol')</label>
                            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.currency_symbol_help')"></i>
                        </div>
                        <div class="col">
                            <input type="text" class="form-control" name="currencies__{{$alpha ?? ''}}[symbol]" value="{{$symbol ?? ''}}" onchange="documentDirty=true;">
                        </div>
                    </div>
                    <div class="row form-row">
                        <div class="col-auto">
                            <label>@lang('sCommerce::global.price_symbol')</label>
                            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.price_symbol_help')"></i>
                        </div>
                        <div class="col">
                            <select class="form-control" name="currencies__{{$alpha ?? ''}}[show]" onchange="documentDirty=true;">
                                <option value="0" @if(($show ?? 1) == 0) selected @endif>@lang('global.no')</option>
                                <option value="1" @if(($show ?? 1) == 1) selected @endif>@lang('global.yes')</option>
                            </select>
                        </div>
                    </div>
                    <div class="row form-row">
                        <div class="col-auto">
                            <label>@lang('sCommerce::global.position')</label>
                            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.price_position_help')"></i>
                        </div>
                        <div class="col">
                            <select class="form-control" name="currencies__{{$alpha ?? ''}}[position]" onchange="documentDirty=true;">
                                <option value="before" @if(($position ?? 'before') == 'before') selected @endif>@lang('sCommerce::global.before_sum')</option>
                                <option value="after" @if(($position ?? 'before') == 'after') selected @endif>@lang('sCommerce::global.after_sum')</option>
                            </select>
                        </div>
                    </div>
                    <div class="row form-row">
                        <div class="col-auto">
                            <label>@lang('sCommerce::global.price_thousands_separator')</label>
                            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.price_thousands_separator_help')"></i>
                        </div>
                        <div class="col">
                            <input type="text" name="currencies__{{$alpha ?? ''}}[thousands]" class="form-control" value="{{$thousands ?? "&nbsp;"}}" onchange="documentDirty=true;">
                        </div>
                    </div>
                    <div class="row form-row">
                        <div class="col-auto">
                            <label>@lang('sCommerce::global.price_decimal_separator')</label>
                            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.price_decimal_separator_help')"></i>
                        </div>
                        <div class="col">
                            <select class="form-control" name="currencies__{{$alpha ?? ''}}[decimals]" onchange="documentDirty=true;">
                                <option value="." @if(($decimals ?? '.') == '.') selected @endif>.</option>
                                <option value="," @if(($decimals ?? '.') == ',') selected @endif>,</option>
                            </select>
                        </div>
                    </div>
                    <div class="row form-row">
                        <div class="col-auto">
                            <label>@lang('sCommerce::global.price_decimals')</label>
                            <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.price_decimals_help')"></i>
                        </div>
                        <div class="col">
                            <select class="form-control" name="currencies__{{$alpha ?? ''}}[exp]" onchange="documentDirty=true;">
                                <option value="0" @if(($exp ?? 2) == 0) selected @endif>0</option>
                                <option value="1" @if(($exp ?? 2) == 1) selected @endif>1</option>
                                <option value="2" @if(($exp ?? 2) == 2) selected @endif>2</option>
                                <option value="3" @if(($exp ?? 2) == 3) selected @endif>3</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>