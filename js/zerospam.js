(function($) {
    "use strict";
    $(function() {
        var forms = "#commentform";
        forms += ", #registerform";
        forms += ", .wpcf7-form";
        forms += ", .gform_wrapper form";
        forms += ", #buddypress #signup_form";
        forms += ", .zerospam";
        forms += ", .ninja-forms-form";
        if (typeof zerospam.key != "undefined") {
            $(forms).on("submit", function() {
                $("<input>").attr("type", "hidden").attr("name", "zerospam_key").attr("value", zerospam.key).appendTo(forms);
                return true;
            });
        }
    });
})(jQuery);