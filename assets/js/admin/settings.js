'use strict';
(function($){
    function update_qualplay_form( val ) {
        // Re-check the start date.
        $('#start_date').prop( 'checked', true );
        $('.qualpay-form [data-show]').each(function() {
            var attr = $(this).attr('data-show'),
                supported = attr.split(',');
            if( -1 === supported.indexOf( val ) ) {
                $(this).hide();
            } else {
                $(this).show();
            }

        });
    }

    function toggle_qualpay_prorate( val ) {
        if( 'true' === val ) {
            $('.prorate').show();
        } else {
            $('.prorate').hide();
            $('[name=qualpay_plan\\[prorate_first_pmt\\]]').prop( 'checked', false );
            toggle_qualpay_prorate_options( false );
        }
    }

    function toggle_qualpay_prorate_options( val ) {
        if( val ) {
            $('.prorate p').show();
        } else {
            $('.prorate p').hide();
        }
    }

    function toggle_qualpay_trials( val ) {
        if( val ){
            $('.qualpay_trials .form-table').show();
            $('[name=qualpay_plan\\[trial_duration\\]]').attr("min", 1);
            $('[name=qualpay_plan\\[dba_suffix\\]]').attr("required",true);
        } else {
            $('.qualpay_trials .form-table').hide();
            $('[name=qualpay_plan\\[trial_duration\\]]').removeAttr("min");
            $('[name=qualpay_plan\\[dba_suffix\\]]').removeAttr("required");
        }
    }
    $(function() {
        /*update_qualplay_form('0');
        toggle_qualpay_prorate('false');
        toggle_qualpay_prorate_options(false);
        toggle_qualpay_trials( false );*/
        $(document).on( 'change', '#frequency', function(){
            var $this = $(this),
                val   = $this.val();
            update_qualplay_form( val );
            toggle_qualpay_prorate( false );
        });


        if($("#woocommerce_qualpay_recurring").prop('checked') == true){
            $('#woocommerce_qualpay_recurring').parent().append("&nbsp;&nbsp;&nbsp;<a id='plan_append' href='admin.php?page=wc-settings&tab=checkout&section=qualpay&plan=all' class='button button-default'>Plans</a>");
        } else {
            $('#plan_append').hide();
        }

        $("#woocommerce_qualpay_recurring").change(function() {
            if(this.checked) {
                if( $('#plan_append').length <= 0) {
                    $('#woocommerce_qualpay_recurring').parent().append("&nbsp;&nbsp;&nbsp;<a id='plan_append' href='admin.php?page=wc-settings&tab=checkout&section=qualpay&plan=all' class='button button-default'>Plans</a>");
                } else {
                    $('#plan_append').show();
                }
            
            } else {
                $('#plan_append').hide();
            }
        });
    
        $(document).on( 'change', '[name=qualpay_plan\\[bill_specific_day\\]]', function(){
           var specific = $(this).val();
            toggle_qualpay_prorate( specific );
        });

        $(document).on( 'change', '[name=qualpay_plan\\[prorate_first_pmt\\]]', function(){
            var checked = $(this).prop('checked');
            toggle_qualpay_prorate_options(checked);
        });

        $(document).on('change', '[name=qualpay_plan\\[qualpay_plan_trial\\]]', function(){
            var checked = $(this).prop('checked');
            toggle_qualpay_trials( checked );
        });
    });
})(jQuery);