( function( wcBlocksRegistry ) {
    const { registerPaymentMethod } = wcBlocksRegistry;

    registerPaymentMethod( {
        name: 'solidpg',
        label: 'Solid Payments Gateway',
        canMakePayment: () => true, // Availability logic
        content: () => <p>Pay securely with Solid Payments Gateway.</p>,
        edit: () => <p>Solid Payment Gateway settings in block editor.</p>,
    } );
} )( window.wc.wcBlocksRegistry );