'use strict';


(function($){
    var token_used = false;
    function qualpay_load_embedded( form_id, form, token ) {
        token = token || qualpay.transient_key;
        
        qpEmbeddedForm.loadFrame(
            parseInt( qualpay.merchant_id ),
            {
                formId: form_id,
                mode: qualpay.mode,
                transientKey: token,
                tokenize: true,
                preSubmit: preSubmit,
                style: qualpay.embedded_css,
                onSuccess: function( data ) {
                    console.log('success');
                    $('#qualpay_card_id').val( data.card_id );
                    token_used = true;
                    form.submit();
                },
                onError: function( error ) {
                    console.log('error');
                    if( error.detail ) {
                        for( var key in error.detail ) {
                            console.log( error.detail[key] );
                            alert('There was an issue processing your transaction.  Please check the card details and try again.' );
                        }
                    }
                }
            }
        );
    
        function preSubmit()
        {
            var billing_first_name  = $('#billing_first_name').val();
            var billing_last_name   = $('#billing_last_name').val();
            var billing_country     = $('#billing_country').val();
            var billing_address_1   = $('#billing_address_1').val();
            var billing_city        = $('#billing_city').val();
            var billing_state       = $('#billing_state').val();
            var billing_postcode    = $('#billing_postcode').val();
            var billing_phone       = $('#billing_phone').val();
            var billing_email       = $('#billing_email').val();
            var account_username    = $('#account_username').val();
            var account_password    = $('#account_password').val();
           //  var name_regex = '/^[a-zA-Z]+$/';
            var email_regex = /^([\w-\.]+)@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.)|(([\w-]+\.)+))([a-zA-Z]{2,4}|[0-9]{1,3})(\]?)$/;
          //  var add_regex = '/^[0-9a-zA-Z]+$/';
            var zip_regex = /(^\d{5}$)|(^\d{5}-\d{4}$)/;
            var phone_regex = /^[0-9\-\(\)\s]+/;
            
            if (billing_first_name.length == 0) {
                alert("First name is mandatory"); 
                $('.wpmc-step-payment').removeClass('current');
                $('.wpmc-step-billing').addClass('current');
                $('#wpmc-prev').removeClass('current');
                $('#wpmc-next').addClass('current');
                $("#billing_first_name").focus();
                return false;
            }
            else if (billing_last_name.length == 0) {
                alert("Last name is mandatory"); 
                $('.wpmc-step-payment').removeClass('current');
                $('.wpmc-step-billing').addClass('current');
                $('#wpmc-prev').removeClass('current');
                $('#wpmc-next').addClass('current');
                $("#billing_last_name").focus();
                return false;
            }
            else if (billing_country.length == 0) {
                alert("Country is mandatory"); 
                $('.wpmc-step-payment').removeClass('current');
                $('.wpmc-step-billing').addClass('current');
                $('#wpmc-prev').removeClass('current');
                $('#wpmc-next').addClass('current');
                $("#billing_country").focus();
                return false;
            }
            else if (billing_address_1.length == 0) {
                alert("Street Address is mandatory"); 
                $('.wpmc-step-payment').removeClass('current');
                $('.wpmc-step-billing').addClass('current');
                $('#wpmc-prev').removeClass('current');
                $('#wpmc-next').addClass('current');
                $("#billing_address_1").focus();
                return false;
            }
            else if (billing_city.length == 0) {
                alert("City is mandatory"); 
                $('.wpmc-step-payment').removeClass('current');
                $('.wpmc-step-billing').addClass('current');
                $('#wpmc-prev').removeClass('current');
                $('#wpmc-next').addClass('current');
                $("#billing_city").focus();
                return false;
            }
            else if (billing_state.length == 0) {
                alert("State is mandatory"); 
                $('.wpmc-step-payment').removeClass('current');
                $('.wpmc-step-billing').addClass('current');
                $('#wpmc-prev').removeClass('current');
                $('#wpmc-next').addClass('current');
                $("#billing_state").focus();
                return false;
            }
            else if (billing_postcode.length == 0) {
                alert("Zip code is mandatory");
                $('.wpmc-step-payment').removeClass('current');
                $('.wpmc-step-billing').addClass('current');
                $('#wpmc-prev').removeClass('current');
                $('#wpmc-next').addClass('current');
                $("#billing_postcode").focus();
                return false;
            }
            else if (!billing_postcode.match(zip_regex) || billing_postcode.length == 0 ) {
                alert("Please enter a valid zip code "); 
                $('.wpmc-step-payment').removeClass('current');
                $('.wpmc-step-billing').addClass('current');
                $('#wpmc-prev').removeClass('current');
                $('#wpmc-next').addClass('current');
                $("#billing_postcode").focus();
                return false;
            }
            else if (billing_phone.length == 0) {
                alert("Phone number is mandatory"); 
                $('.wpmc-step-payment').removeClass('current');
                $('.wpmc-step-billing').addClass('current');
                $('#wpmc-prev').removeClass('current');
                $('#wpmc-next').addClass('current');
                $("#billing_phone").focus();
                return false;
            }
            else if (!billing_phone.match(phone_regex) || billing_phone.length == 0 ) {
                alert("Please enter a valid Phone number "); 
                $('.wpmc-step-payment').removeClass('current');
                $('.wpmc-step-billing').addClass('current');
                $('#wpmc-prev').removeClass('current');
                $('#wpmc-next').addClass('current');
                $("#billing_phone").focus();
                return false;
            }
            else if (billing_email.length == 0) {
                alert("Email address is mandatory"); 
                $('.wpmc-step-payment').removeClass('current');
                $('.wpmc-step-billing').addClass('current');
                $('#wpmc-prev').removeClass('current');
                $('#wpmc-next').addClass('current');
                $("#billing_email").focus();
                return false;
            }
           else if (!billing_email.match(email_regex) || billing_email.length == 0 ) {
                alert("Please enter a valid Email address "); 
                $('.wpmc-step-payment').removeClass('current');
                $('.wpmc-step-billing').addClass('current');
                $('#wpmc-prev').removeClass('current');
                $('#wpmc-next').addClass('current');
                $("#billing_email").focus();
                return false;
            }
          
            else if ($('input[name="createaccount"]:checked').length > 0) {
                if (account_username.length == 0) {
                    alert("Username is mandatory"); 
                    $('.wpmc-step-payment').removeClass('current');
                    $('.wpmc-step-billing').addClass('current');
                    $('#wpmc-prev').removeClass('current');
                    $('#wpmc-next').addClass('current');
                    $("#account_username").focus();
                    return false;
                }
                else if (account_password.length == 0) {
                    alert("Password is mandatory"); 
                    $('.wpmc-step-payment').removeClass('current');
                    $('.wpmc-step-billing').addClass('current');
                    $('#wpmc-prev').removeClass('current');
                    $('#wpmc-next').addClass('current');
                    $("#account_password").focus();
                    return false;
                }
            }
            else {
                return true;
            }
            
        }
       
    }

    $(function(){

        var form = $('form.checkout');
        var url      = window.location.href; 
        var matches = url.match(/\/order-pay\/(.*)$/);
     
        if (matches) {
            form = $('form#order_review');
        } 
       
        if( form.length ) {
            var form_id = form.attr('id');
            if( ! form_id ) {
                form.attr('id', 'woocommerce-checkout');
                form_id = 'woocommerce-checkout';
            }      
            
            qualpay_load_embedded( form_id, form );

            $( document ).on( 'updated_checkout', function( e, data ){
                if ( ! token_used ) {
                    qualpay_load_embedded( form_id, form );
                } else {
                    $.ajax({
                        url: qualpay.ajax_url,
                        data: { action: 'qualpay_get_embedded_token', nonce: qualpay.nonce },
                        success: function( resp ) {
                            if( resp.success ) {
                                qpEmbeddedForm.unloadFrame();
                                qualpay.transient_key = resp.data.transient_key;
                                qualpay_load_embedded( form_id, form, qualpay.transient_key );
                            }
                        }
                    });
                }
            });

            form.on( 'checkout_place_order_qualpay', function(){
                var card_id = $('#qualpay_card_id').val();
                if( '' === card_id ) {
                    return false;
                }
                return true;
            });
        }
    });
})(jQuery);