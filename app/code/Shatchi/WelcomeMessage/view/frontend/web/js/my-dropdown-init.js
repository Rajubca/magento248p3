define(['jquery'], function ($) {
    'use strict';

    return function (config, element) {
        const $dropdown = $(element);
        const $toggle = $dropdown.find('.dropdown-toggle');
        const $menu = $dropdown.find('.dropdown-menu');

        $toggle.on('click', function (e) {
            e.preventDefault();
            $menu.stop(true, true).fadeToggle(200);
        });

        $(document).on('click', function (e) {
            if (!$dropdown.is(e.target) && $dropdown.has(e.target).length === 0) {
                $menu.stop(true, true).fadeOut(200);
            }
        });
    };
});		
