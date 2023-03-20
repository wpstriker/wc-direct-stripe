var stripe = Stripe( wc_direct_stripe_params.key );

jQuery(document).on('click', '.stripe_direct_button', function(e){
	var product_id	= jQuery(this).val();
	var button_text	= jQuery(this).text(); 
	
	jQuery(this).html( 'Please wait...' );
	
	jQuery.post( wc_direct_stripe_params.ajax_url, { action: 'stripe_direct_checkout', product_id: product_id }, function(response) {
		if( response == '0' ) {
			alert( 'Something went wrong, please try again.' );
		} else {
			stripe.redirectToCheckout({
			  	sessionId: response
			}).then(function (result) {
			  	alert( result.error.message );
			});
		}
	});
});