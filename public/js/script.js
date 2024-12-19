jQuery( document).on( 'change', '.wc-block-components-radio-control__input',function(){
    console.log(jQuery(this ).attr('id'));
    var spGatewayId =  jQuery(this ).attr('id')

    if(spGatewayId == 'radio-control-wc-payment-method-options-solidpg'){
        jQuery( '.wc-block-checkout__actions_row' ).hide();        
        jQuery( '.wc-block-checkout__terms' ).hide();        
    }else{
        jQuery( '.wc-block-checkout__actions_row' ).show();
        jQuery( '.wc-block-checkout__terms' ).show();
    }    
    
    console.log('mid')
});