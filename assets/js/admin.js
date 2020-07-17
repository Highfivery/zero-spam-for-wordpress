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
  });
})(jQuery);
