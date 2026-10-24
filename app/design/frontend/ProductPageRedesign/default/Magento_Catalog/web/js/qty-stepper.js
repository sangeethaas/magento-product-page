define(['jquery'], function ($) {
    'use strict';

    return function (config, element) {
        var $control = $(element),
            $input = $control.find('input.qty');

        $control.on('click', '.qty-btn', function () {
            var current = parseFloat($input.val()) || 0,
                next = $(this).hasClass('qty-plus') ? current + 1 : Math.max(1, current - 1);

            $input.val(next).trigger('change');
        });
    };
});
