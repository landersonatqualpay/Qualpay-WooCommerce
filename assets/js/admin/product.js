'use strict';
(function($){

    $(function(){
        // On load.
        show_qualpay_tab_panel();
        toggle_qualpay_duration_field();
        toggle_qualpay_interval_field();
        toggle_qualpay_custom_plan_group();

        $( document ).on( 'change', '#_qualpay_bill_until_cancelled', function(){
            toggle_qualpay_duration_field();
        });

        $( document ).on( 'change', '#_qualpay_use_plan', function(){
            toggle_qualpay_custom_plan_group();
        });

        $( document ).on( 'change', '#_qualpay_frequency', function(){
            toggle_qualpay_interval_field();
        });

        $( document ).on( 'change', '#_qualpay', function(){
            show_qualpay_tab_panel();
        });

        $( document ).on( 'woocommerce-product-type-change', function( e, value ){
            if( 'simple' !== value && 'variable' !== value ) {
                $( 'input#_qualpay' ).prop( 'checked', false );
                $( 'input#_qualpay' ).removeAttr('checked');

            }
            show_qualpay_tab_panel();
        });

        function toggle_qualpay_custom_plan_group() {
            if( $('#_qualpay_use_plan').prop('checked') ) {
                $('#qualpay_custom_plan_group').hide();
                $('._qualpay_plan_code_field').show();
            } else {
                $('#qualpay_custom_plan_group').show();
                $('._qualpay_plan_code_field').hide();
            }
        }

        function toggle_qualpay_interval_field() {
            var frequency = parseInt( $( '#_qualpay_frequency' ).val() );
            if( frequency === 0 || frequency === 3 ) {
                $('._qualpay_interval_field').show();
            } else {
                $('._qualpay_interval_field').hide();
            }
        }

        function toggle_qualpay_duration_field() {
            if( $('#_qualpay_bill_until_cancelled').prop('checked') ) {
                $('._qualpay_duration_field').hide();
            } else {
                $('._qualpay_duration_field').show();
            }
        }

        function show_qualpay_tab_panel() {
            var is_qualpay = $('input#_qualpay:checked').length;

            if( is_qualpay ) {
                $('.show_if_qualpay').show();
                if( $('.show_if_qualpay').hasClass('active') ) {
                    $('#qualpay_product_data').show();
                }
            } else {
                $('.show_if_qualpay').hide();
                $('#qualpay_product_data').hide();
            }
        }
    });
})(jQuery);
