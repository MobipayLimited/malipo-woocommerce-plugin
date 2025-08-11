jQuery(document).ready(function($) {
    // Enhanced checkout support for blocks and classic
    console.log('Malipo checkout script loaded');
    
    // Support for WooCommerce Blocks checkout
    if (typeof wp !== 'undefined' && wp.hooks) {
        // Add any block-specific functionality here
        wp.hooks.addAction('woocommerce_blocks_checkout_submit', 'malipo', function() {
            console.log('Malipo: Block checkout processing');
        });
    }
    
    // Classic checkout enhancements
    $(document.body).on('updated_checkout', function() {
        console.log('Malipo: Classic checkout updated');
    });
});