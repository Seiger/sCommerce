@extends('manager::template.page')
@section('content')
    <h1><i class="@lang('sCommerce::global.icon')" data-tooltip="@lang('sCommerce::global.description')"></i> @lang('sCommerce::global.title')</h1>

    <div class="sectionBody">
        <div class="tab-pane" id="resourcesPane">
            <script>tpResources = new WebFXTabPane(document.getElementById('resourcesPane'), false);</script>
            @foreach($tabs as $tab)
                @if($tab == 'content')
                    @foreach($sArticlesController->langList() as $idx => $lang)
                        <div class="tab-page content{{$lang}}Tab" id="content{{$lang}}Tab">
                            <h2 class="tab">
                                <a onclick="javascript:tabSave('&get={{$tab}}&lang={{$lang}}{{${$tab.'_url'} ?? ''}}');" href="{!!$url!!}&get={{$tab}}&lang={{$lang}}{{${$tab.'_url'} ?? ''}}">
                                    <i class="fa fa-flag"></i>
                                    @lang('sCommerce::global.content')
                                    @if($lang != 'base')
                                        <span class="badge bg-seigerit">{{$lang}}</span>
                                    @endif
                                </a>
                            </h2>
                            <script>tpResources.addTabPage(document.getElementById('content{{$lang}}Tab'));</script>
                            @if($get == $tab && $lang == request()->lang)
                                @include('sCommerce::'.$tab.'Tab')
                                @php($get = 'content' . $lang)
                            @endif
                        </div>
                    @endforeach
                @else
                    <div class="tab-page {{$tab}}Tab" id="{{$tab}}Tab">
                        <h2 class="tab">
                            <a onclick="javascript:tabSave('&get={{$tab}}{{${$tab.'_url'} ?? ''}}');" href="{!!$moduleUrl!!}&get={{$tab}}{{${$tab.'_url'} ?? ''}}">
                                <span><i class="@lang('sCommerce::global.'.$tab.'_icon')" data-tooltip="@lang('sCommerce::global.'.$tab.'_help')"></i> @lang('sCommerce::global.'.$tab)</span>
                            </a>
                        </h2>
                        <script>tpResources.addTabPage(document.getElementById('{{$tab}}Tab'));</script>
                        @if($get == $tab)
                            @include('sCommerce::'.$tab.'Tab')
                        @endif
                    </div>
                @endif
            @endforeach
            <script>tpResources.setSelectedTab('{{$get}}Tab');</script>
        </div>
    </div>
@endsection

@push('scripts.top')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
    @include('sCommerce::partials.style')
    <script>
        function evoRenderImageCheck(a) {
            var b = document.getElementById('image_for_' + a.target.id),
                c = new Image;
            a.target.value ? (c.src = "<?php echo evo()->getConfig('site_url')?>" + a.target.value, c.onerror = function () {
                b.style.backgroundImage = '', b.setAttribute('data-image', '');
            }, c.onload = function () {
                b.style.backgroundImage = 'url(\'' + this.src + '\')', b.setAttribute('data-image', this.src);
            }) : (b.style.backgroundImage = '', b.setAttribute('data-image', ''));
        }
    </script>
@endpush

@push('scripts.bot')
    {!!$editor!!}
    <link rel="stylesheet" href="//cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>
    <script src="media/script/jquery.quicksearch.js"></script>
    <script src="media/script/jquery.nucontextmenu.js"></script>
    <script src="media/script/bootstrap/js/bootstrap.min.js"></script>
    <script src="actions/resources/functions.js"></script>
    <script src="media/calendar/datepicker.js"></script>
    <script>
        $(document).ready(function () {
            $('.select2').select2();
            $('.sortable').sortable();

            $("table img").on("mouseenter", function () {
                var alt = $(this).attr("alt");
                if (alt.length > 0) {
                    $("#img-preview").attr("src", alt).show();
                }
            });

            $("table img").on("mouseleave", function () {
                $("#img-preview").hide();
            });

            $('#confirmDelete').on('show.bs.modal', function (e) {
                $(this).find('#confirm-id').text($(e.relatedTarget).data('id'));
                $(this).find('#confirm-name').text($(e.relatedTarget).data('name'));
                $(this).find('.btn-ok').attr('href', $(e.relatedTarget).data('href'));
            });

            // Delete item
            $(document).on("click", "[data-delete]", function(e) {
                var _this = $(this);
                console.log(_this.attr('data-delete'));
                alertify
                    .confirm(
                        "@lang('sCommerce::global.confirm_delete')",
                        "@lang('sCommerce::global.you_sure') <b>"+_this.attr('data-name')+"</b> @lang('sCommerce::global.with_id') <b>"+_this.attr('data-delete')+"</b>",
                        function() {
                            alertify.success("@lang('sCommerce::global.deleted')");
                            window.location.href = _this.attr('data-href');
                        },
                        function() {
                            alertify.error("@lang('global.cancel')");
                        })
                    .set('labels', {
                        ok:"@lang('global.delete')",
                        cancel:"@lang('global.cancel')"
                    })
                    .set({transition:'zoom'});
                return false;
            });

            // Flash messages
            @if (session()->has('success'))
                alertify.success("{{session('success')}}");
            @endif
            @if (session()->has('error'))
                alertify.success("{{session('error')}}");
            @endif
        });

        // Save tab content on the fly
        const submitting = document.querySelectorAll('[data-target] a');
        for (let i = 0; i < submitting.length; i++) {
            oldClick = submitting[i].getAttribute('onclick');
            newClick = oldClick.replace('if (!window.__cfRLUnblockHandlers) return false; ', '');
            submitting[i].setAttribute('onclick', newClick + ' if(!window.__cfRLUnblockHandlers){return false;}');
        }

        const stabs = document.querySelectorAll('[data-target]');
        for (let j = 0; j < submitting.length; j++) {
            stabs[j].addEventListener("mouseenter", function(e) {
                if (documentDirty === true) {
                    for (let i = 0; i < submitting.length; i++) {
                        submitting[i].setAttribute('href', 'javascript:void(0);');
                    }
                }
            });
        }

        function tabSave(starget) {
            document.form.back.value = starget;
            saveForm('#form');
        }

        // Form Validation and Saving
        function saveForm(selector) {
            var errors = 0;
            var messages = "";
            var validates = $(selector + " [data-validate]");
            validates.each(function (k, v) {
                var rule = $(v).attr("data-validate").split(":");
                switch (rule[0]) {
                    case "textNoEmpty": // Not an empty field
                        if ($(v).val().length < 1) {
                            messages = messages + $(v).parent().find(".error-text").text() + "<br/>";
                            $(v).parent().removeClass("is-valid").addClass("is-invalid");
                            errors = errors + 1;
                        } else {
                            $(v).parent().removeClass("is-invalid").addClass("is-valid");
                        }
                        break;
                    case "textMustContainDefault": // Must contain the value of the default language
                        var _default = $(v).parents('tbody').find('[name^="s_lang_default"]').val();
                        _index = $(v).val().indexOf(_default);
                        if (_index >= $(v).val().length || _index < 0 || isNaN(_index)) {
                            messages = messages + $(v).parent().find(".error-text").text() + "<br/>";
                            $(v).parent().removeClass("is-valid").addClass("is-invalid");
                            errors = errors + 1;
                        } else {
                            $(v).parent().removeClass("is-invalid").addClass("is-valid");
                        }
                        break;
                    case "textMustContainSiteLang": // Must contain site language list values
                        var _default = $(v).parents('tbody').find('[name^="s_lang_default"]').val();
                        var _config = $(v).parents('tbody').find('[name^="s_lang_config"]').val();
                        var _valid = 1;
                        _index = $(v).val().indexOf(_default);
                        $(v).val().forEach(function (val) {
                            if (_config.indexOf(val) < 0) {
                                return _valid = 0;
                            }
                        });
                        if (_index >= $(v).val().length || _index < 0 || isNaN(_index) || _valid < 1) {
                            messages = messages + $(v).parent().find(".error-text").text() + "<br/>";
                            $(v).parent().removeClass("is-valid").addClass("is-invalid");
                            errors = errors + 1;
                        } else {
                            $(v).parent().removeClass("is-invalid").addClass("is-valid");
                        }
                        break;
                }
            });
            if (errors == 0) {
                $(selector).submit();
            } else {
                $('.notifier').addClass("notifier-error");
                $('.notifier').fadeIn(500);
                $('.notifier').find('.notifier-txt').html(messages);
                setTimeout(function () {
                    $('.notifier').fadeOut(5000);
                }, 2000);
                setTimeout(function () {
                    $('.notifier').removeClass("notifier-error");
                }, 5000);
            }
        }

        var dpOffset = -10;
        var dpformat = 'YYYY-mm-dd hh:mm:00';
        var dpdayNames = @lang('global.dp_dayNames');
        var dpmonthNames = @lang('global.dp_monthNames');
        var dpstartDay = 1;
        var DatePickers = document.querySelectorAll('input.DatePicker');
        if (DatePickers) {
            for (var i = 0; i < DatePickers.length; i++) {
                let format = DatePickers[i].getAttribute("data-format");
                new DatePicker(DatePickers[i], {
                    yearOffset: dpOffset,
                    format: format !== null ? format : dpformat,
                    dayNames: dpdayNames,
                    monthNames: dpmonthNames,
                    startDay: dpstartDay
                });
            }
        }

        function changestate(el) {
            if (parseInt(el.value) === 1) {
                el.value = 0;
            } else {
                el.value = 1;
            }
            documentDirty = true;
        }

        let allowParentSelection = false;

        function enableParentSelection(b) {
            let plock = document.getElementById('plock');
            if (b) {
                parent.tree.ca = "parent";
                plock.className = "fa fa-folder-open";
                allowParentSelection = true;
            } else {
                parent.tree.ca = "open";
                plock.className = "fa fa-folder";
                allowParentSelection = false;
            }
        }

        function setParent(pId, pName) {
            documentDirty = true;
            document.form.parent.value = pId;
            let elm = document.getElementById('parentName');
            if (elm) {
                elm.innerHTML = (pId + " (" + pName + ")");
            }
        }
    </script>
    <img src="{{evo()->getConfig('site_url', '/')}}assets/site/noimage.png" id="img-preview" style="display: none;" class="post-thumbnail">
    <div id="copyright"><a href="https://seigerit.com/" target="_blank"><img src="{{evo()->getConfig('site_url', '/')}}assets/site/seigerit-blue.svg"/></a></div>
@endpush
