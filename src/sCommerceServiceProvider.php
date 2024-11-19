<?php namespace Seiger\sCommerce;

use EvolutionCMS\ServiceProvider;
use Seiger\sCommerce\Facades\sCart as sCartFacade;
use Seiger\sCommerce\sCart;

/**
 * Class sCommerceServiceProvider
 *
 * Registers and initializes services for sCommerce.
 */
class sCommerceServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // Only Manager
        if (IN_MANAGER_MODE) {
            // Add custom routes for package
            $this->loadRoutes();

            // Migration for create tables
            $this->loadMigrations();

            // Views
            $this->loadViews();

            // MultiLang
            $this->loadTranslations();

            // Files
            $this->publishFiles();
        }

        // Check sCommerce configuration
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/sCommerceCheck.php', 'cms.settings');

        // Register sCommerce class as a singleton
        $this->app->singleton(sCommerce::class);
        $this->app->alias(sCommerce::class, 'sCommerce');

        // Register the sCart class as a singleton
        $this->app->singleton('sCart', fn($app) => sCart::getInstance());
        class_alias(sCartFacade::class, 'sCart');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Add plugins to Evo
        $this->loadPlugins();

        // Only Manager
        if (IN_MANAGER_MODE) {
            // Add module to Evo
            $this->registerModule();
        }
    }

    /**
     * Load custom routes.
     *
     * @return void
     */
    protected function loadRoutes()
    {
        include(__DIR__.'/Http/routes.php');
    }

    /**
     * Load migrations.
     *
     * @return void
     */
    protected function loadMigrations()
    {
        $this->loadMigrationsFrom(dirname(__DIR__) . '/database/migrations');
    }

    /**
     * Load views.
     *
     * @return void
     */
    protected function loadViews()
    {
        $this->loadViewsFrom(dirname(__DIR__) . '/views', 'sCommerce');
    }

    /**
     * Load translations.
     *
     * @return void
     */
    protected function loadTranslations()
    {
        $this->loadTranslationsFrom(dirname(__DIR__) . '/lang', 'sCommerce');
    }

    /**
     * Publish files.
     *
     * @return void
     */
    protected function publishFiles()
    {
        $this->publishes([
            dirname(dirname(__DIR__)) . '/sgallery/config/sGallerySettings.php' => config_path('seiger/settings/sGallery.php', true),
            dirname(dirname(__DIR__)) . '/sgallery/images/youtube-logo.png' => public_path('assets/site/youtube-logo.png'),
            dirname(__DIR__) . '/config/sCommerceAlias.php' => config_path('app/aliases/sCommerce.php', true),
            dirname(__DIR__) . '/config/sCommerceSettings.php' => config_path('seiger/settings/sCommerce.php', true),
            dirname(__DIR__) . '/config/sCommerceCurrenciesSettings.php' => config_path('seiger/settings/sCommerceCurrencies.php', true),
            dirname(__DIR__) . '/images/noimage.png' => public_path('assets/images/noimage.png'),
            dirname(__DIR__) . '/images/scommerce.ico' => public_path('assets/site/scommerce.ico'),
            dirname(__DIR__) . '/images/seigerit-blue.svg' => public_path('assets/site/seigerit-blue.svg'),
            dirname(__DIR__) . '/views/s_commerce_product.blade.php' => public_path('views/s_commerce_product.blade.php'),
            dirname(__DIR__) . '/builder/accordion/config.php' => public_path('assets/modules/scommerce/builder/accordion/config.php'),
            dirname(__DIR__) . '/builder/accordion/render.blade.php' => public_path('assets/modules/scommerce/builder/accordion/render.blade.php'),
            dirname(__DIR__) . '/builder/accordion/template.blade.php' => public_path('assets/modules/scommerce/builder/accordion/template.blade.php'),
            dirname(__DIR__) . '/builder/richtext/config.php' => public_path('assets/modules/scommerce/builder/richtext/config.php'),
            dirname(__DIR__) . '/builder/richtext/render.blade.php' => public_path('assets/modules/scommerce/builder/richtext/render.blade.php'),
            dirname(__DIR__) . '/builder/richtext/template.blade.php' => public_path('assets/modules/scommerce/builder/richtext/template.blade.php'),
        ]);
    }

    /**
     * Load plugins from the specified directory.
     *
     * @return void
     */
    protected function loadPlugins()
    {
        $this->loadPluginsFrom(dirname(__DIR__) . '/plugins/');
    }

    /**
     * Register the sCommerce module.
     *
     * @return void
     */
    protected function registerModule()
    {
        $lang = 'en';
        if (isset($_SESSION['mgrUsrConfigSet']['manager_language'])) {
            $lang = $_SESSION['mgrUsrConfigSet']['manager_language'];
        } else {
            if (is_file(evo()->getSiteCacheFilePath())) {
                $siteCache = file_get_contents(evo()->getSiteCacheFilePath());
                preg_match('@\$c\[\'manager_language\'\]="\w+@i', $siteCache, $matches);
                if (count($matches)) {
                    $lang = str_replace('$c[\'manager_language\']="', '', $matches[0]);
                }
            }
        }
        $lang = include_once dirname(__DIR__) . '/lang/' . $lang . '/global.php';
        $this->app->registerModule($lang['title'], dirname(__DIR__) . '/module/sCommerceModule.php', $lang['icon']);
    }
}
