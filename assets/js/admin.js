(function($) {
  $(function() {
    var $addIPContainer = $(".wpzerospam-add-ip-container");

    $("#blocked-type", $addIPContainer).change(function() {
      if ( $(this).val() == 'permanent' ) {
        $("#wpzerospam-add-ip-field-start-date").hide();
        $("#wpzerospam-add-ip-field-end-date").hide();
      } else {
        $("#wpzerospam-add-ip-field-start-date").show();
        $("#wpzerospam-add-ip-field-end-date").show();
      }
    });

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
