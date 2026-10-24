define(['jquery'], function ($) {
    'use strict';

    // Each item opens and closes on its own; the CSS handles the animation.
    return function (config, element) {
        $(element).on('click', '.product-acc__trigger', function () {
            var $item = $(this).closest('.product-acc__item'),
                open = !$item.hasClass('is-open');

            $item.toggleClass('is-open', open);
            $(this).attr('aria-expanded', open ? 'true' : 'false');
        });
    };
});
