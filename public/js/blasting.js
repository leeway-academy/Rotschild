$('.action-save').click(function(){
    if ( $('#debit_importe').length ) {
        if ( $('#debit_importe').val() == 0 ) {

            return false;
        }

        $('#debit_importe').val( Math.abs( parseInt( $('#debit_importe').val() ) ) * - 1 );
    }

    if ( $('#credit_importe').length ) {
        if ( $('#credit_importe').val() == 0 ) {

            return false;
        }

        $('#credit_importe').val( Math.abs( parseInt( $('#credit_importe').val() ) ) );
    }
});