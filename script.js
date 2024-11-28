jQuery(document).ready(function($) {
    const checkbox = $('#yfi-use-youtube');
    const inputField = $('#yfi-youtube-url');

    function toggleInput() {
        if (checkbox.is(':checked')) {
            inputField.show();
        } else {
            inputField.hide();
        }
    }

    toggleInput(); // Initial check
    checkbox.on('change', toggleInput);
});
