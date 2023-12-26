<?php
/**
 * E-commerce management module
 */

use Seiger\sCommerce\Controllers\sCommerceController;
use Seiger\sCommerce\Facades\sCommerce;

if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') die("No access");

$sCommerceController = new sCommerceController();
$editor = '';
$tabs = [];
$get = request()->get ?? "orders";

switch ($get) {
    default:
        break;
}

$data['sCommerceController'] = $sCommerceController;
$data['editor'] = $editor;
$data['tabs'] = $tabs;
$data['get'] = $get;
$data['moduleUrl'] = sCommerce::moduleUrl();

echo $sCommerceController->view('index', $data);
