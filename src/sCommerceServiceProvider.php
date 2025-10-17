<?php namespace Seiger\sCommerce;

use EvolutionCMS\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Seiger\sCommerce\Cart\sCart;
use Seiger\sCommerce\Checkout\sCheckout;

/**
 * sCommerceServiceProvider - Service Provider for sCommerce package
 *
 * This service provider registers and initializes all sCommerce services,
 * including models, controllers, console commands, and integrations.
 * It handles both manager and frontend functionality for the sCommerce
 * e-commerce solution in EvolutionCMS.
 *
 * @package Seiger\sCommerce
 * @author Seiger IT Team
 * @since 1.0.0
 */
class sCommerceServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * This method is called after all service providers have been registered.
     * It sets up routes, views, translations, migrations, and registers
     * core sCommerce services as singletons in the application container.
     *
     * @return void
     */
    public function boot()
    {
        // Only Manager
        if (IN_MANAGER_MODE) {
            // Migration for create tables
            $this->loadMigrations();

            // Files
            $this->publishFiles();
        }

        // Add custom routes for package
        $this->loadRoutes();

        // Views
        $this->loadViews();

        // MultiLang
        $this->loadTranslations();

        // Check sCommerce configuration
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/sCommerceCheck.php', 'cms.settings');

        // Register sCommerce class as a singleton
        $this->app->singleton(sCommerce::class);
        $this->app->alias(sCommerce::class, 'sCommerce');

        // Register the sFilter class as a singleton
        $this->app->singleton(sFilter::class);
        $this->app->alias(sFilter::class, 'sFilter');

        // Register the sCart class as a singleton
        $this->app->singleton(sCart::class);
        $this->app->alias(sCart::class, 'sCart');

        // Register the sCheckout class as a singleton
        $this->app->singleton(sCheckout::class);
        $this->app->alias(sCheckout::class, 'sCheckout');

        // Register the sWishlist class as a singleton
        $this->app->singleton(sWishlist::class);
        $this->app->alias(sWishlist::class, 'sWishlist');
    }

    /**
     * Register the service provider.
     *
     * This method is called first and registers plugins, modules,
     * logging configuration, and console commands. It also sets up
     * the sCommerce module in the EvolutionCMS manager interface.
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

        // Log
        $channels = $this->app['config']->get('logging.channels', []);
        if (!isset($channels['scommerce'])) {
            $this->app['config']->set('logging.channels.scommerce', [
                'driver' => 'daily',
                'name' => env('APP_NAME', 'evo'),
                'path' => EVO_STORAGE_PATH . 'logs/scommerce.log',
                'level' => env('LOG_LEVEL', 'debug'),
                'days' => env('LOG_DAILY_DAYS', 14),
                'replace_placeholders' => true,
            ]);
        }
    }

    /**
     * Load custom routes for the sCommerce package.
     *
     * Includes route definitions from Http/routes.php file,
     * which contains API endpoints and web routes for sCommerce functionality.
     *
     * @return void
     */
    protected function loadRoutes()
    {
        include(__DIR__.'/Http/routes.php');
    }

    /**
     * Load database migrations for sCommerce.
     *
     * Loads migration files from the database/migrations directory
     * to create necessary database tables for sCommerce functionality.
     * Only executed in manager mode.
     *
     * @return void
     */
    protected function loadMigrations()
    {
        $this->loadMigrationsFrom(dirname(__DIR__) . '/database/migrations');
    }

    /**
     * Load Blade view templates for sCommerce.
     *
     * Registers the views directory with the sCommerce namespace,
     * making view templates available for rendering in the application.
     *
     * @return void
     */
    protected function loadViews()
    {
        $this->loadViewsFrom(dirname(__DIR__) . '/views', 'sCommerce');
    }

    /**
     * Load language translations for sCommerce.
     *
     * Registers the lang directory with the sCommerce namespace,
     * making translation files available for internationalization.
     *
     * @return void
     */
    protected function loadTranslations()
    {
        $this->loadTranslationsFrom(dirname(__DIR__) . '/lang', 'sCommerce');
    }

    /**
     * Publish configuration and asset files.
     *
     * Defines files to be published when the package is installed,
     * including configuration files, images, and builder templates.
     * Only executed in manager mode.
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
            dirname(__DIR__) . '/images/noimage.png' => public_path('assets/site/noimage.png'),
            dirname(__DIR__) . '/images/scommerce.ico' => public_path('assets/site/scommerce.ico'),
            dirname(__DIR__) . '/images/seigerit-blue.svg' => public_path('assets/site/seigerit-blue.svg'),
            dirname(__DIR__) . '/views/s_commerce_product.blade.php' => public_path('views/s_commerce_product.blade.php'),
            dirname(__DIR__) . '/builder/accordion/config.php' => public_path('assets/modules/scommerce/builder/accordion/config.php'),
            dirname(__DIR__) . '/builder/accordion/render.blade.php' => public_path('assets/modules/scommerce/builder/accordion/render.blade.php'),
            dirname(__DIR__) . '/builder/accordion/template.blade.php' => public_path('assets/modules/scommerce/builder/accordion/template.blade.php'),
            dirname(__DIR__) . '/builder/richtext/config.php' => public_path('assets/modules/scommerce/builder/richtext/config.php'),
            dirname(__DIR__) . '/builder/richtext/render.blade.php' => public_path('assets/modules/scommerce/builder/richtext/render.blade.php'),
            dirname(__DIR__) . '/builder/richtext/template.blade.php' => public_path('assets/modules/scommerce/builder/richtext/template.blade.php'),
        ], 'scommerce');
    }

    /**
     * Load EvolutionCMS plugins for sCommerce.
     *
     * Loads PHP plugins from the plugins directory that extend
     * EvolutionCMS functionality with sCommerce-specific features.
     *
     * @return void
     */
    protected function loadPlugins()
    {
        $this->loadPluginsFrom(dirname(__DIR__) . '/plugins/');
    }

    /**
     * Register the sCommerce module in EvolutionCMS manager.
     *
     * Registers the sCommerce module with the EvolutionCMS manager interface,
     * including title, icon, and language-specific configuration.
     * Only executed in manager mode.
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
