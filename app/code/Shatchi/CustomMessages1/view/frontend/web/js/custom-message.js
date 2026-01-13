define(['jquery'], function ($) {
    'use strict';

    // ✅ Do nothing outside category pages (prevents checkout impact)
    // if (!document.body.classList.contains('catalog-category-view')) {
    //     return;
    // }
    
    $(function () {
        // ✅ Only proceed if on category page
        if (!$('body').hasClass('catalog-category-view')) {
            return;
        }

        // const messageQueue = JSON.parse(sessionStorage.getItem('popupQueue') || '[]');
        // let showing = false;
        const queue = [];

        // ✅ Auto-detect position from DOM
        let popupPosition = $('#custom-popup-message').data('position') || 'top-right';

        const allowedPositions = ['top-left', 'top-center', 'top-right', 'bottom-left', 'bottom-center', 'bottom-right'];
        if (!allowedPositions.includes(popupPosition)) {
            popupPosition = 'top-right';
        }

        const svgIcons = {
            success: `<svg class="popup-icon" fill="#28a745" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM6.97 11.03a.75.75 0 0 0 1.06 0L13 6.06l-1.06-1.06-4.47 4.47-2.47-2.47L4.94 8l2.03 2.97z"/></svg>`,
            error: `<svg class="popup-icon" fill="#dc3545" viewBox="0 0 16 16"><path d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0zM4.646 4.646l2.828 2.828-2.828 2.828 1.414 1.414 2.828-2.828 2.828 2.828 1.414-1.414-2.828-2.828 2.828-2.828-1.414-1.414-2.828 2.828L6.06 3.232 4.646 4.646z"/></svg>`,
            warning: `<svg class="popup-icon" fill="#ffc107" viewBox="0 0 16 16"><path d="M8.982 1.566a1.5 1.5 0 0 0-2.964 0L.165 13.233A1.5 1.5 0 0 0 1.5 15h13a1.5 1.5 0 0 0 1.335-2.233L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1-2.002 0 1 1 0 0 1 2.002 0z"/></svg>`,
            notice: `<svg class="popup-icon" fill="#17a2b8" viewBox="0 0 16 16"><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-12.412a.5.5 0 0 1-.854-.353v-.875a.5.5 0 0 1 1 0v.875a.5.5 0 0 1-.146.353zM8 5a.5.5 0 0 1 .496.438l.39 4.692a.5.5 0 0 1-.992.092L7.5 5.5a.5.5 0 0 1 .5-.5z"/></svg>`
        };

        const $stackContainer = $(`<div class="custom-popup-stack ${popupPosition}" id="popup-stack"></div>`);
        $('body').append($stackContainer);

        $(document).ajaxComplete(function (event, xhr) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response && response.messages && response.messages.messages) {
                    response.messages.messages.forEach(function (msg) {
                        queue.push({ text: msg.text, type: msg.type });
                        updateStack();
                    });
                }
            } catch (e) {
                // Ignore non-JSON responses
            }
        });

        function updateStack() {
            $('#popup-stack').empty();
            const maxVisible = 5;
            queue.slice(-maxVisible).forEach((msg, index, visibleQueue) => {
                const stackIndex = visibleQueue.length - 1 - index;
                let popupStyle = {
                    transform: `rotate(${stackIndex * 2}deg)`,
                    zIndex: 9999 - stackIndex
                };

                if (popupPosition.includes('top')) {
                    popupStyle.top = `${30 + stackIndex * 20}px`;
                } else {
                    popupStyle.bottom = `${30 + stackIndex * 20}px`;
                }

                if (popupPosition.includes('right')) {
                    popupStyle.right = `${30 + stackIndex * 10}px`;
                } else if (popupPosition.includes('left')) {
                    popupStyle.left = `${30 + stackIndex * 10}px`;
                } else if (popupPosition.includes('center')) {
                    popupStyle.left = '50%';
                    popupStyle.transform += ` translateX(-50%) translateY(${stackIndex * 4}px)`;
                }

                const $popup = $('<div>', {
                    class: `custom-popup ${msg.type} ${stackIndex === 0 ? '' : 'stacked'}`
                }).css(popupStyle)
                    .append(
                        $(svgIcons[msg.type] || ''),
                        $('<div>', { class: 'message-text', html: msg.text }),
                        $('<button>', {
                            class: 'close-popup',
                            html: '×',
                            click: function () {
                                queue.splice(queue.length - visibleQueue.length + index, 1);
                                updateStack();
                            }
                        })
                    );

                $('#popup-stack').append($popup);
                if (stackIndex === 0) {
                    $popup.addClass('slideIn');
                    setTimeout(() => $popup.removeClass('slideIn'), 400);
                }
            });
        }
    });
});
