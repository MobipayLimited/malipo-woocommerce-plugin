<?php
/**
 * Plugin Name: Malipo WooCommerce Gateway
 * Plugin URI: https://malipo.mw
 * Description: Accept payments through Malipo's hosted checkout (TNM Mpamba, Airtel Money, Cards). Supports modern WooCommerce blocks.
 * Version: 1.0.1
 * Author: Your Name
 * Author URI: https://malipo.mw
 * Text Domain: malipo-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.4
 * WC requires at least: 6.0
 * WC tested up to: 8.5
 * Requires PHP: 7.4
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

add_action('plugins_loaded', 'malipo_init_gateway_class');

function malipo_init_gateway_class() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once plugin_dir_path(__FILE__) . 'includes/class-malipo-gateway.php';

    add_filter('woocommerce_payment_gateways', 'malipo_add_gateway_class');
    add_action('woocommerce_blocks_loaded', 'malipo_register_payment_method_type');
}

function malipo_register_payment_method_type() {
    if (!class_exists('Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType')) {
        return;
    }

    class Malipo_Blocks_Support extends Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {
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
                'title'            => isset($this->settings['title']) ? $this->settings['title'] : 'Mobile Money & Cards',
                'description'      => isset($this->settings['description']) ? $this->settings['description'] : 'Pay securely using TNM Mpamba, Airtel Money, or your card.',
                'supports'         => $this->get_supported_features(),
                'logo_url'         => plugin_dir_url(__FILE__) . 'assets/images/malipo-icon.svg',
                'merchant_account' => isset($this->settings['merchant_account']) ? $this->settings['merchant_account'] : ''
            );
        }
    }

    add_action('woocommerce_blocks_payment_method_type_registration', function ($payment_method_registry) {
        $payment_method_registry->register(new Malipo_Blocks_Support());
    });
}

function malipo_add_gateway_class($gateways) {
    $gateways[] = 'WC_Malipo_Gateway';
    return $gateways;
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'malipo_plugin_action_links');

function malipo_plugin_action_links($links) {
    $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=malipo">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}

add_action('woocommerce_thankyou_malipo', 'malipo_custom_thankyou_page', 10, 1);
function malipo_custom_thankyou_page($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    $malipo_txn_id = $order->get_meta('_malipo_txn_id');
    $logo_url = plugin_dir_url(__FILE__) . 'assets/images/malipo-logo.png';
    ?>
    <div class="malipo-thankyou" style="max-width:600px;margin:40px auto;padding:32px;background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.07);text-align:center;">
        <img src="<?php echo esc_url($logo_url); ?>" alt="Malipo" style="height:60px;margin-bottom:24px;">
        <h2 style="color:#1d4ed8;">Thank you for your payment!</h2>
        <p style="font-size:18px;margin:16px 0;">Your payment was processed successfully via <strong>Malipo</strong>.</p>
        <div style="margin:32px 0 16px 0;">
            <strong>Order Number:</strong> <span style="font-size:20px;color:#222;letter-spacing:1px;"> <?php echo esc_html($malipo_txn_id ? $malipo_txn_id : $order->get_order_number()); ?> </span><br>
            <strong>Date:</strong> <?php echo esc_html(wc_format_datetime($order->get_date_created())); ?><br>
            <strong>Total:</strong> <?php echo wp_kses_post($order->get_formatted_order_total()); ?><br>
            <strong>Payment Method:</strong> Mobile Money & Cards
        </div>
        <div style="margin-top:32px;">
            <a href="<?php echo esc_url(home_url()); ?>" style="background:#1d4ed8;color:#fff;padding:12px 32px;border-radius:6px;text-decoration:none;font-weight:600;">Return to Home</a>
        </div>
    </div>
    <?php
}

// Admin page for Malipo transactions
add_action('admin_menu', function() {
    add_submenu_page(
        'woocommerce',
        'Malipo Transactions',
        'Malipo Transactions',
        'manage_woocommerce',
        'malipo-transactions',
        'malipo_transactions_admin_page'
    );
});

function malipo_transactions_admin_page() {
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
    echo '<table class="widefat fixed striped"><thead><tr><th>Order #</th><th>Malipo Txn ID</th><th>Status</th><th>Total</th><th>Date</th></tr></thead><tbody>';
    foreach ($orders as $order) {
        $malipo_txn_id = $order->get_meta('_malipo_txn_id');
        $status = wc_get_order_status_name($order->get_status());
        $amount = $order->get_total();
        $total += ($order->get_status() === 'processing' || $order->get_status() === 'completed') ? $amount : 0;
        echo '<tr>';
        echo '<td><a href="' . esc_url(get_edit_post_link($order->get_id())) . '">' . esc_html($order->get_id()) . '</a></td>';
        echo '<td>' . esc_html($malipo_txn_id) . '</td>';
        echo '<td>' . esc_html($status) . '</td>';
        echo '<td>' . wc_price($amount) . '</td>';
        echo '<td>' . esc_html(wc_format_datetime($order->get_date_created())) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '<h3 style="margin-top:32px;">Total Completed/Processing: ' . wc_price($total) . '</h3>';
    echo '</div>';
}
