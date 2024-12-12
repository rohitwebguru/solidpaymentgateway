const { decodeEntities } = wp.htmlEntities;

// Get settings for the SolidPG payment method
const solidPGSettings = window.wc.wcSettings.getSetting('solidpg');

// The content displayed when the SolidPG payment method is selected
const SolidPGContent = () => {
    return decodeEntities(solidPGSettings?.description || 'Pay securely using SolidPG Payment Gateway.');
};

// The label/title for the SolidPG payment method
const SolidPGLabel = (props) => {
    const { PaymentMethodLabel } = props.components;
    return (
        <PaymentMethodLabel text={decodeEntities(solidPGSettings?.title || 'SolidPG Payment Gateway')} />
    );
};

// Register the SolidPG payment method for WooCommerce blocks
const solidPGPaymentMethod = {
    name: 'solidpg', // Unique identifier for the payment method
    label: <SolidPGLabel />, // Label displayed in the checkout
    content: <SolidPGContent />, // Content displayed when selected
    edit: <SolidPGContent />, // Content displayed in the editor
    canMakePayment: () => true, // Enable the payment method (add any custom logic if needed)
    supports: {
        features: solidPGSettings?.supports ?? [], // Supported features (e.g., refunds)
    },
};

// Register the SolidPG payment method in the WooCommerce blocks registry
if (typeof wc !== 'undefined' && wc.blocksRegistry) {
    wc.blocksRegistry.registerPaymentMethod(solidPGPaymentMethod);
}


