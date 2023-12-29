<?php
/**
 * E-commerce management module
 */

use Seiger\sCommerce\Controllers\sCommerceController;
use Seiger\sCommerce\Facades\sCommerce;

if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') die("No access");
if (!file_exists(EVO_CORE_PATH . 'custom/config/seiger/settings/sCommerce.php')) {
    evo()->webAlertAndQuit(__('sCommerce::global.finish_configuring'), "index.php?a=2");
}

$sCommerceController = new sCommerceController();
$get = request()->get ?? "orders";
$editor = '';
$tabs = ['products'];
if (evo()->hasPermission('settings')) {
    $tabs[] = 'settings';
}

switch ($get) {
    default:
    case "orders":
        break;
    case "settings":
        if (!evo()->hasPermission('settings')) {
            $back = request()->back ?? '&get=orders';
            return header('Location: ' . sCommerce::moduleUrl() . $back);
        }
        break;
    case "settingsSave":
        $sCommerceController->saveBasicConfigs();

        evo()->clearCache('full');
        sleep(5);

        session()->flash('success', __('sCommerce::global.settings_save_success'));
        $back = request()->back ?? '&get=settings';
        return header('Location: ' . sCommerce::moduleUrl() . $back);
}

$data['sCommerceController'] = $sCommerceController;
$data['editor'] = $editor;
$data['tabs'] = $tabs;
$data['get'] = $get;
$data['moduleUrl'] = sCommerce::moduleUrl();

echo $sCommerceController->view('index', $data);
