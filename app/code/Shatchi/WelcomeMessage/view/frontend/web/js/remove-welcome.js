define(['jquery', 'domReady!'], function ($) {
    'use strict';

    const observer = new MutationObserver(() => {
        const welcomeBlock = $('li.greet.welcome');
        if (welcomeBlock.length) {
            welcomeBlock.remove(); // remove Knockout-rendered block
            observer.disconnect();
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
});
