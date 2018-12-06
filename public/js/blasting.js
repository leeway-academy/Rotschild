$('.action-save').click(function(){
    if ( $('#debito_importe').length ) {
        if ( $('#debito_importe').val() == 0 ) {

            return false;
        }

        $('#debito_importe').val( Math.abs( parseInt( $('#debito_importe').val() ) ) * - 1 );
    }

    if ( $('#credito_importe').length ) {
        if ( $('#credito_importe').val() == 0 ) {

            return false;
        }

        $('#credito_importe').val( Math.abs( parseInt( $('#credito_importe').val() ) ) );
    }
});