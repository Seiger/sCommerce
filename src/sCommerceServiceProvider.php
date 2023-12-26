<?php namespace Seiger\sCommerce;

use EvolutionCMS\ServiceProvider;

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
            include(__DIR__.'/Http/routes.php');

            // Migration for create tables
            $this->loadMigrationsFrom(dirname(__DIR__) . '/database/migrations');

            // Views
            $this->loadViewsFrom(dirname(__DIR__) . '/views', 'sCommerce');

            // MultiLang
            $this->loadTranslationsFrom(dirname(__DIR__) . '/lang', 'sCommerce');

            // Files
            $this->publishes([
                dirname(__DIR__) . '/config/sCommerceAlias.php' => config_path('app/aliases/sCommerce.php', true),
                dirname(__DIR__) . '/config/sCommerceSettings.php' => config_path('seiger/settings/sCommerce.php', true),
                dirname(__DIR__) . '/images/noimage.png' => public_path('assets/site/noimage.png'),
                dirname(__DIR__) . '/images/seigerit-blue.svg' => public_path('assets/site/seigerit-blue.svg'),
            ]);
        }

        // Check sCommerce
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/sCommerceCheck.php', 'cms.settings');

        // Class alias
        $this->app->singleton(sCommerce::class);
        $this->app->alias(sCommerce::class, 'sCommerce');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Add plugins to Evo
        $this->loadPluginsFrom(dirname(__DIR__) . '/plugins/');

        // Only Manager
        if (IN_MANAGER_MODE) {
            // Add module to Evo. Module ID is md5('sCommerce').
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
}