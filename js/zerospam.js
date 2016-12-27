(function($) {
    "use strict";
    $(function() {
        var forms = "#commentform";
        forms += ", #contactform";
        forms += ", #registerform";
        forms += ", #buddypress #signup_form";
        forms += ", .zerospam";
        forms += ", .ninja-forms-form";
        forms += ", .wpforms-form";
        forms += ", .gform_wrapper form";
        if (typeof zerospam.key != "undefined") {
            $(forms).on("submit", function() {
                $("<input>").attr("type", "hidden").attr("name", "zerospam_key").attr("value", zerospam.key).appendTo(forms);
                return true;
            });
            $(document).on("gform_post_render", function() {
                $("<input>").attr("type", "hidden").attr("name", "zerospam_key").attr("value", zerospam.key).appendTo(".gform_wrapper form ");
            });
            $(".wpcf7-submit").click(function() {
                $("<input>").attr("type", "hidden").attr("name", "zerospam_key").attr("value", zerospam.key).appendTo(".wpcf7-form");
            });
        }
    });
})(jQuery);