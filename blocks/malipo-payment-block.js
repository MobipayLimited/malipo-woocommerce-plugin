const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { createElement } = window.wp.element;
const { __ } = window.wp.i18n;
const { getSetting } = window.wc.wcSettings;

const settings = getSetting('malipo_data', {});

// Ensure Malipo SDK is loaded for WooCommerce Blocks checkout
if (typeof window.Malipo === 'undefined') {
    const script = document.createElement('script');
    script.src = 'https://app.malipo.mw/sdk/v1-malipo-hosted-checkout.js';
    script.async = true;
    script.onload = function() {
        console.log('Malipo SDK loaded for blocks checkout');
    };
    document.head.appendChild(script);
}

// Utility to show/hide a loading spinner overlay
function showMalipoSpinner() {
    let spinner = document.getElementById('malipo-block-spinner');
    if (!spinner) {
        spinner = document.createElement('div');
        spinner.id = 'malipo-block-spinner';
        spinner.style.position = 'fixed';
        spinner.style.top = 0;
        spinner.style.left = 0;
        spinner.style.width = '100vw';
        spinner.style.height = '100vh';
        spinner.style.background = 'rgba(255,255,255,0.7)';
        spinner.style.display = 'flex';
        spinner.style.alignItems = 'center';
        spinner.style.justifyContent = 'center';
        spinner.style.zIndex = 9999;
        spinner.innerHTML = '<div style="border: 6px solid #f3f3f3; border-top: 6px solid #3498db; border-radius: 50%; width: 48px; height: 48px; animation: spin 1s linear infinite;"></div>' +
            '<style>@keyframes spin{0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}</style>';
        document.body.appendChild(spinner);
    }
    spinner.style.display = 'flex';
}
function hideMalipoSpinner() {
    const spinner = document.getElementById('malipo-block-spinner');
    if (spinner) spinner.style.display = 'none';
}

const MalipoPaymentMethod = {
    name: 'malipo',
    label: createElement('div', {
        style: { display: 'flex', alignItems: 'center' }
    }, [
        settings.logo_url ? createElement('img', {
            key: 'logo',
            src: settings.logo_url,
            alt: 'Malipo',
            style: {
                marginRight: '8px',
                height: '24px',
                width: 'auto'
            }
        }) : null,
        settings.title || __('Mobile Money & Cards', 'malipo-woocommerce')
    ]),
    content: createElement('div', {
        style: {
            padding: '16px',
            backgroundColor: '#f8f9fa',
            borderRadius: '6px',
            marginTop: '8px'
        }
    }, settings.description || __('Pay securely using TNM Mpamba, Airtel Money, or your card.', 'malipo-woocommerce')),
    edit: createElement('div', {}, settings.description),
    canMakePayment: () => true,
    ariaLabel: settings.title || __('Mobile Money & Cards', 'malipo-woocommerce'),
    supports: {
        features: settings.supports || ['products']
    },
    createPayment: async (eventData, { onPaymentProcessing }) => {
        showMalipoSpinner();
         const uniqueOrderId = eventData.orderId + '-' + Math.random().toString(36).substr(2, 9) + '-' + Date.now();
        console.log('Malipo createPayment called', eventData, 'Unique order_id:', uniqueOrderId);
        return new Promise((resolve, reject) => {
            if (typeof window.Malipo !== 'undefined') {
                window.Malipo.open({
                    merchantAccount: "settings.merchant_account12",
                    currency: eventData.currency,
                    amount: eventData.total,
                    order_id: uniqueOrderId,
                    description: 'Order #' + eventData.orderId,
                    onSuccess: function(result) {
                        hideMalipoSpinner();
                        resolve({ type: 'success' });
                    },
                    onError: function(error) {
                        hideMalipoSpinner();
                        reject(new Error(error));
                    }
                });
            } else {
                hideMalipoSpinner();
                reject(new Error('Malipo SDK not loaded'));
            }
        });
    }
};

registerPaymentMethod(MalipoPaymentMethod);