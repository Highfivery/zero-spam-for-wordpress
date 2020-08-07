/**
 * WordPress Zero Spam jQuery plugin.
 *
 * Handles adding the required functionality for spam detections.
 *
 * @since 4.9.11
 */
(function ($) {
  $.fn.WordPressZeroSpam = function () {
    // Check if the required WPZS key is defined.
    if (typeof wpzerospam.key == "undefined") {
      // The key is not defined, alert the site owner via the console.
      console.log(
        "WordPress Zero Spam is unable to initialize, missing the required key."
      );

      return this;
    }

    // Check if the element is on the page.
    if (!this.length) {
      console.log(
        "WordPress Zero Spam could not find a " + this.selector + " instance."
      );

      return this;
    }

    console.log(
      "WordPress Zero Spam found " +
        this.length +
        " instance(s) of " +
        this.selector +
        "."
    );

    // Add an attribute to the element to show its been initialized by WPZS.
    this.attr("data-wpzerospam", "protected");

    // Check if the WPZS hidden input already exists.
    if ($('[name="wpzerospam_key"]', this).length) {
      // Hidden input already exists, update its value.
      $('[name="wpzerospam_key"]', this).val(wpzerospam.key);
    } else {
      // Hidden input isn't present, add it.
      $(
        '<input type="hidden" name="wpzerospam_key" value="' +
          wpzerospam.key +
          '" />'
      ).appendTo(this);
    }
  };
})(jQuery);

// Initialize WPZS on form elements with the wpzerospam class.
jQuery(function() {
  jQuery(".wpzerospam").WordPressZeroSpam();
});
