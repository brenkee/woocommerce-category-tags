(function ($) {
    $(document).ready(function () {
        var form = $(wctagsFilter.formSelector);
        var buttons = $(wctagsFilter.buttonSelector);

        if (!form.length || !buttons.length) {
            return;
        }

        buttons.on('click', function (event) {
            event.preventDefault();

            var selectedTag = $(this).data('tag');
            form.find('input[name="product_tag"]').val(selectedTag);

            buttons.removeClass('is-active');
            $(this).addClass('is-active');

            form.trigger('submit');
        });
    });
})(jQuery);
