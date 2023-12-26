<?php if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') die("No access");
/**
 * E-commerce management module
 */
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
$data['url'] = $sCommerceController->url;

echo $sCommerceController->view('index', $data);
