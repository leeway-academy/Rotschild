$('.action-save').click(function(){
    if ( $('#debitoproyectado_importe').length ) {
        $('#debitoproyectado_importe').val( Math.abs($('#debitoproyectado_importe').val() ) * - 1 );
    }

    if ( $('#creditoproyectado_importe').length ) {
        $('#creditoproyectado_importe').val( Math.abs($('#creditoproyectado_importe').val() ) );
    }
});