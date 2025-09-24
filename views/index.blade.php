@php use Seiger\sCommerce\Facades\sCommerce; @endphp
@extends('manager::template.page')
@section('content')
    <style>
        .nice-select{-webkit-tap-highlight-color:rgba(0,0,0,0);background-color:#fff;border-radius:5px;border:solid 1px #e8e8e8;box-sizing:border-box;clear:both;cursor:pointer;display:block;float:left;font-family:inherit;font-size:14px;font-weight:normal;height:38px;line-height:25px;outline:none;padding-left:18px;padding-right:30px;position:relative;text-align:left !important;transition:all .2s ease-in-out;user-select:none;white-space:nowrap;width:auto}.nice-select:hover{border-color:#dbdbdb}.nice-select:active,.nice-select.open,.nice-select:focus{border-color:#999}.nice-select:after{border-bottom:2px solid #999;border-right:2px solid #999;content:"";display:block;height:5px;margin-top:-4px;pointer-events:none;position:absolute;right:12px;top:50%;transform-origin:66% 66%;transform:rotate(45deg);transition:all .15s ease-in-out;width:5px}.nice-select.open:after{transform:rotate(-135deg)}.nice-select.open .nice-select-dropdown{opacity:1;pointer-events:auto;transform:scale(1) translateY(0)}.nice-select.disabled{border-color:#ededed;color:#999;pointer-events:none}.nice-select.disabled:after{border-color:#ccc}.nice-select.wide{width:100%}.nice-select.wide .nice-select-dropdown{left:0 !important;right:0 !important}.nice-select.right{float:right}.nice-select.right .nice-select-dropdown{left:auto;right:0}.nice-select.small{font-size:12px;height:36px;line-height:34px}.nice-select.small:after{height:4px;width:4px}.nice-select.small .option{line-height:34px;min-height:34px}.nice-select .nice-select-dropdown{margin-top:4px;background-color:#fff;border-radius:5px;box-shadow:0 0 0 1px rgba(68,68,68,.11);pointer-events:none;position:absolute;top:100%;left:0;transform-origin:50% 0;transform:scale(0.75) translateY(19px);transition:all .2s cubic-bezier(0.5, 0, 0, 1.25),opacity .15s ease-out;z-index:9;opacity:0}.nice-select .list{border-radius:5px;box-sizing:border-box;overflow:hidden;padding:0;max-height:210px;overflow-y:auto}.nice-select .list:hover .option:not(:hover){background-color:rgba(0,0,0,0) !important}.nice-select .option{cursor:pointer;font-weight:400;line-height:40px;list-style:none;outline:none;padding-left:18px;padding-right:29px;text-align:left;transition:all .2s}.nice-select .option:hover,.nice-select .option.focus,.nice-select .option.selected.focus{background-color:#f6f6f6}.nice-select .option.selected{font-weight:bold}.nice-select .option.disabled{background-color:rgba(0,0,0,0);color:#999;cursor:default}.nice-select .extra{float:right}.nice-select .optgroup{font-weight:bold}.no-csspointerevents .nice-select .nice-select-dropdown{display:none}.no-csspointerevents .nice-select.open .nice-select-dropdown{display:block}.nice-select .list::-webkit-scrollbar{width:0}.nice-select .has-multiple{white-space:inherit;height:auto;padding:7px 12px;min-height:36px;line-height:22px}.nice-select .has-multiple span.current{border:1px solid #ccc;background:#eee;padding:0 10px;border-radius:3px;display:inline-block;line-height:24px;font-size:14px;margin-bottom:3px;margin-right:3px}.nice-select .has-multiple .multiple-options{display:block;line-height:24px;padding:0}.nice-select .nice-select-search-box{box-sizing:border-box;width:100%;padding:5px;pointer-events:none;border-radius:5px 5px 0 0}.nice-select .nice-select-search{box-sizing:border-box;background-color:#fff;border:1px solid #e8e8e8;border-radius:3px;color:#444;display:inline-block;vertical-align:middle;padding:7px 12px;margin:0 10px 0 0;width:100%;min-height:36px;line-height:22px;height:auto;outline:0 !important;font-size:14px}
    </style>
    <div id="mainloader"><div class="evo__logo">EVO</div></div>
    <h1><i class="@lang('sCommerce::global.icon')" data-tooltip="@lang('sCommerce::global.description')"></i> @lang('sCommerce::global.title')</h1>
    <div class="sectionBody">
        <div class="tab-pane" id="resourcesPane">
            <script>tpResources = new WebFXTabPane(document.getElementById('resourcesPane'), false);</script>
            @foreach($tabs as $tab)
                @if($tab == 'content')
                    @foreach($sCommerceController->langList() as $idx => $lang)
                        <div class="tab-page content{{$lang}}Tab" id="content{{$lang}}Tab">
                            <h2 class="tab">
                                <a onclick="javascript:tabSave('&get={{$tab}}&lang={{$lang}}{{$iUrl}}');" href="{!!$moduleUrl!!}&get={{$tab}}&lang={{$lang}}{{$iUrl}}">
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
                    {!!sCommerce::tabRender($tab, 'sCommerce::'.$tab.'Tab', $sCommerceController->getData())!!}
                @endif
                @if(is_array($events = evo()->invokeEvent('sCommerceManagerAddTabEvent', ['currentTab' => $tab, 'dataInput' => $sCommerceController->getData()])))
                    @foreach($events as $event){!!$event['view']!!}@endforeach
                @endif
            @endforeach
            <script>tpResources.setSelectedTab('{{$get}}Tab');</script>
        </div>
    </div>
@endsection
@push('scripts.top')
    <link rel="shortcut icon" href="{{evo()->getConfig('site_url', '/')}}assets/site/scommerce.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/nice-select2@2.2.0/dist/css/nice-select2.min.css"></script>
    <script src="https://cdn.jsdelivr.net/npm/nice-select2@2.2.0/dist/js/nice-select2.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
    @include('sCommerce::partials.style')
    <script>
        function evoRenderImageCheck(a) {
            var b = document.getElementById('image_for_' + a.target.id), c = new Image;
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
    <link rel="stylesheet" href="//cdn.jsdelivr.net/npm/alertifyjs@1.14.0/build/css/alertify.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.14.0/build/alertify.min.js"></script>
    <script src="media/script/jquery.quicksearch.js"></script>
    <script src="media/script/jquery.nucontextmenu.js"></script>
    <script src="media/script/bootstrap/js/bootstrap.min.js"></script>
    <script src="media/calendar/datepicker.js"></script>
    <script>
        $(document).ready(function () {
            $('.select2').select2();

            $('#confirmDelete').on('show.bs.modal', function (e) {
                $(this).find('#confirm-id').text($(e.relatedTarget).data('id'));
                $(this).find('#confirm-name').text($(e.relatedTarget).data('name'));
                $(this).find('.btn-ok').attr('href', $(e.relatedTarget).data('href'));
            });

            // Delete item
            $(document).on("click", "[data-delete]", function(e) {
                var _this = $(this);
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

            // Duplicate item
            $(document).on("click", "[data-duplicate]", function(e) {
                var _this = $(this);
                alertify.confirm(
                    "@lang('sCommerce::global.confirm_duplicate')",
                    "@lang('sCommerce::global.you_sure_duplicate') <b>"+_this.attr('data-name')+"</b> @lang('sCommerce::global.with_id') <b>"+_this.attr('data-duplicate')+"</b>",
                    function() {
                        alertify.success("@lang('sCommerce::global.copied')");
                        window.location.href = _this.attr('data-href');
                    },
                    function() {
                        alertify.error("@lang('global.cancel')");
                        document.querySelector('.ajs-ok').classList.remove('ajs-ok-info');
                    }
                ).set({
                    labels: {ok:"@lang('global.duplicate')", cancel:"@lang('global.cancel')"},
                    transition: 'zoom',
                    movable: false,
                    closableByDimmer: false,
                    pinnable: false
                });
                document.querySelector('.ajs-ok').classList.add('ajs-ok-info');
                return false;
            });

            // Ordering
            $('.sorting').on('click', function () {
                const urlParams = new URLSearchParams(window.location.search);
                const order = $(this).attr('data-order');
                let direc = 'asc';
                let newHref = '{!!sCommerce::moduleUrl()!!}&get={{$get}}&order=' + order;
                if (urlParams.get('order') == order && urlParams.get('direc') == direc) {
                    direc = 'desc';
                }
                newHref = newHref + '&direc=' + direc;
                if (urlParams.has('cat')) {
                    newHref = newHref + '&cat=' + '{{$cat ?? ''}}';
                }
                window.location.href = newHref;
            });

            // Flash messages
            @if (session()->has('success'))
                alertify.success("{{session('success')}}");
            @endif
            @if (session()->has('error'))
                alertify.success("{{session('error')}}");
            @endif
        });

        // Enable table sorting
        evo.sortable('.sortable > tbody > tr', {complete:function(e){documentDirty=true}});
        evo.sortable('.sortable > div', {complete:function(e){documentDirty=true}});

        let niceSelect2 = document.querySelectorAll('.niceSelect2');
        niceSelect2?.forEach(select => {
            NiceSelect.bind(document.getElementById(select.id), {searchable: true});
        });

        // Image preview
        document.querySelectorAll("table img").forEach(function (img) {
            img.addEventListener("mouseenter", function () {
                var alt = img.getAttribute("alt");
                if (alt && alt.length > 0) {
                    var preview = document.getElementById("img-preview");
                    preview.setAttribute("src", alt);
                    preview.style.display = "block";
                }
            });

            img.addEventListener("mouseleave", function () {
                document.getElementById("img-preview").style.display = "none";
            });
        });

        // Search form
        const searchForm = document.querySelector('input[name="search"]');
        const submitForm = document.querySelector('.scom-submit-search');
        const clearFrom = document.querySelector('.scom-clear-search');
        searchForm?.addEventListener('keypress', (e) => {
            if (e.which == 13) {
                searchFormSend(searchForm.value);
            }
        });
        submitForm?.addEventListener('click', () => {
            searchFormSend(searchForm.value);
        });
        clearFrom?.addEventListener('click', () => {
            searchForm.value = "";
            searchFormSend(searchForm.value);
        });
        function searchFormSend(search) {
            const _url = window.location.pathname;
            const _get = new URLSearchParams(window.location.search);
            let string = '';
            _get.delete('search');
            if (search.length > 0) {
                string = '&search='+search;
            }
            window.location.href = _url+'?'+_get.toString()+string;
        }

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

        function changestate(el){if(parseInt(el.value)===1){el.value=0}else{el.value=1;}documentDirty=true}

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

        //dropdown
        document.addEventListener("click", function (event) {
            const dropdowns = document.querySelectorAll('.dropdown');
            dropdowns.forEach(function(dropdown) {
                if (!dropdown.contains(event.target)) {
                    dropdown.classList.remove('active')
                } else {
                    dropdown.classList.toggle('active')
                }
            });
        });
        //dropdown
        // cookies
        let cookieName = 'scom_per_page';
        if (typeof window.cookieName !== 'undefined') {
            cookieName = window.cookieName;
        }
        const cookieItems = document.querySelectorAll('[data-items]');
        const actualCount = document.querySelector('[data-actual]');
        const cookieValue = document.cookie.split('; ').find(row => row.startsWith(cookieName + '='))?.split('=')[1];
        if (cookieValue !== undefined) {
            actualCount?.setAttribute('data-actual', cookieValue);
        } else {
            setCookie(cookieName, 50, 30)
        }
        // Function to set a cookie
        function setCookie(name, value, days) {
            var expires = "";
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = "; expires=" + date.toUTCString();
            }
            document.cookie = name + "=" + value + expires + "; path=/";
        }
        function getCookie(name) {
            document.cookie
        }
        cookieItems?.forEach(cookieItem => {
            cookieItem.addEventListener('click', (e) => {
                let itemValue = cookieItem.getAttribute('data-items')
                setCookie(cookieName, itemValue, 30)
            })
        })
        document.title = "@lang('sCommerce::global.title') - {{strip_tags(__('sCommerce::global.description'))}}";
    </script>
    <img src="{{evo()->getConfig('site_url', '/')}}assets/site/noimage.png" id="img-preview" style="display: none;" class="post-thumbnail">
    <div id="copyright"><a href="https://seiger.github.io/sCommerce/" target="_blank"><img src="{{evo()->getConfig('site_url', '/')}}assets/site/seigerit-blue.svg" alt="Seiger IT Logo"/></a></div>
@endpush
