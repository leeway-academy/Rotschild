$('.action-save').click(function(){
    if ( $('#debito_importe').length ) {
        $('#debito_importe').val( Math.abs($('#debito_importe').val() ) * - 1 );
    }

    if ( $('#credito_importe').length ) {
        $('#credito_importe').val( Math.abs($('#credito_importe').val() ) );
    }
});