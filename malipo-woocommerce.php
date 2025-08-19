<?php
/**
 * Plugin Name: Malipo Gateway for WooCommerce
 * Plugin URI: https://malipo.mw/plugins/malipo-woocommerce
 * Description: Accept payments through Malipo's hosted checkout (TNM Mpamba, Airtel Money, Bank Cards). Supports modern WooCommerce blocks.
 * Version: 1.0.1
 * Author: Mobipay
 * Author URI: https://mobipay.mw
 * Text Domain: malipo-gateway-for-woocommerce
 * Requires at least: 5.8
 * Tested up to: 6.4
 * WC requires at least: 6.0
 * WC tested up to: 8.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('before_woocommerce_init', function () {
    if (class_exists('\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, false);
    }
});

add_action('plugins_loaded', 'MALIGAFO_init_gateway_class');

function MALIGAFO_init_gateway_class() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once plugin_dir_path(__FILE__) . 'includes/class-malipo-gateway.php';

    add_filter('woocommerce_payment_gateways', 'MALIGAFO_add_gateway_class');
    add_action('woocommerce_blocks_loaded', 'MALIGAFO_register_payment_method_type');
}

function MALIGAFO_register_payment_method_type() {
    if (!class_exists('Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType')) {
        return;
    }

    class MALIGAFO_Blocks_Support extends Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {
        protected $name = 'malipo';

        public function initialize() {
            $this->settings = get_option('woocommerce_malipo_settings', array());
        }

        public function is_active() {
            $payment_gateways_class = WC()->payment_gateways();
            $payment_gateways       = $payment_gateways_class->payment_gateways();
            return isset($payment_gateways['malipo']) && $payment_gateways['malipo']->is_available();
        }

        public function get_payment_method_script_handles() {
            wp_register_script(
                'malipo-blocks-integration',
                plugin_dir_url(__FILE__) . 'blocks/malipo-payment-block.js',
                array('wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n'),
                '1.0.1',
                true
            );
            return array('malipo-blocks-integration');
        }

        public function get_payment_method_data() {
            return array(
                'title'            => isset($this->settings['title']) ? $this->settings['title'] : 'Mobile Money and Cards',
                'description'      => isset($this->settings['description']) ? $this->settings['description'] : 'Pay securely using TNM Mpamba, Airtel Money, or your Bank Card.',
                'supports'         => $this->get_supported_features(),
                'logo_url'         => plugin_dir_url(__FILE__) . 'assets/images/malipo-icon.svg',
                'merchant_account' => isset($this->settings['merchant_account']) ? $this->settings['merchant_account'] : ''
            );
        }
    }

    add_action('woocommerce_blocks_payment_method_type_registration', function ($payment_method_registry) {
        $payment_method_registry->register(new MALIGAFO_Blocks_Support());
    });
}

function MALIGAFO_add_gateway_class($gateways) {
    $gateways[] = 'WC_Malipo_Gateway';
    return $gateways;
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'MALIGAFO_plugin_action_links');

function MALIGAFO_plugin_action_links($links) {
    $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=malipo">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// Helper to get the public IPN endpoint for Malipo (for merchant dashboard)
function MALIGAFO_get_public_ipn_url() {
    return home_url('/wp-json/malipo/v1/ipn');
}

// Admin page for Malipo transactions
add_action('admin_menu', function() {
    add_submenu_page(
        'woocommerce',
        'Malipo Transactions',
        'Malipo Transactions',
        'manage_woocommerce',
        'malipo-transactions',
        'MALIGAFO_transactions_admin_page'
    );
});

function MALIGAFO_transactions_admin_page() {
    if (!current_user_can('manage_woocommerce')) return;
    echo '<div class="wrap"><h1>Malipo Transactions</h1>';
    $args = array(
        'limit' => 50,
        'payment_method' => 'malipo',
        'orderby' => 'date',
        'order' => 'DESC',
    );
    $orders = wc_get_orders($args);
    $total = 0;
    echo '<table class="widefat fixed striped"><thead><tr><th>Order #</th><th>Txn ID</th><th>Status</th><th>Total</th><th>Product(s)</th><th>Qty</th><th>Date</th></tr></thead><tbody>';
    foreach ($orders as $order) {
        $malipo_txn_id = $order->get_meta('_malipo_txn_id');
        $status = wc_get_order_status_name($order->get_status());
        $amount = $order->get_total();
        $total += ($order->get_status() === 'processing' || $order->get_status() === 'completed') ? $amount : 0;
        $products = array();
        $qty = 0;
        foreach ($order->get_items() as $item) {
            $products[] = $item->get_name();
            $qty += $item->get_quantity();
        }
        echo '<tr>';
        echo '<td><a href="' . esc_url(get_edit_post_link($order->get_id())) . '">' . esc_html($order->get_id()) . '</a></td>';
        echo '<td>' . esc_html($malipo_txn_id) . '</td>';
        echo '<td>' . esc_html($status) . '</td>';
        echo '<td>' . wc_price($amount) . '</td>';
        echo '<td>' . esc_html(implode(', ', $products)) . '</td>';
        echo '<td>' . esc_html($qty) . '</td>';
        echo '<td>' . esc_html(wc_format_datetime($order->get_date_created())) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '<h3 style="margin-top:32px;">Total Completed/Processing: ' . wc_price($total) . '</h3>';
    echo '</div>';
}

add_action('rest_api_init', function () {
    register_rest_route('malipo/v1', '/ipn', array(
        'methods'  => 'POST',
        'callback' => 'MALIGAFO_ipn_callback',
        'permission_callback' => '__return_true',  
    ));
});

function MALIGAFO_ipn_callback($request) {
    $params = $request->get_json_params();
    $merchant_txn_id = isset($params['merchant_txn_id']) ? sanitize_text_field($merchant_txn_id) : '';
    $status = isset($params['status']) ? sanitize_text_field($params['status']) : '';
    $transaction_id = isset($params['transaction_id']) ? sanitize_text_field($params['transaction_id']) : '';
    $customer_ref = isset($params['customer_ref']) ? sanitize_text_field($params['customer_ref']) : '';
    $amount = isset($params['amount']) ? floatval($params['amount']) : 0;

    if (!$merchant_txn_id) {
        return new WP_REST_Response(['error' => 'Missing merchant_txn_id'], 400);
    }

    $orders = wc_get_orders([
        'meta_key' => '_malipo_txn_id',
        'meta_value' => $merchant_txn_id,
        'limit' => 1
    ]);
    if (empty($orders)) {
        return new WP_REST_Response(['error' => 'Order not found'], 404);
    }
    $order = $orders[0];

    if ($status === 'Completed') {
        $order->payment_complete($transaction_id);
        $order->add_order_note('Malipo payment completed via IPN. Customer Ref: ' . $customer_ref);
    } elseif ($status === 'Failed') {
        $order->update_status('failed', 'Malipo payment failed via IPN. Customer Ref: ' . $customer_ref);
    } else {
        $order->add_order_note('Malipo IPN received with unknown status: ' . $status);
    }

    $order->update_meta_data('_malipo_transaction_id', $transaction_id);
    $order->update_meta_data('_malipo_customer_ref', $customer_ref);
    $order->update_meta_data('_malipo_ipn_amount', $amount);
    $order->save();

    return new WP_REST_Response(array('success' => true), 200);
}
