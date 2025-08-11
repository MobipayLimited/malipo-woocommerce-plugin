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
