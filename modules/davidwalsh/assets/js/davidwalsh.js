/**
 * Zero Spam for WordPress David Walsh jQuery plugin.
 *
 * Handles adding the required functionality for spam detections.
 */
(function ($) {
  $.fn.ZeroSpamDavidWalsh = function () {
    // Check if the required WPZS key is defined.
    if (typeof ZeroSpamDavidWalsh.key == "undefined") {
      return this;
    }

    // Check if the element is on the page.
    if (!this.length) {
      return this;
    }

    // Add an attribute to the element to show its been initialized by WPZS.
    this.attr("data-zerospam-davidwalsh", "protected");

    // Check if the WPZS hidden input already exists.
    if ($('[name="zerospam_david_walsh_key"]', this).length) {
      // Hidden input already exists, update its value.
      $('[name="zerospam_david_walsh_key"]', this).val(ZeroSpamDavidWalsh.key);
    } else {
      // Hidden input isn't present, add it.
      $(
        '<input type="hidden" name="zerospam_david_walsh_key" value="' +
          ZeroSpamDavidWalsh.key +
          '" />'
      ).appendTo(this);
    }
  };

  $(function () {
    var selectors =
      ".frm-fluent-form, .mepr-signup-form, .mc4wp-form, #mepr_loginform";
    if (
      typeof ZeroSpamDavidWalsh.selectors != "undefined" &&
      ZeroSpamDavidWalsh.selectors
    ) {
      selectors += "," + ZeroSpamDavidWalsh.selectors;
    }

    jQuery(selectors).ZeroSpamDavidWalsh();
  });
})(jQuery);
