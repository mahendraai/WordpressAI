jQuery(document).ready(function($) {
    // Check if the user has accepted the age verification
    if (document.cookie.indexOf("avp_accepted") == -1) {
        // Show popup if not accepted
        $('#avp-popup').fadeIn();
    }

    // Close the popup when the button is clicked
    $('#avp-close-btn').click(function() {
        $.post(window.location.href, { avp_accept: true }, function() {
            $('#avp-popup').fadeOut();
        });
    });
});

