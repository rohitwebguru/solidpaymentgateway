// Import required modules (if applicable)
const { createElement } = wp.element;
const { decodeEntities } = wp.htmlEntities;

// Get settings for the SolidPG payment method
const solidPGSettings = window.wc.wcSettings.getSetting('solidpg');

// The content displayed when the SolidPG payment method is selected
const SolidPGContent = function () {
    return decodeEntities(solidPGSettings?.description || 'Pay securely using SolidPG Payment Gateway.');
};

const SolidPGLabel = function () {
    return decodeEntities(solidPGSettings?.title || 'SolidPG Payment Gateway');
};

const CardInputForm = () => {
    return createElement('div', { id: 'card-input-form', style: { padding: '20px', fontFamily: 'Arial, sans-serif' } }, [
        createElement('label', { key: 'cardNumberLabel', style: { display: 'block', marginBottom: '10px' } }, [
            'Card Number',
            createElement('input', {
                key: 'cardNumberInput',
                type: 'text',
                name: 'cardNumber',
                placeholder: 'Enter card number',
                maxLength: 16,
                required: true,
                style: { padding: '10px', fontSize: '16px', width: '100%', boxSizing: 'border-box' },
            }),
        ]),
        createElement('label', { key: 'expiryDateLabel', style: { display: 'block', marginBottom: '10px' } }, [
            'Expiration Date',
            createElement('input', {
                key: 'expiryDateInput',
                type: 'text',
                name: 'expiryDate',
                placeholder: 'MM/YY',
                maxLength: 10,
                required: true,
                style: { padding: '10px', fontSize: '16px', width: '100%', boxSizing: 'border-box' },
            }),
        ]),
        createElement('label', { key: 'cvvLabel', style: { display: 'block', marginBottom: '10px' } }, [
            'CVV',
            createElement('input', {
                key: 'cvvInput',
                type: 'text',
                name: 'cvv',
                placeholder: 'Enter CVV',
                maxLength: 3,
                required: true,
                style: { padding: '10px', fontSize: '16px', width: '100%', boxSizing: 'border-box' },
            }),
        ]),
        createElement('label', { key: 'cardholderNameLabel', style: { display: 'block', marginBottom: '10px' } }, [
            'Cardholder Name',
            createElement('input', {
                key: 'cardholderNameInput',
                type: 'text',
                name: 'cardholderName',
                placeholder: 'Enter cardholder name',
                required: true,
                style: { padding: '10px', fontSize: '16px', width: '100%', boxSizing: 'border-box' },
            }),
        ]),
    ]);
};


const SolidPGPaymentContent = () => {
    // Single form wrapping all input elements
    return createElement('form', {
        id: 'solidpg-payment-form',
        onSubmit: async (event) => {
            // Prevent default form submission (page refresh)
            event.preventDefault();

            // Collect input data
            const formData = new FormData(event.target);
            const cardData = {
                cardNumber: formData.get('cardNumber'),
                expiryDate: formData.get('expiryDate'),
                cvv: formData.get('cvv'),
                cardholderName: formData.get('cardholderName'),
            };

            // Log the data to check if it's collected correctly
            console.log('Collected Card Data:', cardData);

            // Send data to API (using fetch)
            try {
                const response = await fetch('solidpg/v1/payment', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(cardData),
                });

                // Check if the response is successful
                if (!response.ok) throw new Error('Payment failed');
                
                const result = await response.json();
                console.log('Payment successful:', result);
                alert('Payment successful!'); // You can replace this with a success message UI
            } catch (error) {
                console.error('Error during payment:', error);
                alert('Error during payment, please try again.');
            }
        },
    }, [
        createElement(CardInputForm, { key: 'cardInputForm' }),
        // createElement('button', { key: 'submitButton', type: 'submit' }, 'Submit Payment'),
    ]);
};


// Register the SolidPG payment method for WooCommerce blocks
const solidPGPaymentMethod = {
    name: 'solidpg', // Unique identifier for the payment method
    label: createElement('span', null, SolidPGLabel()), // Label displayed in the checkout
    content: createElement(SolidPGPaymentContent),
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
