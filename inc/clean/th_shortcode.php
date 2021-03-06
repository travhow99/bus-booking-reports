<?php
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// use TH_Order;
require_once(dirname(__FILE__) . "/../TH_Order.php");

add_shortcode('th-manifest-list', 'th_manifest_list');
function th_manifest_list($atts)
{
    $date = isset($_GET['date']) ? strip_tags($_GET['date']) : date('Y-m-d');

    $build = '<div><h2 style="text-align:center;">Manifest for ' . date('m-d-Y', strtotime($date)) . '</h2></div>';

    if (isset($_GET['date'])) {
        $build .= '<div class="aligncenter"><button id="today" class="aligncenter">Today</button></div>';
    }

    $build .= '<div class="mar_b pull-right"><label for="date">Date: </label><br><input id="date" type="date" for="date" value="' . $date . '" /></div>';
    $build .= '<div class="th-bus-manifest-container">';
    $build .= '<div class="th-bus-column">' . th_bus_bookings($date, false, true) . '</div>';
    $build .= '<div class="th-bus-column">' . th_bus_bookings($date, true, true) . '</div>';
    $build .= '</div>';

    $build .= th_add_manifest_scripts();

    return $build;
}

/**
 * TH Custom Shortcodes
 */
add_shortcode('th_bus_order_form', 'th_bus_order_form');
function th_bus_order_form()
{
    // var_dump(function_exists('TH_Order::gatherDropdownData'));
    // $html = TH_Order::addCustomCheckoutStyles();

    $html = TH_Order::addCustomCheckoutForm();
    // var_dump(function_exists('th_add_orders_scripts'));
    $html .= TH_Order::addCustomCheckoutScripts();

    $html .= do_shortcode('[woocommerce_checkout]');

    return $html;

    // return TH_Order::gatherDropdownData();
}
