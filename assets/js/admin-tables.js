(function($) {
  $(function() {
    /**
     * Handles opening the 'View Details' modal on the spam log.
     */
    var $detailsTrigger = $( '.wpzerospam-details-trigger' );
    $detailsTrigger.click(function( e ) {
      e.preventDefault();

      var id = $(this).data('id');
      $('#wpzerospam-details-modal-' + id).addClass( 'is-active' );
    });

    $('.wpzerospam-details-modal').click(function(){
      $(this).removeClass('is-active');
    });

    $(".wpzerospam-details-modal .wpzerospam-details-modal-inner").click(function(e) {
        e.stopPropagation();
    });
  });
})(jQuery);
