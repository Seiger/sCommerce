<h3>@lang('sCommerce::global.notifications_email') <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.notifications_email_layout', ['file' => '/views/notifications/email/layout.blade.php', 'directory' => '/views/notifications/email/'])"></i></h3>
<div class="row form-row">
    <div class="col-auto">
        <label for="notifications__email_addresses">@lang('sCommerce::global.notifications_email_addresses')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.notifications_email_addresses_help')"></i>
    </div>
    <div class="col">
        <input type="text" class="form-control" id="notifications__email_addresses" name="notifications__email_addresses" value="{{sCommerce::config('notifications.email_addresses', '')}}" onchange="documentDirty=true;">
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="notifications__email_template_admin_order">@lang('sCommerce::global.notifications_email_template_admin_order')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.notifications_email_template_on_help')"></i>
    </div>
    <div class="input-group col">
        <div class="input-group-prepend">
            <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.notifications__email_template_admin_order_on);" @if(sCommerce::config('notifications.email_template_admin_order_on', 1) == 1) checked @endif>
            <input type="hidden" id="notifications__email_template_admin_order_on" name="notifications__email_template_admin_order_on" value="{{sCommerce::config('notifications.email_template_admin_order_on', 1)}}" onchange="documentDirty=true;">
        </div>
        <select id="notifications__email_template_admin_order" class="form-control select2" name="notifications__email_template_admin_order" onchange="documentDirty=true;">
            <option value="" @if(sCommerce::config('notifications.email_template_admin_order', '') == '') selected @endif></option>
            @foreach($emailNotifications as $view)
                <option value="{{$view}}" @if(sCommerce::config('notifications.email_template_admin_order', '') == $view) selected @endif>/view/{{$view}}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="notifications__email_template_admin_fast_order">@lang('sCommerce::global.notifications_email_template_admin_fast_order')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.notifications_email_template_on_help')"></i>
    </div>
    <div class="input-group col">
        <div class="input-group-prepend">
            <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.notifications__email_template_admin_fast_order_on);" @if(sCommerce::config('notifications.email_template_admin_fast_order_on', 1) == 1) checked @endif>
            <input type="hidden" id="notifications__email_template_admin_fast_order_on" name="notifications__email_template_admin_fast_order_on" value="{{sCommerce::config('notifications.email_template_admin_fast_order_on', 1)}}" onchange="documentDirty=true;">
        </div>
        <select id="notifications__email_template_admin_fast_order" class="form-control select2" name="notifications__email_template_admin_fast_order" onchange="documentDirty=true;">
            <option value="" @if(sCommerce::config('notifications.email_template_admin_fast_order', '') == '') selected @endif></option>
            @foreach($emailNotifications as $view)
                <option value="{{$view}}" @if(sCommerce::config('notifications.email_template_admin_fast_order', '') == $view) selected @endif>/view/{{$view}}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="notifications__email_template_customer_order">@lang('sCommerce::global.notifications_email_template_customer_order')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.notifications_email_template_on_help')"></i>
    </div>
    <div class="input-group col">
        <div class="input-group-prepend">
            <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.notifications__email_template_customer_order_on);" @if(sCommerce::config('notifications.email_template_customer_order_on', 1) == 1) checked @endif>
            <input type="hidden" id="notifications__email_template_customer_order_on" name="notifications__email_template_customer_order_on" value="{{sCommerce::config('notifications.email_template_customer_order_on', 1)}}" onchange="documentDirty=true;">
        </div>
        <select id="notifications__email_template_customer_order" class="form-control select2" name="notifications__email_template_customer_order" onchange="documentDirty=true;">
            <option value="" @if(sCommerce::config('notifications.email_template_customer_order', '') == '') selected @endif></option>
            @foreach($emailNotifications as $view)
                <option value="{{$view}}" @if(sCommerce::config('notifications.email_template_customer_order', '') == $view) selected @endif>/view/{{$view}}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="row form-row">
    <div class="col-auto">
        <label for="notifications__email_template_customer_fast_order">@lang('sCommerce::global.notifications_email_template_customer_fast_order')</label>
        <i class="fa fa-question-circle" data-tooltip="@lang('sCommerce::global.notifications_email_template_on_help')"></i>
    </div>
    <div class="input-group col">
        <div class="input-group-prepend">
            <input type="checkbox" class="form-checkbox form-control" onchange="documentDirty=true;" onclick="changestate(document.form.notifications__email_template_customer_fast_order_on);" @if(sCommerce::config('notifications.email_template_customer_fast_order_on', 1) == 1) checked @endif>
            <input type="hidden" id="notifications__email_template_customer_fast_order_on" name="notifications__email_template_customer_fast_order_on" value="{{sCommerce::config('notifications.email_template_customer_fast_order_on', 1)}}" onchange="documentDirty=true;">
        </div>
        <select id="notifications__email_template_customer_fast_order" class="form-control select2" name="notifications__email_template_customer_fast_order" onchange="documentDirty=true;">
            <option value="" @if(sCommerce::config('notifications.email_template_customer_fast_order', '') == '') selected @endif></option>
            @foreach($emailNotifications as $view)
                <option value="{{$view}}" @if(sCommerce::config('notifications.email_template_customer_fast_order', '') == $view) selected @endif>/view/{{$view}}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="split my-3"></div>