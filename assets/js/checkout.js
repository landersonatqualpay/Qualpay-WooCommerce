'use strict';
(function($){
    var token_used = false;
    var capture_id = $("#capture_id").val();
    var enable_ach = false;
    if(capture_id){
        enable_ach = true;
    }
    
    function qualpay_load_embedded( form_id, form, token ) {
        $('#qp-embedded-container').show();
        $('#save_card').show();
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
                achConfig: {
                    enabled: enable_ach,
                    onPaymentTypeChange: function (data) {
                      console.log("Display ", data.type);
                      if(data.type == 'ACH') {
                        $("#ach_container").show();
                     //   $("#save_card").hide(); 
                      } else {
                        $("#ach_container").hide(); 
                      //  $("#save_card").show(); 
                      }
                    }
                  },
                onSuccess: function( data ) {
                    $('#qualpay_card_id').val( data.card_id );

                    token_used = true;
                    form.submit();
                },
                onError: function( error ) {
                    if( error.code == 2 ) {
                        for( var key in error.detail ) {
                            alert(error.detail[key]);
                            return false;
                        }
                    } else {
                        alert('There was an issue processing your transaction.  Please check the card details and try again.');
                        return false;
                    }
                }
            }
        );   
    }

    function preSubmit()
        {
            var url      = window.location.href; 
            //var matches = url.match(/\/order\-pay\/(.*)$/);
            var matches = url.match(/order-pay/);
            if (!matches) {
                
                var billing_first_name  = $('#billing_first_name');
                var billing_last_name   = $('#billing_last_name');
                var billing_country     = $('#billing_country');
                var billing_address_1   = $('#billing_address_1');
                var billing_city        = $('#billing_city');
                var billing_state       = $('#billing_state');
                var billing_postcode    = $('#billing_postcode');
                var billing_phone       = $('#billing_phone');
                var billing_email       = $('#billing_email');
                var account_username    = $('#account_username');
                var account_password    = $('#account_password');

                var email_regex = /^([\w-\.]+)@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.)|(([\w-]+\.)+))([a-zA-Z]{2,4}|[0-9]{1,3})(\]?)$/;
                var zip_regex = /(^\d{5}$)|(^\d{5}-\d{4}$)/;
             //  var phone_regex = /^[0-9\-\(\)\s]+/;
                if ($('#g-recaptcha-response').length !== 0 && grecaptcha.getResponse() == '') {
                    alert("Captcha is mandatory");
                    return false;
                } 
                if ((typeof billing_first_name !== 'undefined') && (billing_first_name.val() == "")) {
                    alert("First name is mandatory"); 
                    $('.wpmc-step-payment').removeClass('current');
                    $('.wpmc-step-billing').addClass('current');
                    $('#wpmc-prev').removeClass('current');
                    $('#wpmc-next').addClass('current');
                    $("#billing_first_name").focus();
                    return false;
                }
                else if ((typeof billing_last_name !== 'undefined') && billing_last_name.val() == "") {
                    alert("Last name is mandatory"); 
                    $('.wpmc-step-payment').removeClass('current');
                    $('.wpmc-step-billing').addClass('current');
                    $('#wpmc-prev').removeClass('current');
                    $('#wpmc-next').addClass('current');
                    $("#billing_last_name").focus();
                    return false;
                }
                else if ((typeof billing_country !== 'undefined') && billing_country.val() == "") {
                    alert("Country is mandatory"); 
                    $('.wpmc-step-payment').removeClass('current');
                    $('.wpmc-step-billing').addClass('current');
                    $('#wpmc-prev').removeClass('current');
                    $('#wpmc-next').addClass('current');
                    $("#billing_country").focus();
                    return false;
                }
                else if ((typeof billing_address_1 !== 'undefined') && billing_address_1.val() == "") {
                    alert("Street Address is mandatory"); 
                    $('.wpmc-step-payment').removeClass('current');
                    $('.wpmc-step-billing').addClass('current');
                    $('#wpmc-prev').removeClass('current');
                    $('#wpmc-next').addClass('current');
                    $("#billing_address_1").focus();
                    return false;
                }
                else if ((typeof billing_city !== 'undefined') && billing_city.val() == "") {
                    alert("City is mandatory"); 
                    $('.wpmc-step-payment').removeClass('current');
                    $('.wpmc-step-billing').addClass('current');
                    $('#wpmc-prev').removeClass('current');
                    $('#wpmc-next').addClass('current');
                    $("#billing_city").focus();
                    return false;
                }
                else if ((typeof billing_state !== 'undefined') && billing_state.val() == "") {
                    alert("State is mandatory"); 
                    $('.wpmc-step-payment').removeClass('current');
                    $('.wpmc-step-billing').addClass('current');
                    $('#wpmc-prev').removeClass('current');
                    $('#wpmc-next').addClass('current');
                    $("#billing_state").focus();
                    return false;
                }
                else if ((typeof billing_postcode !== 'undefined') && billing_postcode.val()== "") {
                        alert("Zip code is mandatory");
                        $('.wpmc-step-payment').removeClass('current');
                        $('.wpmc-step-billing').addClass('current');
                        $('#wpmc-prev').removeClass('current');
                        $('#wpmc-next').addClass('current');
                        $("#billing_postcode").focus();
                        return false;
                }
                else if(billing_postcode.val() != "" && !billing_postcode.val().match(zip_regex)) {
                        alert("Please enter a valid zip code "); 
                        $('.wpmc-step-payment').removeClass('current');
                        $('.wpmc-step-billing').addClass('current');
                        $('#wpmc-prev').removeClass('current');
                        $('#wpmc-next').addClass('current');
                        $("#billing_postcode").focus();
                        return false;
                    } 
                else if ((typeof billing_phone !== 'undefined') && billing_phone.val() == "") {
                    alert("Phone number is mandatory"); 
                    $('.wpmc-step-payment').removeClass('current');
                    $('.wpmc-step-billing').addClass('current');
                    $('#wpmc-prev').removeClass('current');
                    $('#wpmc-next').addClass('current');
                    $("#billing_phone").focus();
                    return false;
                }
                // else if (billing_phone.val() != "" && (!billing_phone.val().match(phone_regex) )) {
                //     alert("Please enter a valid Phone number "); 
                //     $('.wpmc-step-payment').removeClass('current');
                //     $('.wpmc-step-billing').addClass('current');
                //     $('#wpmc-prev').removeClass('current');
                //     $('#wpmc-next').addClass('current');
                //     $("#billing_phone").focus();
                //     return false;
                // }
                else if ((typeof billing_email !== 'undefined') && billing_email.val() == "") {
                    alert("Email address is mandatory"); 
                    $('.wpmc-step-payment').removeClass('current');
                    $('.wpmc-step-billing').addClass('current');
                    $('#wpmc-prev').removeClass('current');
                    $('#wpmc-next').addClass('current');
                    $("#billing_email").focus();
                    return false;
                }
                else if (billing_email.val() != "" && (!billing_email.val().match(email_regex))) {
                    alert("Please enter a valid Email address "); 
                    $('.wpmc-step-payment').removeClass('current');
                    $('.wpmc-step-billing').addClass('current');
                    $('#wpmc-prev').removeClass('current');
                    $('#wpmc-next').addClass('current');
                    $("#billing_email").focus();
                    return false;
                }
            
               // else 
                // var method = $('input[name=payment_method]:checked');
                //     if(method.val() == 'qualpay'){
                //         qualpay_load_embedded( form_id, form );
                //     }
                // var payment = $('input[name=payment_method]');
                
                else if ($('input[name="createaccount"]:checked').length == '1') {
                    if ((typeof account_username !== 'undefined') && account_username.val() == "") {
                        alert("Username is mandatory"); 
                        $('.wpmc-step-payment').removeClass('current');
                        $('.wpmc-step-billing').addClass('current');
                        $('#wpmc-prev').removeClass('current');
                        $('#wpmc-next').addClass('current');
                        $("#account_username").focus();
                        return false;
                    }
                    else if ((typeof account_password !== 'undefined') && account_password.val() == "") {
                        alert("Password is mandatory"); 
                        $('.wpmc-step-payment').removeClass('current');
                        $('.wpmc-step-billing').addClass('current');
                        $('#wpmc-prev').removeClass('current');
                        $('#wpmc-next').addClass('current');
                        $("#account_password").focus();
                        return false;
                    } 
                }
                else if($('#ach_authorize').is(":visible")) {
                    if($('input[name="ach_authorize"]:checked').length == '0') {
                        alert('Click on the check box to authorize the electronic funds transfer.');
                        return false;
                    }
                }
                else if( $('#terms').is(":visible")){
                    if(!($('#terms').is(':checked'))) {
                        alert("Please Select Read terms and conditions."); 
                        $("#terms").focus();
                        return false;
                    } else {
                        return true;
                    }
                }
                else if(($('#account_username').is(":visible")) && ($('#account_password').is(":visible"))) {
                    if ((typeof account_username !== 'undefined') && account_username.val() == "") {
                        alert("Username is mandatory"); 
                        $('.wpmc-step-payment').removeClass('current');
                        $('.wpmc-step-billing').addClass('current');
                        $('#wpmc-prev').removeClass('current');
                        $('#wpmc-next').addClass('current');
                        $("#account_username").focus();
                        return false;
                    }
                    if ((typeof account_password !== 'undefined') && account_password.val() == "") {
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
            } else {
                if($('#ach_authorize').is(":visible")) {
                    if($('input[name="ach_authorize"]:checked').length == '0') {
                        alert('Click on the check box to authorize the electronic funds transfer.');
                        return false;
                    }
                }
                else if( $('#terms').is(":visible")){
                    if(!($('#terms').is(':checked'))) {
                        alert("Please Select Read terms and conditions."); 
                        $("#terms").focus();
                        return false;
                    } else {
                        return true;
                    }
                }
               
                else {
                    return true;
                }
               
            }

        }
    function unload_frame(){
        $('#qp-embedded-container').hide();
        $("#ach_container").hide();  
        $("#save_card").hide();
        qpEmbeddedForm.unloadFrame();

    }

    $(function(){
        var form = $('form.checkout');
        var url      = window.location.href; 
        // var regex = /order-pay/g;
        //var found = url.match(regex);
       // var matches = url.match(/\/order-pay\/(.*)$/);
       var matches = url.match(/order-pay/g);
        
        //alert(found);

        if (matches) {
            form = $('form#order_review');
        } 
       //alert(form.length);
        if( form.length ) {
            var form_id = form.attr('id');
            if( ! form_id ) {
                form.attr('id', 'woocommerce-checkout');
                form_id = 'woocommerce-checkout';
            }      
            if (matches) {
                frame_load_with_conditions(form_id,form);
                //qualpay_load_embedded( form_id, form );
            }

            $( document ).on( 'updated_checkout', function( e, data ){
                if ( ! token_used ) {
                    frame_load_with_conditions(form_id,form);
                } else {
                     $.ajax({
                        url: qualpay.ajax_url,
                        data: { action: 'qualpay_get_embedded_token', nonce: qualpay.nonce },
                        success: function( resp ) {
                            if( resp.success ) {
                                unload_frame();
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

        // if(($('input[name=createaccount]').is(":visible"))) {
        //     $("#save_card").hide();
        //     $('input[name="createaccount"]').change(function(){
        //         if($(this).prop("checked") == true){
        //             $("#save_card").show();
        //         }
        //         else if($(this).prop("checked") == false){
        //             $("#save_card").hide();
        //         }
        //     });
        // } 
    });

    function frame_load_with_conditions(form_id,form) {
        var payment = $('input[name=payment_method]');
        var method = $('input[name=payment_method]:checked');
        
       if(method.val() == 'qualpay'){
            get_card_id(form_id,form);
        }
         payment.change(function() {
            if ('qualpay' == $(this).val() ) {
                if ( ! token_used ) {
                    get_card_id(form_id,form);
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
            } else {
                unload_frame();
            }
        });
    }
    function get_card_id(form_id, form) {
        var card_value = $('input[name=qp_payment_cards]:checked').val();
        var payment_card = $('input[name=qp_payment_cards]');
        var elmId = $('input[name=qp_payment_cards]:checked').attr("id");
        if(($('input[name=qp_payment_cards]').is(":visible"))) {
            if($('#cvvDiv').is(":visible")) {
                $('#cvvDiv').remove();
            }
            if(card_value == 'credit_card') {
                qualpay_load_embedded( form_id, form );
            } else {
                unload_frame();
                $('#qualpay_card_id').val( card_value );
                if((typeof $('#settingCVVon') != 'undefined') && ($('#settingCVVon').val()==1) && elmId != 'AP') {
                    var $newdiv = '<div id="cvvDiv"><input type="text" id="cvv2" name="cvv2" placeholder="CVV" /></div>' ;
                    $('input[name=qp_payment_cards]:checked').parent().append($newdiv);
                }
            }
            payment_card.change(function() {
                get_card_id(form_id,form);
            });
        } else {
            qualpay_load_embedded( form_id, form );
        }   
    }
    
})(jQuery);