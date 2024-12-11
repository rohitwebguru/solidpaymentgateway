const { createElement } = wp.element;

// Assuming SolidPGButtonData is already localized in the script with the button HTML
const SolidPGPaymentMethod = function() {
    // Access the localized button HTML
    const buttonHTML = SolidPGButtonData.button_html;

    // Create the container for the payment method
    const paymentMethodContainer = document.createElement('div');

    // Create the title for the payment method
    const title = document.createElement('h2');
    title.textContent = 'SolidPG Payment Gateway';

    // Create the description paragraph
    const description = document.createElement('p');
    description.textContent = 'Pay securely using SolidPG payment method.';

    // Append the title and description to the container
    paymentMethodContainer.appendChild(title);
    paymentMethodContainer.appendChild(description);

    // Create a div for the button and insert the raw HTML
    const buttonContainer = document.createElement('div');
    buttonContainer.innerHTML = buttonHTML;

    // Append the button to the payment method container
    paymentMethodContainer.appendChild(buttonContainer);

    return paymentMethodContainer;
};

// Wait for `wc.blocksRegistry` to be available
function waitForWooCommerceBlocks() {
    // if (typeof wc !== 'undefined' && wc.blocksRegistry) {
        // Register the payment method when wc.blocksRegistry is available
        wc.blocksRegistry.registerPaymentMethod('solidpg', {
            methodTitle: 'SolidPG Payment',
            methodDescription: 'Use SolidPG for fast and secure payments',
            content: SolidPGPaymentMethod(),
        });
    // } else {
        // Retry after a short delay if `wc.blocksRegistry` is not yet available
        setTimeout(waitForWooCommerceBlocks, 100);
    // }
}

// Call the function to check and register the payment method
waitForWooCommerceBlocks();
