<?php namespace Seiger\sCommerce\Integration;

use Carbon\Carbon;
use EvolutionCMS\Models\SiteContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Seiger\sCommerce\Controllers\sCommerceController;
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sCommerce\Models\sProduct;
use Seiger\sLang\Facades\sLang;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Workers\BaseWorker;

/**
 * GoogleMerchantFeed - Worker responsible for generating Google Merchant XML feeds.
 * https://support.google.com/merchants/answer/7052112
 *
 * Supports:
 * - Multi-domain and multi-language configurations
 * - Batched feed generation to stay within Google limits
 * - Atomic file replacement via temporary file rename
 * - Automatic cleanup of obsolete feed files
 * - Detailed file list display after generation
 */
class GoogleMerchantFeed extends BaseWorker
{
    private const DEFAULT_CHUNK = 2500;

    public function identifier(): string
    {
        return 'sGoogleMerchantFeed';
    }

    public function scope(): string
    {
        return 'sCommerce';
    }

    public function icon(): string
    {
        return '<i class="fab fa-google"></i>';
    }

    public function title(): string
    {
        return 'Google Merchant XML';
    }

    public function description(): string
    {
        return 'Generates multi-language Google Merchant feeds with batching.';
    }

    /**
     * Get the widget for the integration.
     *
     * Retrieves the widget of the integration with generated files list.
     *
     * @return string The widget of the integration.
     */
    public function renderWidget(): string
    {
        return view('sTask::widgets.defaultWorkerWidget', ['identifier' => $this->identifier()])->render();
    }

    /**
     * Get generated files from root directory.
     * Reads all XML feed files directly from filesystem.
     *
     * @return array
     */
    public function getGeneratedFiles(): array
    {
        $directory = rtrim(EVO_BASE_PATH, DIRECTORY_SEPARATOR);
        $publicBase = rtrim(EVO_SITE_URL, '/');

        // Get all configured feed slugs to filter files
        $feeds = $this->loadFeedsFromSettings();
        $feedSlugs = [];
        foreach ($feeds as $feed) {
            $normalized = $this->normalizeFeedConfig($feed);
            $slug = $normalized['slug'] ?? '';
            if (!empty($slug)) {
                $feedSlugs[] = $slug;
            }
        }

        // Get all XML files from root directory
        $feedFiles = glob($directory . DIRECTORY_SEPARATOR . '*.xml') ?: [];
        $generatedFiles = [];

        foreach ($feedFiles as $filePath) {
            $filename = basename($filePath);

            // Check if file matches feed pattern (slug-XXX.xml or slug.xml)
            $isFeedFile = false;
            if (empty($feedSlugs)) {
                // If no feeds configured, accept any XML file that looks like a feed
                $isFeedFile = preg_match('/^([a-z0-9-]+)(?:-\d{3})?\.xml$/', $filename);
            } else {
                // Check if file belongs to any configured feed
                foreach ($feedSlugs as $slug) {
                    // Match exact slug.xml or slug-XXX.xml pattern
                    if (preg_match('/^' . preg_quote($slug, '/') . '(?:-\d{3})?\.xml$/', $filename)) {
                        $isFeedFile = true;
                        break;
                    }
                }
            }

            if ($isFeedFile) {
                $generatedFiles[] = [
                    'filename' => $filename,
                    'url' => $publicBase . '/' . $filename,
                ];
            }
        }

        // Sort by filename
        usort($generatedFiles, function($a, $b) {
            return strcmp($a['filename'], $b['filename']);
        });

        return $generatedFiles;
    }

    /**
     * Render settings form for feed definitions stored as JSON.
     *
     * @return string
     */
    public function renderSettings(): string
    {
        // Check if sLang is installed and get languages
        $isLang = evo()->getConfig('check_sLang', false);
        $languages = [];
        $defaultLanguage = '';
        if ($isLang && class_exists(sLang::class)) {
            $langFront = sLang::langFront();
            $langList = sLang::langList();
            foreach ($langFront as $code) {
                if (isset($langList[$code])) {
                    $languages[$code] = $langList[$code]['name'] ?? $code;
                }
            }
            $defaultLanguage = sLang::langDefault();
        }

        $feeds = $this->loadFeedsFromSettings();
        if (!count($feeds)) {
            $feeds = [[
                'slug' => '',
                'domain' => '',
                'lang' => $defaultLanguage,
                'currency' => 'UAH',
                'country' => 'UA',
                'chunk' => self::DEFAULT_CHUNK,
                'include_out_of_stock' => false,
                'google_product_category' => '',
                'site_key' => '',
                'enabled' => true,
            ]];
        }
        $defaultChunk = self::DEFAULT_CHUNK;

        // Load countries from manager language file
        $countries = $this->loadCountriesFromLang();

        // Load Google Product Categories will be done per feed block based on domain

        $availableCurrencies = sCommerce::config('basic.available_currencies', []);
        // If no currencies configured, use main currency as fallback
        if (empty($availableCurrencies)) {
            $mainCurrency = sCommerce::config('basic.main_currency', 'USD');
            if ($mainCurrency) {
                $availableCurrencies = [$mainCurrency];
            }
        }
        $currenciesCollection = sCommerce::getCurrencies($availableCurrencies);
        $currencies = $currenciesCollection->pluck('name', 'alpha')->toArray();

        // Check if sMultisite is installed
        $isMultisite = evo()->getConfig('check_sMultisite', false);
        $multisiteSites = [];
        $domainToSiteKey = [];
        if ($isMultisite && class_exists(\Seiger\sMultisite\Models\sMultisite::class)) {
            $multisiteSites = \Seiger\sMultisite\Models\sMultisite::all()->mapWithKeys(function ($site) use (&$domainToSiteKey) {
                $scheme = evo()->getConfig('server_protocol', 'https');
                $url = rtrim($scheme . '://' . $site->domain, '/');
                $domainToSiteKey[$url] = $site->key ?? $site->site_key ?? 'default';
                return [$url => $site->site_name . ' (' . $site->domain . ')'];
            })->toArray();
        }

        $defaultDomain = !$isMultisite ? rtrim(evo()->getConfig('site_url', EVO_BASE_URL), '/') : '';
        $firstMultisiteDomain = $isMultisite && !empty($multisiteSites) ? array_key_first($multisiteSites) : '';

        $feedsCount = count($feeds);
        $slugRequired = $feedsCount > 1;

        // Preload Google Product Categories for all domains
        $allGoogleCategories = [];
        if ($isMultisite && !empty($domainToSiteKey)) {
            // Load all categories once (contains all domains)
            $allCategoriesData = $this->getGoogleProductCategories();
            foreach ($domainToSiteKey as $domain => $siteKey) {
                if (isset($allCategoriesData[$siteKey])) {
                    $allGoogleCategories[$domain] = $allCategoriesData[$siteKey];
                } elseif (is_array($allCategoriesData) && !empty($allCategoriesData)) {
                    $firstKey = array_key_first($allCategoriesData);
                    $allGoogleCategories[$domain] = $allCategoriesData[$firstKey] ?? [];
                } else {
                    $allGoogleCategories[$domain] = [];
                }
            }
        } else {
            $categoriesData = $this->getGoogleProductCategories();
            // If it's nested structure, extract default or first
            if (is_array($categoriesData) && !empty($categoriesData)) {
                $firstKey = array_key_first($categoriesData);
                if (is_array($categoriesData[$firstKey] ?? null)) {
                    $allGoogleCategories[$defaultDomain] = $categoriesData['default'] ?? $categoriesData[$firstKey] ?? [];
                } else {
                    $allGoogleCategories[$defaultDomain] = $categoriesData;
                }
            } else {
                $allGoogleCategories[$defaultDomain] = [];
            }
        }

        // Render feed blocks
        $feedBlocks = '';
        foreach ($feeds as $index => $feed) {
            // Determine site_key for this feed
            $feedSiteKey = '';
            $domainValue = $feed["domain"] ?? "";
            if (!$domainValue) {
                if ($isMultisite && !empty($multisiteSites)) {
                    $domainValue = array_key_first($multisiteSites);
                } else {
                    $domainValue = rtrim(evo()->getConfig("site_url", EVO_BASE_URL), "/");
                }
            }
            if ($isMultisite && !empty($domainToSiteKey) && isset($domainToSiteKey[$domainValue])) {
                $feedSiteKey = $domainToSiteKey[$domainValue];
            }

            // Load Google Product Categories for this domain/site_key
            // getGoogleProductCategories() returns nested array by domain key when multisite is enabled
            // Structure: ["default" => [id => name], "polypro" => [id => name], ...]
            // We need to extract categories for the current domain's site_key
            $googleCategories = [];
            if (isset($allGoogleCategories[$domainValue])) {
                $categoriesForDomain = $allGoogleCategories[$domainValue];
                // Check if it's a nested array (multisite structure)
                if ($isMultisite && $feedSiteKey && is_array($categoriesForDomain) && isset($categoriesForDomain[$feedSiteKey])) {
                    // Extract categories for this site_key
                    $googleCategories = $categoriesForDomain[$feedSiteKey];
                } elseif (is_array($categoriesForDomain) && !empty($categoriesForDomain)) {
                    // Check if first element is an array (nested structure)
                    $firstKey = array_key_first($categoriesForDomain);
                    if (is_array($categoriesForDomain[$firstKey] ?? null)) {
                        // It's nested, try to get by site_key or use first available
                        $googleCategories = $categoriesForDomain[$feedSiteKey] ?? $categoriesForDomain[$firstKey] ?? [];
                    } else {
                        // It's a flat array [id => name]
                        $googleCategories = $categoriesForDomain;
                    }
                }
            } else {
                // Fallback: load directly and extract
                $allCategoriesForFeed = $this->getGoogleProductCategories($feedSiteKey);
                if ($isMultisite && $feedSiteKey && is_array($allCategoriesForFeed) && isset($allCategoriesForFeed[$feedSiteKey])) {
                    $googleCategories = $allCategoriesForFeed[$feedSiteKey];
                } elseif (is_array($allCategoriesForFeed) && !empty($allCategoriesForFeed)) {
                    $firstKey = array_key_first($allCategoriesForFeed);
                    if (is_array($allCategoriesForFeed[$firstKey] ?? null)) {
                        $googleCategories = $allCategoriesForFeed[$feedSiteKey] ?? $allCategoriesForFeed[$firstKey] ?? [];
                    } else {
                        $googleCategories = $allCategoriesForFeed;
                    }
                }
            }

            // Ensure we have an array
            if (!is_array($googleCategories)) {
                $googleCategories = [];
            }

            $feedBlocks .= Blade::render('
                @php
                    $title = ($feed["slug"] ?? "") ? htmlspecialchars($feed["slug"]) : "Новий фід";
                    $enabledChecked = !isset($feed["enabled"]) || $feed["enabled"] ? "checked" : "";
                    $stockChecked = ($feed["include_out_of_stock"] ?? false) ? "checked" : "";
                    $slugRequiredAttr = $slugRequired ? "required" : "";
                    $slugAsterisk = $slugRequired ? "<span style=\"color:#dc3545;\">*</span>" : "";
                    $showDeleteButton = $feedsCount > 1;
                @endphp
                <div class="gm-feed" style="border:1px solid #dbe3f0; border-radius:10px; padding:1rem; margin-bottom:1rem; background:#f9fbff;">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.75rem;">
                        <h5 style="margin:0; font-weight:600; color:#1f4d8c;">{{$title}}</h5>
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <span class="badge badge-info"><i class="fas fa-sync-alt"></i> Google Merchant</span>
                            @if($showDeleteButton)
                                <button type="button" class="btn btn-sm btn-danger" onclick="removeGoogleFeed(this)" title="Видалити фід" style="padding:0.25rem 0.5rem; font-size:0.75rem;">
                                    <i data-lucide="trash-2" style="width:14px;height:14px;"></i>
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <label>
                                Slug {!! $slugAsterisk !!}
                                <i data-lucide="help-circle" class="settings-icon" data-tooltip="Унікальний ідентифікатор фіду. Використовується для генерації назв файлів XML." style="width:14px;height:14px;"></i>
                            </label>
                            <input type="text" name="slug[]" class="form-control" value="{{$feed["slug"]?? ""}}" {{$slugRequiredAttr}}>
                        </div>
                        @if($isMultisite && !empty($multisiteSites))
                            <div class="col-sm-6">
                                <label>
                                    Domain <span style="color:#dc3545;">*</span>
                                    <i data-lucide="help-circle" class="settings-icon" data-tooltip="Домен сайту для генерації посилань на товари в XML фіді." style="width:14px;height:14px;"></i>
                                </label>
                                <select name="domain[]" class="form-control" required>
                                    @foreach($multisiteSites as $url => $label)
                                        <option value="{{$url}}" {{$url === $domainValue ? "selected" : ""}}>{{$label}}</option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <input type="hidden" name="domain[]" value="{{htmlspecialchars($domainValue, ENT_QUOTES, "UTF-8")}}">
                        @endif
                        @if($isLang && !empty($languages))
                            <div class="col-sm-4">
                                <label>
                                    Language
                                    <i data-lucide="help-circle" class="settings-icon" data-tooltip="Мова для перекладів товарів у фіді. Визначає, які переклади будуть використовуватись." style="width:14px;height:14px;"></i>
                                </label>
                                <select name="lang[]" class="form-control">
                                    @foreach($languages as $code => $name)
                                        @php
                                            $feedLang = $feed["lang"] ?? $defaultLanguage;
                                        @endphp
                                        <option value="{{$code}}" {{$feedLang === $code ? "selected" : ""}}>{{$name}}</option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <input type="hidden" name="lang[]" value="">
                        @endif
                        <div class="col-sm-4">
                            <label>
                                Currency <span style="color:#dc3545;">*</span>
                                <i data-lucide="help-circle" class="settings-icon" data-tooltip="Валюта для відображення цін у Google Merchant. Має відповідати валюті, налаштованій в sCommerce." style="width:14px;height:14px;"></i>
                            </label>
                            <select name="currency[]" class="form-control" required>
                                @foreach($currencies as $code => $name)
                                    <option value="{{$code}}" {{($feed["currency"] ?? "UAH") === $code ? "selected" : ""}}>{{$name}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label>
                                Country <span style="color:#dc3545;">*</span>
                                <i data-lucide="help-circle" class="settings-icon" data-tooltip="Країна призначення фіду. Використовується для g:target_country в XML." style="width:14px;height:14px;"></i>
                            </label>
                            <select name="country[]" class="form-control" required>
                                @foreach($countries as $code => $name)
                                    <option value="{{$code}}" {{ ($feed["country"] ?? "UA") === $code ? "selected" : ""}}>{{$name}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label>
                                Chunk size
                                <i data-lucide="help-circle" class="settings-icon" data-tooltip="Кількість товарів в одному XML файлі (100-10000). При великій кількості товарів створюється кілька файлів." style="width:14px;height:14px;"></i>
                            </label>
                            <input type="number" name="chunk[]" class="form-control" min="100" max="10000" value="{{$feed["chunk"] ?? $defaultChunk}}">
                        </div>
                        @if($isMultisite)
                            <input type="hidden" name="site_key[]" value="">
                        @endif
                        <div class="col-sm-6">
                            <label>
                                Google Product Category
                                <i data-lucide="help-circle" class="settings-icon" data-tooltip="Категорія товарів Google (наприклад: Apparel & Accessories > Clothing). Використовується для g:google_product_category в XML." style="width:14px;height:14px;"></i>
                            </label>
                            <select name="category[]" class="form-control" style="width:100%;">
                                @foreach($googleCategories as $id => $name)
                                    @php
                                        $feedCategory = $feed["category"] ?? "";
                                    @endphp
                                    <option value="{{$id}}" {{$feedCategory === (string)$id ? "selected" : ""}}>{{$name}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-3">
                            <label>
                                <input type="checkbox" name="out_of_stock[]" {{$stockChecked}}> Include out of stock
                                <i data-lucide="help-circle" class="settings-icon" data-tooltip="Якщо увімкнено, товари з нульовим залишком також будуть включені в фід." style="width:14px;height:14px;"></i>
                            </label>
                        </div>
                        <div class="col-sm-3">
                            <label>
                                <input type="checkbox" name="enabled[]" {{$enabledChecked}}> Enabled
                                <i data-lucide="help-circle" class="settings-icon" data-tooltip="Увімкнути або вимкнути генерацію цього фіду." style="width:14px;height:14px;"></i>
                            </label>
                        </div>
                    </div>
                </div>
            ', [
                'feed' => $feed,
                'languages' => $languages,
                'countries' => $countries,
                'currencies' => $currencies,
                'googleCategories' => $googleCategories,
                'domainValue' => $domainValue,
                'slugRequired' => $slugRequired,
                'isMultisite' => $isMultisite,
                'multisiteSites' => $multisiteSites,
                'defaultChunk' => $defaultChunk,
                'domainToSiteKey' => $domainToSiteKey ?? [],
                'isLang' => $isLang,
                'defaultLanguage' => $defaultLanguage,
                'feedsCount' => $feedsCount,
            ]);
        }

        return Blade::render('
            <style>
                .gm-feed label {
                    display: inline-flex;
                    align-items: center;
                    gap: 0.25rem;
                }
                .gm-feed label .settings-icon {
                    vertical-align: middle;
                    flex-shrink: 0;
                }
                .gm-feed label input[type="checkbox"] {
                    margin-right: 0.25rem;
                }
            </style>
            <div class="form-group">
                <input type="hidden" name="feeds" value="">
                <div id="gmFeedsContainer">
                    {!! $feedBlocks !!}
                </div>
                <button type="button" class="btn btn-secondary" onclick="addGoogleFeed()">+ Додати фід</button>
                <div style="margin-top: 1rem; font-size: 0.875rem; color: #6c757d;">
                    <span style="color:#dc3545;">*</span> - обов&#39;язкові поля
                </div>
            </div>
            <hr>
            <script>
                const defaultDomain = @json($defaultDomain);
                const firstMultisiteDomain = @json($firstMultisiteDomain);
                const isMultisite = @json($isMultisite);
                const isLang = @json($isLang);
                const defaultLanguage = @json($defaultLanguage);
                const domainToSiteKey = @json($domainToSiteKey);
                const allGoogleCategories = @json($allGoogleCategories);

                function updateGoogleCategories(domainSelect, categorySelect) {
                    const domain = domainSelect.value || domainSelect.getAttribute("value") || "";
                    let categories = allGoogleCategories[domain] || {};
                    
                    // Check if categories is a nested structure (multisite)
                    // Structure: {"default": {id: name}, "polypro": {id: name}, ...}
                    if (categories && typeof categories === "object" && !Array.isArray(categories)) {
                        const firstKey = Object.keys(categories)[0];
                        // If first value is also an object (nested structure), extract by site_key
                        if (firstKey && categories[firstKey] && typeof categories[firstKey] === "object" && !Array.isArray(categories[firstKey])) {
                            // Get site_key for this domain
                            const siteKey = domainToSiteKey[domain] || firstKey;
                            categories = categories[siteKey] || categories[firstKey] || {};
                        }
                    }
                    
                    // Clear existing options
                    categorySelect.innerHTML = "";
                    
                    // Add categories (now should be flat object {id: name})
                    for (const [id, name] of Object.entries(categories)) {
                        const option = document.createElement("option");
                        option.value = id;
                        option.textContent = name;
                        categorySelect.appendChild(option);
                    }
                    
                    // Select is already initialized, no need for select2
                }

                // Initialize domain change handlers
                document.addEventListener("DOMContentLoaded", function() {
                    const container = document.getElementById("gmFeedsContainer");
                    if (container) {
                        // Update slug required status and delete buttons
                        updateSlugRequired();
                        updateDeleteButtons();
                        
                        container.querySelectorAll(".gm-feed").forEach(feedEl => {
                            const domainSelect = feedEl.querySelector("[name=\"domain[]\"]");
                            const categorySelect = feedEl.querySelector("[name=\"category[]\"]");
                            
                            if (domainSelect && categorySelect) {
                                // Set initial categories
                                const domain = domainSelect.type === "hidden" ? domainSelect.value : domainSelect.value;
                                updateGoogleCategories(domainSelect, categorySelect);
                                
                                // Add change handler if it is a select
                                if (domainSelect.tagName === "SELECT") {
                                    domainSelect.addEventListener("change", function() {
                                        updateGoogleCategories(this, categorySelect);
                                    });
                                }
                            }
                        });
                    }
                });

                /**
                 * Custom serialization for Google Merchant Feed.
                 * Collects all feed fields and creates a structured multi-dimensional object.
                 */
                function serializeWorkerSettings(form) {
                    const container = document.getElementById("gmFeedsContainer");
                    if (!container) {
                        console.warn("Google Merchant Feed: Container not found");
                        return;
                    }
                    
                    const feedBlocks = container.querySelectorAll(".gm-feed");
                    if (feedBlocks.length === 0) {
                        console.warn("Google Merchant Feed: No feed blocks found");
                        return;
                    }
                    
                    const feedsObject = {};
                    
                    feedBlocks.forEach((feedBlock, index) => {
                        // Collect all field values from this feed block
                        const feed = {};
                        
                        // Get slug
                        const slugInput = feedBlock.querySelector("[name=\"slug[]\"]");
                        const slug = slugInput ? slugInput.value.trim() : "";
                        const feedKey = slug !== "" ? slug : "feed_" + index;
                        
                        // Get domain
                        const domainInput = feedBlock.querySelector("[name=\"domain[]\"]");
                        feed.domain = domainInput ? domainInput.value.trim() : "";
                        
                        // Get language
                        const languageInput = feedBlock.querySelector("[name=\"lang[]\"]");
                        feed.lang = languageInput ? languageInput.value.trim() : "";
                        
                        // Get currency
                        const currencyInput = feedBlock.querySelector("[name=\"currency[]\"]");
                        feed.currency = currencyInput ? currencyInput.value.trim() : "";
                        
                        // Get country
                        const countryInput = feedBlock.querySelector("[name=\"country[]\"]");
                        feed.country = countryInput ? countryInput.value.trim() : "";
                        
                        // Get chunk
                        const chunkInput = feedBlock.querySelector("[name=\"chunk[]\"]");
                        feed.chunk = chunkInput ? chunkInput.value.trim() : "";
                        
                        
                        // Get category
                        const categoryInput = feedBlock.querySelector("[name=\"category[]\"]");
                        feed.category = categoryInput ? categoryInput.value.trim() : "";
                        
                        // Get enabled checkbox
                        const enabledInput = feedBlock.querySelector("[name=\"enabled[]\"]");
                        feed.enabled = enabledInput && enabledInput.checked ? "on" : "";
                        
                        // Get out_of_stock checkbox
                        const outOfStockInput = feedBlock.querySelector("[name=\"out_of_stock[]\"]");
                        feed.include_out_of_stock = outOfStockInput && outOfStockInput.checked;
                        
                        // Set slug in feed object
                        feed.slug = slug;
                        
                        // Add site_key for multisite
                        if (isMultisite && domainToSiteKey && feed.domain) {
                            feed.site_key = domainToSiteKey[feed.domain] || "";
                        }
                        
                        // Store feed in object with slug as key
                        feedsObject[feedKey] = feed;
                    });
                    
                    // Store in hidden input
                    const feedsInput = form.querySelector(\'input[name="feeds"]\');
                    if (feedsInput) {
                        const jsonValue = JSON.stringify(feedsObject);
                        feedsInput.value = jsonValue;
                        console.log("Google Merchant Feed: Serialized feeds:", jsonValue);
                    } else {
                        console.error("Google Merchant Feed: Hidden input \'feeds\' not found");
                    }
                    
                    // Remove temporary array fields before submission
                    // This prevents them from being sent as separate form fields
                    const arrayFields = form.querySelectorAll(\'input[name$="[]"], select[name$="[]"]\');
                    arrayFields.forEach(input => {
                        // Remove all array fields that are part of feed blocks
                        if (input.closest(".gm-feed")) {
                            input.remove();
                        }
                    });
                }

                function updateSlugRequired() {
                    const container = document.getElementById("gmFeedsContainer");
                    const feeds = container.querySelectorAll(".gm-feed");
                    const isRequired = feeds.length > 1;
                    
                    feeds.forEach(feedEl => {
                        const slugInput = feedEl.querySelector("[name=\"slug[]\"]");
                        const slugLabel = slugInput ? slugInput.closest(".col-sm-6").querySelector("label") : null;
                        
                        if (slugInput && slugLabel) {
                            if (isRequired) {
                                slugInput.setAttribute("required", "required");
                                if (!slugLabel.querySelector("span[style*=\"color:#dc3545\"]")) {
                                    const asterisk = document.createElement("span");
                                    asterisk.style.color = "#dc3545";
                                    asterisk.textContent = " *";
                                    slugLabel.insertBefore(asterisk, slugLabel.querySelector(".settings-icon"));
                                }
                            } else {
                                slugInput.removeAttribute("required");
                                const asterisk = slugLabel.querySelector("span[style*=\"color:#dc3545\"]");
                                if (asterisk) {
                                    asterisk.remove();
                                }
                            }
                        }
                    });
                }

                function updateDeleteButtons() {
                    const container = document.getElementById("gmFeedsContainer");
                    const feeds = container.querySelectorAll(".gm-feed");
                    const showDelete = feeds.length > 1;
                    
                    feeds.forEach(feedEl => {
                        const deleteButton = feedEl.querySelector("button[onclick*=\"removeGoogleFeed\"]");
                        if (deleteButton) {
                            if (showDelete) {
                                deleteButton.style.display = "";
                            } else {
                                deleteButton.style.display = "none";
                            }
                        }
                    });
                }

                function removeGoogleFeed(button) {
                    const feedBlock = button.closest(".gm-feed");
                    if (feedBlock && confirm("Ви впевнені, що хочете видалити цей фід?")) {
                        feedBlock.remove();
                        updateSlugRequired();
                        updateDeleteButtons();
                        
                        // Reinitialize lucide icons after removal
                        if (window.lucide) {
                            window.lucide.createIcons();
                        }
                    }
                }

                function addGoogleFeed() {
                    const container = document.getElementById("gmFeedsContainer");
                    const template = container.querySelector(".gm-feed").cloneNode(true);
                    template.querySelectorAll("input, select, textarea").forEach(input => {
                        if (input.type === "checkbox") {
                            input.checked = false;
                        } else if (input.type === "hidden" && input.name === "domain[]" && !isMultisite) {
                            input.value = defaultDomain;
                        } else if (input.name === "domain[]" && input.tagName === "SELECT" && isMultisite) {
                            input.value = firstMultisiteDomain;
                        } else if (input.name === "lang[]" && input.tagName === "SELECT" && isLang) {
                            input.value = defaultLanguage;
                        } else if (input.tagName === "SELECT") {
                            // Reset select
                            input.value = "";
                        } else {
                            input.value = "";
                        }
                    });
                    container.appendChild(template);
                    updateSlugRequired();
                    updateDeleteButtons();
                    // Initialize lucide icons for the new feed block
                    if (window.lucide) {
                        window.lucide.createIcons();
                    }
                    
                    // Initialize Google categories for the new feed block
                    const domainSelect = template.querySelector("[name=\"domain[]\"]");
                    const categorySelect = template.querySelector("[name=\"category[]\"]");
                    if (domainSelect && categorySelect) {
                        updateGoogleCategories(domainSelect, categorySelect);
                        
                        // Add change handler if it is a select
                        if (domainSelect.tagName === "SELECT") {
                            domainSelect.addEventListener("change", function() {
                                updateGoogleCategories(this, categorySelect);
                            });
                        }
                    } else if (categorySelect) {
                        // Category select is already initialized
                    }
                }

                // Serialization is now handled by universal serializeArrayFields() in workerSettings.blade.php
                // Custom serializeWorkerSettings() will be called automatically if defined
                
                // Initialize lucide icons on page load
                if (window.lucide) {
                    window.lucide.createIcons();
                }
                
                // Selects are already initialized, no need for select2
            </script>
        ', [
            'feedBlocks' => $feedBlocks,
            'defaultChunk' => $defaultChunk,
            'defaultDomain' => $defaultDomain,
            'firstMultisiteDomain' => $firstMultisiteDomain,
            'isMultisite' => $isMultisite,
            'domainToSiteKey' => $domainToSiteKey,
            'allGoogleCategories' => $allGoogleCategories,
            'isLang' => $isLang,
            'defaultLanguage' => $defaultLanguage,
        ]);
    }

    /**
     * Generate Google Merchant feeds for all configured feeds.
     *
     * @param sTaskModel $task
     * @param array $opt
     * @return void
     */
    public function taskMake(sTaskModel $task, array $opt = []): void
    {
        @ini_set('auto_detect_line_endings', '1');
        @ini_set('output_buffering', '0');

        try {
            $feeds = $this->getConfiguredFeeds($opt);
            if (!count($feeds)) {
                throw new \RuntimeException(
                    'No Google Merchant feeds configured. Please configure feeds in worker settings.'
                );
            }

            $task->update([
                'status' => sTaskModel::TASK_STATUS_RUNNING,
                'message' => 'Preparing Google Merchant feeds...',
            ]);

            $manifestBySite = [];
            $filesBySite = [];

            $totalFeeds = count($feeds);
            $feedIndex = 0;

            foreach ($feeds as $feed) {
                $feedIndex++;
                $normalized = $this->normalizeFeedConfig($feed);

                $result = $this->generateFeed($task, $normalized, $feedIndex, $totalFeeds);

                $siteKey = $normalized['site_key'];
                $manifestBySite[$siteKey]['directory'] = $normalized['directory'];
                $manifestBySite[$siteKey]['public_base'] = $normalized['public_base'];
                $manifestBySite[$siteKey]['feeds'][] = $result['manifest'];
                $filesBySite[$siteKey] = array_merge($filesBySite[$siteKey] ?? [], $result['filenames']);
            }

            // Cleanup obsolete files and prepare feed summary
            $feedSummary = [];
            foreach ($manifestBySite as $siteKey => $manifest) {
                $directory = $manifest['directory'];
                $publicBase = $manifest['public_base'];
                $feedList = $manifest['feeds'] ?? [];
                $allFiles = $filesBySite[$siteKey] ?? [];

                // Cleanup old files
                $this->cleanupDirectory($directory, $allFiles);

                // Build summary for each feed
                foreach ($feedList as $feedInfo) {
                    $feedSummary[] = [
                        'slug' => $feedInfo['slug'] ?? 'unknown',
                        'domain' => $feedInfo['domain'] ?? 'unknown',
                        'files' => $feedInfo['files'] ?? [],
                    ];
                }
            }

            // Build success message with file list
            $message = "**Google Merchant feeds generated successfully.**\n\n";
            foreach ($feedSummary as $feed) {
                $message .= "**Feed: {$feed['slug']}** ({$feed['domain']})\n";
                if (empty($feed['files'])) {
                    $message .= "⚠️ No files generated (no products found)\n";
                } else {
                    $fileCount = count($feed['files']);
                    $message .= "📄 Files generated: {$fileCount}\n";
                    foreach ($feed['files'] as $file) {
                        $url = $file['url'] ?? '';
                        $filename = $file['filename'] ?? 'unknown';
                        if ($url) {
                            $message .= "   • [{$filename}]({$url})\n";
                        } else {
                            $message .= "   • {$filename}\n";
                        }
                    }
                }
                $message .= "\n";
            }
            $message .= "**Note:** Submit these XML files to Google Merchant Center. For multiple files, consider creating a ZIP archive.";

            $task->update([
                'status' => sTaskModel::TASK_STATUS_FINISHED,
                'progress' => 100,
                'message' => $message,
                'finished_at' => now(),
            ]);

            $this->pushProgress($task, [
                'status' => sTaskModel::statusText(sTaskModel::TASK_STATUS_FINISHED),
                'progress' => 100,
                'message' => 'Google Merchant feeds generated successfully. Check task details for file list.',
            ]);
        } catch (\Throwable $e) {
            $message = 'Google Merchant feed generation failed: ' . $e->getMessage();

            Log::error($message, [
                'exception' => $e,
            ]);

            $task->update([
                'status' => sTaskModel::TASK_STATUS_FAILED,
                'message' => $message,
                'finished_at' => now(),
            ]);

            $this->pushProgress($task, [
                'status' => sTaskModel::statusText(sTaskModel::TASK_STATUS_FAILED),
                'progress' => 0,
                'message' => $message,
            ]);

            throw $e;
        }
    }

    /**
     * Retrieve enabled feeds from configuration or overrides.
     *
     * @param array $opt
     * @return array
     */
    protected function getConfiguredFeeds(array $opt): array
    {
        $feeds = [];

        if (isset($opt['feeds']) && is_array($opt['feeds'])) {
            $feeds = $opt['feeds'];
        }

        if (!count($feeds)) {
            $feeds = $this->loadFeedsFromSettings();
        }

        return array_values(array_filter($feeds, static function ($feed) {
            return is_array($feed) && (bool)($feed['enabled'] ?? true);
        }));
    }

    /**
     * Retrieve feed definitions stored in worker settings.
     *
     * @return array
     */
    protected function loadFeedsFromSettings(): array
    {
        $feeds = $this->getSettingValue('feeds');
        if ($feeds && $feeds !== '') {
            return $this->normalizeFeedArray($feeds);
        }

        return [];
    }

    /**
     * Normalize feed payload coming either as array, object, or JSON string.
     * Supports both array format: [{"slug": "...", ...}] and object format: {"slug_key": {"slug": "...", ...}}
     *
     * @param mixed $value
     * @return array
     */
    protected function normalizeFeedArray(mixed $value): array
    {
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return [];
            }

            $decoded = json_decode($value, true);
            if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
                return [];
            }

            $value = $decoded;
        }

        if (!is_array($value)) {
            return [];
        }

        // If it's a single feed object (has 'domain' or 'slug' keys directly), wrap it
        if (array_key_exists('domain', $value) || array_key_exists('slug', $value)) {
            $value = [$value];
        }

        $normalized = [];

        // Check if it's an object with string keys (structured format: {"feed_slug": {...}})
        $isStructuredObject = false;
        foreach (array_keys($value) as $key) {
            if (is_string($key) && !is_numeric($key)) {
                $isStructuredObject = true;
                break;
            }
        }

        if ($isStructuredObject) {
            // Object format: {"feed_slug": {"slug": "...", ...}}
            foreach ($value as $key => $feed) {
                if (is_array($feed) && count($feed)) {
                    // Ensure slug is set from key if not present in feed
                    if (!isset($feed['slug']) || $feed['slug'] === '') {
                        $feed['slug'] = $key;
                    }
                    $normalized[] = $feed;
                }
            }
        } else {
            // Array format: [{"slug": "...", ...}]
            foreach ($value as $feed) {
                if (is_array($feed) && count($feed)) {
                    $normalized[] = $feed;
                }
            }
        }

        return $normalized;
    }

    /**
     * Helper to safely access worker settings.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getSettingValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings(), $key, $default);
    }

    /**
     * Normalize feed configuration.
     *
     * @param array $feed
     * @return array
     */
    protected function normalizeFeedConfig(array $feed): array
    {
        // Determine site_key: use from feed, or from domain if multisite, or default
        $siteKey = $feed['site_key'] ?? null;
        if (!$siteKey && evo()->getConfig('check_sMultisite', false) && class_exists(\Seiger\sMultisite\Models\sMultisite::class)) {
            $domain = $this->normalizeDomain($feed['domain'] ?? EVO_SITE_URL);
            $site = \Seiger\sMultisite\Models\sMultisite::where('domain', parse_url($domain, PHP_URL_HOST))->first();
            if ($site) {
                $siteKey = $site->key ?? $site->site_key ?? 'default';
            }
        }
        $siteKey = $siteKey ?? evo()->getConfig('site_key', 'default');

        // Support both 'lang' and 'language' field names
        $languageValue = $feed['lang'] ?? $feed['language'] ?? null;
        $slug = $feed['slug'] ?? Str::slug(($feed['domain'] ?? '') . '-' . ($languageValue ?? '') . '-' . ($feed['currency'] ?? ''));
        $chunk = max(100, min(10000, (int)($feed['chunk'] ?? self::DEFAULT_CHUNK)));

        $isMultisite = evo()->getConfig('check_sMultisite', false);
        // All feeds are generated in root directory
        $directory = rtrim(EVO_BASE_PATH, DIRECTORY_SEPARATOR);
        $publicBase = rtrim(EVO_SITE_URL, '/');

        // Determine language: use from feed if provided, or null if sLang not installed
        $language = null;
        if ($languageValue && $languageValue !== '') {
            $language = strtolower($languageValue);
        } elseif (evo()->getConfig('check_sLang', false) && class_exists(\Seiger\sLang\Facades\sLang::class)) {
            $language = strtolower(sLang::langDefault());
        }

        // Support both 'category' and 'google_product_category' field names
        $googleProductCategory = $feed['category'] ?? $feed['google_product_category'] ?? null;

        // Support both 'include_out_of_stock' field name
        $includeOutOfStock = $feed['include_out_of_stock'] ?? false;

        return [
            'slug' => $slug ?: Str::slug($siteKey . '-' . ($language ?? 'base')),
            'domain' => $this->normalizeDomain($feed['domain'] ?? EVO_SITE_URL),
            'language' => $language,
            'currency' => strtoupper($feed['currency'] ?? sCommerce::config('basic.main_currency', 'USD')),
            'country' => strtoupper($feed['country'] ?? 'UA'),
            'title' => $feed['title'] ?? (evo()->getConfig('site_name', 'Store') . ' | Google Merchant'),
            'description' => $feed['description'] ?? 'Automatically generated Google Merchant feed.',
            'chunk' => $chunk,
            'include_out_of_stock' => (bool)$includeOutOfStock,
            'google_product_category' => $googleProductCategory,
            'site_key' => $siteKey,
            'directory' => $directory,
            'public_base' => $publicBase,
            'is_multisite' => $isMultisite,
        ];
    }


    /**
     * Generate a single feed (may produce several chunk files).
     *
     * @param sTaskModel $task
     * @param array $config
     * @param int $feedIndex
     * @param int $totalFeeds
     * @return array
     */
    protected function generateFeed(sTaskModel $task, array $config, int $feedIndex, int $totalFeeds): array
    {
        $query = $this->buildProductQuery($config);
        $totalProducts = (clone $query)->count();
        $chunkCounter = 0;
        $processed = 0;
        $filenames = [];

        if ($totalProducts === 0) {
            Log::warning('Google Merchant Feed: No products found for feed', [
                'slug' => $config['slug'] ?? 'unknown',
                'domain' => $config['domain'] ?? 'unknown',
                'include_out_of_stock' => $config['include_out_of_stock'] ?? false,
            ]);

            $task->update([
                'message' => sprintf(
                    'Feed "%s": No products found matching criteria (include_out_of_stock: %s)',
                    $config['slug'] ?? 'unknown',
                    $config['include_out_of_stock'] ? 'yes' : 'no'
                ),
            ]);

            return [
                'filenames' => $filenames,
                'manifest' => $this->buildManifestEntry($config, []),
            ];
        }

        // Determine if this will be a single file feed
        $estimatedChunks = ceil($totalProducts / $config['chunk']);
        $isSingleFile = $estimatedChunks == 1;

        $query->chunk($config['chunk'], function ($products) use (
            $config,
            &$chunkCounter,
            &$processed,
            $totalProducts,
            $feedIndex,
            $totalFeeds,
            &$filenames,
            $task,
            $isSingleFile
        ) {
            $chunkCounter++;

            // If single file, use slug-based name or feed.xml if slug is empty
            if ($isSingleFile) {
                $slug = $config['slug'] ?? '';
                $fileName = !empty($slug) ? $slug . '.xml' : 'feed.xml';
            } else {
                $fileName = $this->buildFeedFilename($config['slug'], $chunkCounter);
            }

            $tmpPath = $config['directory'] . DIRECTORY_SEPARATOR . $fileName . '.tmp';
            $finalPath = $config['directory'] . DIRECTORY_SEPARATOR . $fileName;

            $this->writeXmlChunk($tmpPath, $products, $config);
            $this->atomicReplace($tmpPath, $finalPath);

            $filenames[] = $fileName;
            $processed += $products->count();

            $this->pushProgress($task, [
                'progress' => $this->calculateProgress($feedIndex, $totalFeeds, $processed, $totalProducts),
                'processed' => $processed,
                'total' => $totalProducts,
                'message' => sprintf(
                    'Feed %s (%d/%d): %d / %d products',
                    $config['slug'],
                    $feedIndex,
                    $totalFeeds,
                    $processed,
                    $totalProducts
                ),
            ]);
        }, 's_products.id');

        // public_base is already set to root URL, no need to update

        return [
            'filenames' => $filenames,
            'manifest' => $this->buildManifestEntry($config, $filenames),
            'is_single_file' => $isSingleFile,
        ];
    }

    /**
     * Build the query used to fetch products for a feed.
     *
     * @param array $config
     * @return Builder
     */
    protected function buildProductQuery(array $config): Builder
    {
        $query = sProduct::query()
            ->select('s_products.*')
            ->active()
            ->orderBy('s_products.id')
            ->with([
                'texts' => function ($relation) use ($config) {
                    $langs = ['base'];
                    if (!empty($config['language'])) {
                        $langs[] = $config['language'];
                    }
                    $relation->whereIn('lang', array_unique($langs));
                },
                'categories',
            ]);

        if (!$config['include_out_of_stock']) {
            $query->where(function ($builder) {
                $builder->where('s_products.availability', sProduct::AVAILABILITY_IN_STOCK)
                    ->orWhere('s_products.inventory', '>', 0);
            });
        }


        return $query;
    }

    /**
     * Write XML chunk to a temporary file.
     *
     * @param string $tmpPath
     * @param iterable $products
     * @param array $config
     * @return void
     */
    protected function writeXmlChunk(string $tmpPath, iterable $products, array $config): void
    {
        $writer = new \XMLWriter();

        if (!$writer->openUri($tmpPath)) {
            throw new \RuntimeException('Unable to open temporary file for writing: ' . $tmpPath);
        }

        $writer->startDocument('1.0', 'UTF-8');
        $writer->setIndent(true);
        $writer->startElement('rss');
        $writer->writeAttribute('version', '2.0');
        $writer->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');

        $writer->startElement('channel');
        $writer->writeElement('title', $config['title']);
        $writer->writeElement('link', $config['domain']);
        $writer->writeElement('description', $config['description']);

        foreach ($products as $product) {
            $this->writeItem($writer, $product, $config);
        }

        $writer->endElement(); // channel
        $writer->endElement(); // rss
        $writer->endDocument();
        $writer->flush();
    }

    /**
     * Write a single product item into XML writer.
     *
     * @param \XMLWriter $writer
     * @param sProduct $product
     * @param array $config
     * @return void
     */
    protected function writeItem(\XMLWriter $writer, sProduct $product, array $config): void
    {
        $translation = $this->resolveTranslation($product, $config['language']);
        $title = $this->sanitizeText($translation['title'] ?? $product->alias, 150);
        $description = $this->sanitizeText(trim($translation['introtext'] ?? '') ?: ($translation['content'] ?? ''), 5000);
        $link = $this->buildProductLink($product->link ?? '', $config['domain']);
        $image = $this->buildProductLink($product->coverSrc ?? '', $config['domain']);
        $price = $this->formatPrice($product->priceToNumber($config['currency']), $config['currency']);
        $salePrice = $product->price_special > 0 && $product->price_special < $product->price_regular
            ? $this->formatPrice($product->specialPriceToNumber($config['currency']), $config['currency'])
            : null;
        $availability = $this->mapAvailability($product->availability);
        $identifier = $this->buildProductId($product);
        $brand = $translation['brand'] ?? evo()->getConfig('site_name', 'Brand');
        $productType = $this->resolveProductType($product);

        $writer->startElement('item');
        $writer->writeElement('g:id', $identifier);
        $writer->writeElement('title', $title);
        $writer->writeElement('description', $description);
        $writer->writeElement('link', $link);
        $writer->writeElement('g:image_link', $image);
        $writer->writeElement('g:availability', $availability);
        $writer->writeElement('g:price', $price);

        if ($salePrice) {
            $writer->writeElement('g:sale_price', $salePrice);
        }

        $writer->writeElement('g:condition', 'new');
        $writer->writeElement('g:brand', $this->sanitizeText($brand, 70));
        $writer->writeElement('g:mpn', $this->sanitizeText($product->sku ?: $identifier, 70));
        $writer->writeElement('g:identifier_exists', $product->sku ? 'true' : 'false');

        if (!empty($config['country'])) {
            $writer->writeElement('g:target_country', $config['country']);
        }

        if ($productType) {
            $writer->writeElement('g:product_type', $productType);
        }

        if (!empty($config['google_product_category'])) {
            $writer->writeElement('g:google_product_category', $config['google_product_category']);
        }

        if ($product->mode === sProduct::MODE_GROUP) {
            $writer->writeElement('g:item_group_id', $identifier);
        }

        $writer->endElement(); // item
    }

    /**
     * Replace temporary file with final feed file.
     *
     * @param string $tmpPath
     * @param string $finalPath
     * @return void
     */
    protected function atomicReplace(string $tmpPath, string $finalPath): void
    {
        if (file_exists($finalPath)) {
            @unlink($finalPath);
        }

        if (!@rename($tmpPath, $finalPath)) {
            @unlink($tmpPath);
            throw new \RuntimeException('Unable to move temporary file to final destination: ' . $finalPath);
        }

        $permissions = octdec(evo()->getConfig('new_file_permissions', '0666'));
        @chmod($finalPath, $permissions);
    }

    /**
     * Remove obsolete feed files from root directory.
     *
     * @param string $directory
     * @param array $keepFiles
     * @return void
     */
    protected function cleanupDirectory(string $directory, array $keepFiles): void
    {
        // Only cleanup if directory exists
        if (!is_dir($directory)) {
            return;
        }

        // Clean up feed.xml and any {slug}.xml files that are not in keepFiles
        $feedFiles = glob($directory . DIRECTORY_SEPARATOR . '*.xml') ?: [];
        $keep = array_map('strval', $keepFiles);

        foreach ($feedFiles as $feedFile) {
            $basename = basename($feedFile);
            // Only remove feed files (feed.xml or files that look like feed slugs)
            // Don't remove other XML files that might be in the directory
            if (preg_match('/^(feed|[a-z0-9-]+)\.xml$/', $basename) && !in_array($basename, $keep, true)) {
                @unlink($feedFile);
            }
        }
    }

    /**
     * Build manifest entry for feed.
     *
     * @param array $config
     * @param array $filenames
     * @return array
     */
    protected function buildManifestEntry(array $config, array $filenames): array
    {
        $files = array_map(function ($file) use ($config) {
            return [
                'filename' => $file,
                'url' => rtrim($config['public_base'], '/') . '/' . $file,
            ];
        }, $filenames);

        return [
            'slug' => $config['slug'],
            'language' => $config['language'],
            'currency' => $config['currency'],
            'country' => $config['country'],
            'domain' => $config['domain'],
            'files' => $files,
            'updated_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Calculate overall progress.
     *
     * @param int $feedIndex
     * @param int $totalFeeds
     * @param int $processed
     * @param int $totalProducts
     * @return int
     */
    protected function calculateProgress(int $feedIndex, int $totalFeeds, int $processed, int $totalProducts): int
    {
        $feedFraction = $totalFeeds > 0 ? ($feedIndex - 1) / $totalFeeds : 0;
        $withinFeed = $totalProducts > 0 ? $processed / $totalProducts : 1;
        $progress = ($feedFraction + ($withinFeed / max($totalFeeds, 1))) * 100;

        return (int)min(99, max(0, round($progress, 0)));
    }

    /**
     * Build feed filename.
     *
     * @param string $slug
     * @param int $chunkIndex
     * @return string
     */
    protected function buildFeedFilename(string $slug, int $chunkIndex): string
    {
        return sprintf('%s-%03d.xml', $slug, $chunkIndex);
    }

    /**
     * Resolve translation data for a product.
     *
     * @param sProduct $product
     * @param string|null $language Language code or null to use 'base'
     * @return array
     */
    protected function resolveTranslation(sProduct $product, ?string $language): array
    {
        $texts = $product->texts ?? collect();

        // If language is null or empty, use 'base' as fallback
        $lang = $language ?: 'base';
        $preferred = $texts->firstWhere('lang', $lang) ?? $texts->firstWhere('lang', 'base');

        if (!$preferred) {
            return [
                'title' => $product->alias,
                'description' => '',
                'content' => '',
            ];
        }

        return [
            'title' => $preferred->pagetitle ?? $product->alias,
            'description' => $preferred->introtext ?? '',
            'content' => $preferred->content ?? '',
        ];
    }

    /**
     * Convert availability code to Google Merchant value.
     *
     * @param int $availability
     * @return string
     */
    protected function mapAvailability(int $availability): string
    {
        return match ($availability) {
            sProduct::AVAILABILITY_IN_STOCK => 'in stock',
            sProduct::AVAILABILITY_ON_ORDER => 'preorder',
            default => 'out of stock',
        };
    }

    /**
     * Build formatted price string.
     *
     * @param float $value
     * @param string $currency
     * @return string
     */
    protected function formatPrice(float $value, string $currency): string
    {
        return number_format($value, 2, '.', '') . ' ' . $currency;
    }

    /**
     * Sanitize text for XML output.
     *
     * @param string|null $text
     * @param int|null $limit
     * @return string
     */
    protected function sanitizeText(?string $text, ?int $limit = null): string
    {
        $clean = strip_tags(html_entity_decode($text ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $clean) ?? '';
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? '';
        $clean = trim($clean);

        if ($limit !== null) {
            $clean = Str::limit($clean, $limit, '');
        }

        return $clean;
    }

    /**
     * Build stable product identifier.
     *
     * @param sProduct $product
     * @return string
     */
    protected function buildProductId(sProduct $product): string
    {
        if (!empty($product->uuid)) {
            return $product->uuid;
        }

        if (!empty($product->sku)) {
            return $product->sku;
        }

        return 'product-' . $product->id;
    }

    /**
     * Build absolute product link for configured domain.
     *
     * @param string $link
     * @param string $domain
     * @return string
     */
    protected function buildProductLink(string $link, string $domain): string
    {
        if (str_starts_with($link, 'http://') || str_starts_with($link, 'https://')) {
            $parsed = parse_url($link);
            $path = $parsed['path'] ?? '';
            $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
            return rtrim($domain, '/') . '/' . ltrim($path, '/') . $query;
        }

        $link = str_replace([EVO_SITE_URL, EVO_CORE_PATH], '|',$link);
        $link = explode('|', $link);
        $link = end($link);
        $link = ltrim($link, '.');
        $link = '/' . ltrim($link, '/');

        return rtrim($domain, '/') . $link;
    }

    /**
     * Retrieve human readable product type based on first category.
     *
     * @param sProduct $product
     * @return string|null
     */
    protected function resolveProductType(sProduct $product): ?string
    {
        if (!$product->relationLoaded('categories')) {
            return null;
        }

        $category = $product->categories->first();
        if (!$category) {
            return null;
        }

        return $this->sanitizeText($category->pagetitle ?? '', 750);
    }

    /**
     * Normalize domain string to include scheme.
     *
     * @param string $domain
     * @return string
     */
    protected function normalizeDomain(string $domain): string
    {
        if (!str_starts_with($domain, 'http://') && !str_starts_with($domain, 'https://')) {
            $domain = 'https://' . ltrim($domain, '/');
        }

        return rtrim($domain, '/');
    }

    /**
     * Load countries from manager language file and map to ISO codes.
     *
     * @return array Array of ISO country codes => country names in admin language
     */
    protected function loadCountriesFromLang(): array
    {
        // Get admin language
        $adminLang = evo()->getConfig('manager_language', 'english');

        // Map country ID to ISO 3166-1 alpha-2 code based on English country name
        $idToIso = $this->getCountryIdToIsoMap();

        // Load country file for admin language
        $countryFile = EVO_BASE_PATH . 'manager/includes/lang/country/' . $adminLang . '_country.inc.php';
        if (!file_exists($countryFile)) {
            // Fallback to English if language file doesn't exist
            $countryFile = EVO_BASE_PATH . 'manager/includes/lang/country/en_country.inc.php';
        }

        $countries = [];
        if (file_exists($countryFile)) {
            $_country_lang = [];
            include $countryFile;

            // Map country ID to ISO code and store with translated name
            foreach ($_country_lang as $id => $name) {
                if (isset($idToIso[$id])) {
                    $isoCode = $idToIso[$id];
                    $countries[$isoCode] = $name;
                }
            }
        }

        // Sort by country name
        asort($countries);

        return $countries;
    }

    /**
     * Get mapping from country ID to ISO 3166-1 alpha-2 code.
     * Based on MODX Evolution country list order.
     *
     * @return array
     */
    protected function getCountryIdToIsoMap(): array
    {
        return [
            '1' => 'AF', // Afghanistan
            '2' => 'AL', // Albania
            '3' => 'DZ', // Algeria
            '4' => 'AS', // American Samoa
            '5' => 'AD', // Andorra
            '6' => 'AO', // Angola
            '7' => 'AI', // Anguilla
            '8' => 'AQ', // Antarctica
            '9' => 'AG', // Antigua and Barbuda
            '10' => 'AR', // Argentina
            '11' => 'AM', // Armenia
            '12' => 'AW', // Aruba
            '13' => 'AU', // Australia
            '14' => 'AT', // Austria
            '15' => 'AZ', // Azerbaijan
            '16' => 'BS', // Bahamas
            '17' => 'BH', // Bahrain
            '18' => 'BD', // Bangladesh
            '19' => 'BB', // Barbados
            '20' => 'BY', // Belarus
            '21' => 'BE', // Belgium
            '22' => 'BZ', // Belize
            '23' => 'BJ', // Benin
            '24' => 'BM', // Bermuda
            '25' => 'BT', // Bhutan
            '26' => 'BO', // Bolivia
            '27' => 'BA', // Bosnia and Herzegowina
            '28' => 'BW', // Botswana
            '29' => 'BV', // Bouvet Island
            '30' => 'BR', // Brazil
            '31' => 'IO', // British Indian Ocean Territory
            '32' => 'BN', // Brunei Darussalam
            '33' => 'BG', // Bulgaria
            '34' => 'BF', // Burkina Faso
            '35' => 'BI', // Burundi
            '36' => 'KH', // Cambodia
            '37' => 'CM', // Cameroon
            '38' => 'CA', // Canada
            '39' => 'CV', // Cape Verde
            '40' => 'KY', // Cayman Islands
            '41' => 'CF', // Central African Republic
            '42' => 'TD', // Chad
            '43' => 'CL', // Chile
            '44' => 'CN', // China
            '45' => 'CX', // Christmas Island
            '46' => 'CC', // Cocos (Keeling) Islands
            '47' => 'CO', // Colombia
            '48' => 'KM', // Comoros
            '49' => 'CG', // Congo
            '50' => 'CK', // Cook Islands
            '51' => 'CR', // Costa Rica
            '52' => 'CI', // Cote D'Ivoire
            '53' => 'HR', // Croatia
            '54' => 'CU', // Cuba
            '55' => 'CY', // Cyprus
            '56' => 'CZ', // Czech Republic
            '57' => 'DK', // Denmark
            '58' => 'DJ', // Djibouti
            '59' => 'DM', // Dominica
            '60' => 'DO', // Dominican Republic
            '61' => 'TL', // East Timor
            '62' => 'EC', // Ecuador
            '63' => 'EG', // Egypt
            '64' => 'SV', // El Salvador
            '65' => 'GQ', // Equatorial Guinea
            '66' => 'ER', // Eritrea
            '67' => 'EE', // Estonia
            '68' => 'ET', // Ethiopia
            '69' => 'FK', // Falkland Islands (Malvinas)
            '70' => 'FO', // Faroe Islands
            '71' => 'FJ', // Fiji
            '72' => 'FI', // Finland
            '73' => 'FR', // France
            '74' => 'FX', // France, Metropolitan
            '75' => 'GF', // French Guiana
            '76' => 'PF', // French Polynesia
            '77' => 'TF', // French Southern Territories
            '78' => 'GA', // Gabon
            '79' => 'GM', // Gambia
            '80' => 'GE', // Georgia
            '81' => 'DE', // Germany
            '82' => 'GH', // Ghana
            '83' => 'GI', // Gibraltar
            '84' => 'GR', // Greece
            '85' => 'GL', // Greenland
            '86' => 'GD', // Grenada
            '87' => 'GP', // Guadeloupe
            '88' => 'GU', // Guam
            '89' => 'GT', // Guatemala
            '90' => 'GN', // Guinea
            '91' => 'GW', // Guinea-bissau
            '92' => 'GY', // Guyana
            '93' => 'HT', // Haiti
            '94' => 'HM', // Heard and Mc Donald Islands
            '95' => 'HN', // Honduras
            '96' => 'HK', // Hong Kong
            '97' => 'HU', // Hungary
            '98' => 'IS', // Iceland
            '99' => 'IN', // India
            '100' => 'ID', // Indonesia
            '101' => 'IR', // Iran (Islamic Republic of)
            '102' => 'IQ', // Iraq
            '103' => 'IE', // Ireland
            '104' => 'IL', // Israel
            '105' => 'IT', // Italy
            '106' => 'JM', // Jamaica
            '107' => 'JP', // Japan
            '108' => 'JO', // Jordan
            '109' => 'KZ', // Kazakhstan
            '110' => 'KE', // Kenya
            '111' => 'KI', // Kiribati
            '112' => 'KP', // Korea, Democratic People's Republic of
            '113' => 'KR', // Korea, Republic of
            '114' => 'KW', // Kuwait
            '115' => 'KG', // Kyrgyzstan
            '116' => 'LA', // Lao People's Democratic Republic
            '117' => 'LV', // Latvia
            '118' => 'LB', // Lebanon
            '119' => 'LS', // Lesotho
            '120' => 'LR', // Liberia
            '121' => 'LY', // Libyan Arab Jamahiriya
            '122' => 'LI', // Liechtenstein
            '123' => 'LT', // Lithuania
            '124' => 'LU', // Luxembourg
            '125' => 'MO', // Macau
            '126' => 'MK', // Macedonia, The Former Yugoslav Republic of
            '127' => 'MG', // Madagascar
            '128' => 'MW', // Malawi
            '129' => 'MY', // Malaysia
            '130' => 'MV', // Maldives
            '131' => 'ML', // Mali
            '132' => 'MT', // Malta
            '133' => 'MH', // Marshall Islands
            '134' => 'MQ', // Martinique
            '135' => 'MR', // Mauritania
            '136' => 'MU', // Mauritius
            '137' => 'YT', // Mayotte
            '138' => 'MX', // Mexico
            '139' => 'FM', // Micronesia, Federated States of
            '140' => 'MD', // Moldova, Republic of
            '141' => 'MC', // Monaco
            '142' => 'MN', // Mongolia
            '143' => 'MS', // Montserrat
            '144' => 'MA', // Morocco
            '145' => 'MZ', // Mozambique
            '146' => 'MM', // Myanmar
            '147' => 'NA', // Namibia
            '148' => 'NR', // Nauru
            '149' => 'NP', // Nepal
            '150' => 'NL', // Netherlands
            '151' => 'AN', // Netherlands Antilles
            '152' => 'NC', // New Caledonia
            '153' => 'NZ', // New Zealand
            '154' => 'NI', // Nicaragua
            '155' => 'NE', // Niger
            '156' => 'NG', // Nigeria
            '157' => 'NU', // Niue
            '158' => 'NF', // Norfolk Island
            '159' => 'MP', // Northern Mariana Islands
            '160' => 'NO', // Norway
            '161' => 'OM', // Oman
            '162' => 'PK', // Pakistan
            '163' => 'PW', // Palau
            '164' => 'PA', // Panama
            '165' => 'PG', // Papua New Guinea
            '166' => 'PY', // Paraguay
            '167' => 'PE', // Peru
            '168' => 'PH', // Philippines
            '169' => 'PN', // Pitcairn
            '170' => 'PL', // Poland
            '171' => 'PT', // Portugal
            '172' => 'PR', // Puerto Rico
            '173' => 'QA', // Qatar
            '174' => 'RE', // Reunion
            '175' => 'RO', // Romania
            '176' => 'RU', // Russian Federation
            '177' => 'RW', // Rwanda
            '178' => 'KN', // Saint Kitts and Nevis
            '179' => 'LC', // Saint Lucia
            '180' => 'VC', // Saint Vincent and the Grenadines
            '181' => 'WS', // Samoa
            '182' => 'SM', // San Marino
            '183' => 'ST', // Sao Tome and Principe
            '184' => 'SA', // Saudi Arabia
            '185' => 'SN', // Senegal
            '186' => 'SC', // Seychelles
            '187' => 'SL', // Sierra Leone
            '188' => 'SG', // Singapore
            '189' => 'SK', // Slovakia (Slovak Republic)
            '190' => 'SI', // Slovenia
            '191' => 'SB', // Solomon Islands
            '192' => 'SO', // Somalia
            '193' => 'ZA', // South Africa
            '194' => 'GS', // South Georgia and the South Sandwich Islands
            '195' => 'ES', // Spain
            '196' => 'LK', // Sri Lanka
            '197' => 'SH', // St. Helena
            '198' => 'PM', // St. Pierre and Miquelon
            '199' => 'SD', // Sudan
            '200' => 'SR', // Suriname
            '201' => 'SJ', // Svalbard and Jan Mayen Islands
            '202' => 'SZ', // Swaziland
            '203' => 'SE', // Sweden
            '204' => 'CH', // Switzerland
            '205' => 'SY', // Syrian Arab Republic
            '206' => 'TW', // Taiwan
            '207' => 'TJ', // Tajikistan
            '208' => 'TZ', // Tanzania, United Republic of
            '209' => 'TH', // Thailand
            '210' => 'TG', // Togo
            '211' => 'TK', // Tokelau
            '212' => 'TO', // Tonga
            '213' => 'TT', // Trinidad and Tobago
            '214' => 'TN', // Tunisia
            '215' => 'TR', // Turkey
            '216' => 'TM', // Turkmenistan
            '217' => 'TC', // Turks and Caicos Islands
            '218' => 'TV', // Tuvalu
            '219' => 'UG', // Uganda
            '220' => 'UA', // Ukraine
            '221' => 'AE', // United Arab Emirates
            '222' => 'GB', // United Kingdom
            '223' => 'US', // United States
            '224' => 'UM', // United States Minor Outlying Islands
            '225' => 'UY', // Uruguay
            '226' => 'UZ', // Uzbekistan
            '227' => 'VU', // Vanuatu
            '228' => 'VA', // Vatican City State (Holy See)
            '229' => 'VE', // Venezuela
            '230' => 'VN', // Viet Nam
            '231' => 'VG', // Virgin Islands (British)
            '232' => 'VI', // Virgin Islands (U.S.)
            '233' => 'WF', // Wallis and Futuna Islands
            '234' => 'EH', // Western Sahara
            '235' => 'YE', // Yemen
            '236' => 'CS', // DEPRECATED: Serbia and Montenegro
            '237' => 'CD', // Congo, Democratic Republic of the
            '238' => 'ZM', // Zambia
            '239' => 'ZW', // Zimbabwe
            '240' => 'RS', // Serbia
            '241' => 'ME', // Montenegro
        ];
    }

    /**
     * Get Google Product Taxonomy categories.
     * Returns array of category ID => category name.
     *
     * @param string $siteKey Optional site key for multisite setups
     * @return array
     */
    protected function getGoogleProductCategories(?string $siteKey = null): array
    {
        $sCommerceController = new sCommerceController();

        $domains = null;
        if (evo()->getConfig('check_sMultisite', false)) {
            $domains = \Seiger\sMultisite\Models\sMultisite::all();
        }

        $listCategories = Cache::rememberForever(
            'sCommerceListCategoriesManager1',
            function () use ($domains, $sCommerceController) {
                if ($domains && count($domains)) {
                    $out = [];
                    foreach ($domains as $domain) {
                        $root = sCommerce::config('basic.catalog_root' . $domain->key, $domain->site_start);
                        $res = $sCommerceController->listCategories($root, 1);
                        foreach ($res as $key => $value) {
                            $out[$domain->key][$key] = $value;
                        }
                    }
                    return $out;
                }

                $root = sCommerce::config('basic.catalog_root', evo()->getConfig('site_start', 1));
                return $sCommerceController->listCategories($root);
            }
        );

        return $listCategories;
    }
}
