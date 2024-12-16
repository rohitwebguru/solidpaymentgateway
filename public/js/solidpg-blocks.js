// Import required modules (if applicable)
const { createElement } = wp.element;
const { decodeEntities } = wp.htmlEntities;

// Get settings for the SolidPG payment method
const solidPGSettings = window.wc.wcSettings.getSetting('solidpg');

// The content displayed when the SolidPG payment method is selected
const SolidPGContent = function () {
    return decodeEntities(solidPGSettings?.description || 'Pay securely using SolidPG Payment Gateway.');
};

// The label/title for the SolidPG payment method
// const SolidPGLabel = function (props) {
//     const PaymentMethodLabel = props.components.PaymentMethodLabel;
//     return PaymentMethodLabel({
//         text: decodeEntities(solidPGSettings?.title || 'SolidPG Payment Gateway')
//     });
// };

const SolidPGLabel = function () {
    return decodeEntities(solidPGSettings?.title || 'SolidPG Payment Gateway');
};

// Register the SolidPG payment method for WooCommerce blocks
const solidPGPaymentMethod = {
    name: 'solidpg', // Unique identifier for the payment method
    label: createElement('span', null, SolidPGLabel()), // Label displayed in the checkout
    content: createElement('div', null, SolidPGContent()), // Content displayed when selected
    edit: createElement('div', null, SolidPGContent()), // Content displayed in the editor
    canMakePayment: function () {
        return true; // Enable the payment method (add any custom logic if needed)
    },
    ariaLabel: SolidPGLabel(), // Accessibility label
    supports: {
        features: ['default', 'products'], // Add necessary features
    },
    savedTokenComponent: null, 
};

// Register the SolidPG payment method in the WooCommerce blocks registry
if (typeof wc !== 'undefined' && wc.wcBlocksRegistry) {
    wc.wcBlocksRegistry.registerPaymentMethod(solidPGPaymentMethod);
    console.log('Registered Payment Methods:', wc.wcBlocksRegistry.getRegisteredPaymentMethods());
}
