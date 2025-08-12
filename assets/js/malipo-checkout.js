jQuery(document).ready(function($) {
     console.log('Malipo checkout script loaded');
    
    // Support for WooCommerce Blocks checkout
    if (typeof wp !== 'undefined' && wp.hooks) {
         wp.hooks.addAction('woocommerce_blocks_checkout_submit', 'malipo', function() {
            console.log('Malipo: Block checkout processing');
        });
    }
    
     $(document.body).on('updated_checkout', function() {
        console.log('Malipo: Classic checkout updated');
    });
});