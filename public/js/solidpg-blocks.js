// Import required modules (if applicable)
const { createElement } = wp.element;
const { decodeEntities } = wp.htmlEntities;
const { useState, useEffect } = wp.element;
const { createRoot } = wp.element;

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
    // State variables to store form input values
    const [cardNumber, setCardNumber] = useState('');
    const [expiryDate, setExpiryDate] = useState('');
    const [cvv, setCvv] = useState('');
    const [cardholderName, setCardholderName] = useState('');
    const [isLoading, setIsLoading] = useState(false); 
    const [error, setError] = useState(null);

    // Function to format the expiration date as MM/YYYY
    const formatExpiryDate = (event) => {
        let value = event.target.value.replace(/\D/g, ''); // Remove all non-digit characters
        if (value.length > 2 && value.length <= 6) {
            value = value.slice(0, 2) + '/' + value.slice(2);
        }
        setExpiryDate(value); // Update the state for expiryDate
    };
    let value = '';
    const textarea = document.getElementsByClassName('wc-block-components-textarea'); 
    if( textarea.length > 0){
        const value = textarea[0].value ? textarea[0].value : '';
    }

    const handleSubmit = (event) => {
        event.preventDefault();
        setIsLoading(true); // Show loader

        let isValid = true;
        const termsCheckbox = document.getElementById('terms');
        const refundPolicyCheckbox = document.getElementById('refund-policy-checkbox');
    
        // Validate checkboxes
        if (!termsCheckbox.checked || !refundPolicyCheckbox.checked) {
            setError('You must agree to the terms and refund policy to proceed.');
            isValid = false;
        }
        // Validate card number (must be 16 digits)
        if (!/^\d{16}$/.test(cardNumber)) {
            setError('Card number must be exactly 16 digits.');
            isValid = false;            
        }else if(!/^\d{2}\/\d{4}$/.test(expiryDate)) {
            setError('Expiration date must be in MM/YYYY format.');
            isValid = false;
        }else if (!/^\d{3}$/.test(cvv)) {
            setError('CVV must be exactly 3 digits.');
            isValid = false;
        }else if (!cardholderName) {
            setError('Cardholder name is required.');
            isValid = false;
        }

        if (!isValid) {
            setIsLoading(false); // Hide loader if validation fails
            return;
        }else{
            setError('');
        }

        // Extract month and year from expiryDate
        const [month, year] = expiryDate.split('/');

        // Prepare data to send
        const formData = {
            "entityId": solidpgData.merchantEntityId,
            "amount": solidpgData.total_price,
            "currency": "EUR",
            "paymentBrand": 'VISA',
            "paymentType": "DB",
            "card_number": cardNumber,
            "card_holder": cardholderName,
            "card_expiryMonth": month,
            "card_expiryYear": year,
            "card_cvv": cvv,
            "shopperResultUrl": solidpgData.returnUrl,
            "order_note" : value ? value : ''
        };
        
        // Send data to the specified URL using fetch
        fetch(`${solidpgData.home_url}/wp-json/solidpg/v1/payment`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                "Authorization": `Bearer ${solidpgData.merchantToken}`,
            },
            body: JSON.stringify(formData),
        })
            .then(response => response.json())
            .then(data => {
                setIsLoading(false); // Hide loader
                // Handle success response
                if (data?.resultDetails?.ExtendedDescription) {
                    window.location.href = `${solidpgData.home_url}/solidpg-thankyou-page?order_id_solid=${data.id}&order_note=${value?value:''}`;
                }else{
                    setError(data.result.description);
                }
            })
            // .catch(error => {
            //     setIsLoading(false); // Hide loader on error
            //     console.error('Error:', error);
            //     alert('An error occurred. Please try again later.');
            // });
    };

    return createElement('div', { id: 'card-input-form', style: { padding: '20px', fontFamily: 'Arial, sans-serif' } }, [
       
        createElement('label', { key: 'cardNumberLabel', style: { display: 'block', marginBottom: '10px', fontSize: '12px', } }, [
            'Card Number',
            createElement('input', {
                key: 'cardNumberInput',
                type: 'text',
                id: 'cardNumberInput',
                name: 'cardNumber',
                placeholder: 'Enter card number',
                maxLength: 16,
                required: true,
                value: cardNumber,
                onChange: (e) => setCardNumber(e.target.value),
                style: { padding: '10px', fontSize: '16px', width: '100%', boxSizing: 'border-box' },
            }),
        ]),
        createElement('label', { key: 'expiryDateLabel', style: { display: 'block', marginBottom: '10px', fontSize: '12px', } }, [
            'Expiration Date',
            createElement('input', {
                key: 'expiryDateInput',
                type: 'text',
                id: 'expiryDateInput',
                name: 'expiryDate',
                placeholder: 'MM/YYYY',
                maxLength: 7,
                required: true,
                value: expiryDate,
                onChange: formatExpiryDate,
                style: { padding: '10px', fontSize: '16px', width: '100%', boxSizing: 'border-box' },
            }),
        ]),
        createElement('label', { key: 'cvvLabel', style: { display: 'block', marginBottom: '10px', fontSize: '12px', } }, [
            'CVV',
            createElement('input', {
                key: 'cvvInput',
                type: 'text',
                id: 'cvvInput',
                name: 'cvv',
                placeholder: 'Enter CVV',
                maxLength: 3,
                required: true,
                value: cvv,
                onChange: (e) => setCvv(e.target.value),
                style: { padding: '10px', fontSize: '16px', width: '100%', boxSizing: 'border-box' },
            }),
        ]),
        createElement('label', { key: 'cardholderNameLabel', style: { display: 'block', marginBottom: '10px', fontSize: '12px', } }, [
            'Cardholder Name',
            createElement('input', {
                key: 'cardholderNameInput',
                type: 'text',
                id: 'cardholderNameInput',
                name: 'cardholderName',
                placeholder: 'Enter cardholder name',
                required: true,
                value: cardholderName,
                onChange: (e) => setCardholderName(e.target.value),
                style: { padding: '10px', fontSize: '16px', width: '100%', boxSizing: 'border-box' },
            }),
        ]),
        error && createElement('div', {
            id: 'error-div',
            style: { display: 'block', marginBottom: '10px', fontSize: '12px',background: 'red',textAlign: 'center',color: 'white' }
        }, [
            createElement('p', {
                id: 'error-text',
                style: { padding: '10px', fontSize: '16px', width: '100%', boxSizing: 'border-box' }
            }, error) // Display the error message
        ]),
        createElement('button', {
            key: 'submitButton',
            type: 'button',
            id: 'react-custom-btn',
            onClick: handleSubmit,
            style: {
                padding: '15px 20px',
                fontSize: '16px',
                width: '100%',
                backgroundColor: '#7a7ff4',
                color: 'white',
                border: 'none',
                cursor: 'pointer',
                display: 'flex',
                justifyContent: 'center',
                alignItems: 'center',
            }
        }, isLoading ? createElement('div', { key: 'loader', style: { textAlign: 'center' } }, [
            createElement('span', {
                key: 'spinner',
                style: {
                    display: 'inline-block',
                    width: '16px',
                    height: '16px',
                    border: '5px solid rgba(255, 254, 254, 0.2)',
                    borderTop: '5px solid white',
                    borderRadius: '50%',
                    animation: 'spin 1s linear infinite'
                }
            })
        ]) : 'Place Order')        
    ]);
};

let root = null; // Declare root globally

const renderReactComponent = () => {
    const container = document.getElementById('solidpg-react-container');
    
    if (container) {
        if (!root) {
            root = createRoot(container);
        }
        
        // Render or update the React component
        root.render(createElement(CardInputForm));
    }
};

// document.addEventListener('DOMContentLoaded', () => {
    

//     // Monitor DOM changes and render only if the container is replaced
//     const observer = new MutationObserver((mutations) => {
//         for (const mutation of mutations) {
//             if (mutation.type === 'childList') {
//                 const container = document.getElementById('solidpg-react-container');
//                 if (container && !root) {
//                     renderReactComponent();
//                 }
//             }
//         }
//     });

//     observer.observe(document.body, { childList: true, subtree: true });
// });


// Register the SolidPG payment method for WooCommerce blocks
const solidPGPaymentMethod = {
    name: 'solidpg', // Unique identifier for the payment method
    label: createElement('span', null, SolidPGLabel()), // Label displayed in the checkout
    content: createElement(CardInputForm),
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
}
