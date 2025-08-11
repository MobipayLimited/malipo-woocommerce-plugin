<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Malipo_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'malipo';
        $this->icon               = plugin_dir_url(__FILE__) . '../assets/images/malipo-icon.svg';
        $this->has_fields         = false;
        $this->method_title       = 'Malipo';
        $this->method_description = 'Accept payments via Malipo (TNM Mpamba, Airtel Money, Cards). Supports WooCommerce blocks.';

        $this->supports = array(
            'products',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'multiple_subscriptions',
            'refunds'
        );

        $this->init_form_fields();
        $this->init_settings();

        $this->title            = $this->get_option('title');
        $this->description      = $this->get_option('description');
        $this->enabled          = $this->get_option('enabled');
        $this->merchant_account = $this->get_option('merchant_account');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable Malipo Payment',
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'Payment method title that customers see',
                'default'     => 'Mobile Money & Cards',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'Payment method description that customers see',
                'default'     => 'Pay securely using TNM Mpamba, Airtel Money, or your card.',
            ),
            'merchant_account' => array(
                'title'       => 'Merchant Account ID',
                'type'        => 'text',
                'description' => 'Your Malipo Merchant Account ID from your dashboard',
                'default'     => '',
                'desc_tip'    => true,
            )
        );
    }

    public function payment_scripts() {
        if (!is_checkout() && !is_checkout_pay_page()) {
            return;
        }

        wp_enqueue_style('malipo-styles', plugin_dir_url(__FILE__) . '../assets/css/malipo-styles.css', [], '1.0.1');
        wp_enqueue_script('malipo-sdk', 'https://app.malipo.mw/sdk/v1-malipo-hosted-checkout.js', array(), null, true);
        wp_enqueue_script('malipo-checkout', plugin_dir_url(__FILE__) . '../assets/js/malipo-checkout.js', array('jquery', 'malipo-sdk'), '1.0.1', true);

        wp_localize_script('malipo-checkout', 'malipo_params', array(
            'merchant_account' => $this->merchant_account,
            'currency'         => get_woocommerce_currency(),
            'nonce'            => wp_create_nonce('malipo_payment_nonce')
        ));
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        if (empty($this->merchant_account)) {
            wc_add_notice('Payment error: Merchant account not configured', 'error');
            return array('result' => 'failure');
        }

         $malipo_txn_id = $order_id . '-' . uniqid();
         $order->update_meta_data('_malipo_txn_id', $malipo_txn_id);
        $order->save();

        $order->update_status('pending', __('Awaiting Malipo payment', 'malipo-woocommerce'));

        return array(
            'result'   => 'success',
            'redirect' => add_query_arg('malipo_txn_id', $malipo_txn_id, $order->get_checkout_payment_url(true))
        );
    }

    public function receipt_page($order_id) {
        $order = wc_get_order($order_id);
         $malipo_txn_id = $order->get_meta('_malipo_txn_id');
        ?>
        <div id="malipo-payment-container">
            <h3>Processing your payment...</h3>
            <div id="malipo-loading">Please complete your payment in the popup window.</div>
        </div>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            if (typeof window.Malipo !== 'undefined') {
                window.Malipo.open({
                    merchantAccount: "<?php echo esc_js($this->merchant_account); ?>",
                    currency: "<?php echo esc_js(get_woocommerce_currency()); ?>",
                    amount: "<?php echo esc_js($order->get_total()); ?>",
                    order_id: "<?php echo esc_js($malipo_txn_id); ?>",
                    description: "Order #<?php echo esc_js($order_id); ?>",
                    onSuccess: function() {
                         window.location.href = "<?php echo esc_url($order->get_checkout_order_received_url()); ?>";
                    },
                    onError: function(error) {
                        alert('Payment failed: ' + error);
                        window.location.href = "<?php echo esc_url($order->get_cancel_order_url()); ?>";
                    }
                });
            } else {
                alert('Payment system not available. Please try again.');
                window.location.href = "<?php echo esc_url($order->get_cancel_order_url()); ?>";
            }
        });
        </script>
        <?php
    }
}