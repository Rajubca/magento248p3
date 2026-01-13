define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/action/get-totals',
    'Magento_Checkout/js/model/full-screen-loader',
    'mage/cookies'
], function ($, customerData, getTotalsAction, fullScreenLoader) {
    'use strict';
    // Only run on Cart page; do nothing on Checkout or elsewhere.
    if (!document.body.classList.contains('checkout-cart-index')) {
        return;
    }

    function roundToStep(value, step, min) {
        var v = parseInt(value, 10);
        if (isNaN(v) || v < min) v = min;
        // Always snap to the nearest *higher* multiple so MOQ is respected
        var multiples = Math.ceil(v / step);
        return Math.max(min, multiples * step);
    }

    function sendUpdate($box, newQty, context) {
        var $row = $box.closest('tr.item-info');
        var itemId = parseInt($row.data('cart-item-id'), 10);
        var $qty = $box.find('input.qty');
        if (!itemId) return;

        fullScreenLoader.startLoader();
        $('body').addClass('cursor-wait');

        $.ajax({
            url: window.BASE_URL ? (window.BASE_URL + 'customcheckout/cart/updateitem') : '/customcheckout/cart/updateitem',
            type: 'POST',
            dataType: 'json',
            data: {
                item_id: itemId,
                qty: newQty,
                context: context || 'manual',
                form_key: $.mage.cookies.get('form_key')
            }
        }).done(function (res) {
            if (!res || res.error) {
                // Replace top messages if provided, else alert
                if (res && res.messages_html) {
                    $('#shatchi-cart-messages').html(res.messages_html);
                } else {
                    alert(res && res.message ? res.message : 'Failed to update cart.');
                }
                return;
            }

            // Applied qty from server (truth after salable/step capping)
            var applied = parseInt(res.applied_qty, 10);
            if (!isNaN(applied)) {
                $qty.val(applied);
            }
            if (res.free_delivery_html !== undefined) {
                $('#free-delivery-message').html(res.free_delivery_html);
            }

            // Update row subtotal cell (faster than reloading the whole row)
            if (res.row_subtotal_html) {
                $row.find('td.col.subtotal').html(res.row_subtotal_html);
            }
            if (res.surcharge_html !== undefined) {
                $('#surcharge-message').html(res.surcharge_html);
            }
            if (res.banner_html !== undefined) {
                $('#cart-top-banner').html(res.banner_html);
            }

            // Replace centralized messages (top)
            if (res.messages_html) {
                $('#shatchi-cart-messages').html(res.messages_html);
            }
            // ⬇️ Replace totals markup immediately if backend provided it
            if (res.cart_totals_html) {
                var $newTotals = $(res.cart_totals_html).filter('#cart-totals');
                if (!$newTotals.length) $newTotals = $(res.cart_totals_html).find('#cart-totals');
                if ($newTotals.length) $('#cart-totals').replaceWith($newTotals);
            }


            // Refresh mini-cart + checkout-data
            customerData.invalidate(['cart', 'checkout-data']);
            customerData.reload(['cart', 'checkout-data'], true);

            // Refresh totals/summary
            getTotalsAction([]);

            // Disable/enable +/- based on bounds if server sent max/min
            var step = parseInt($box.data('step'), 10) || 1;
            var min = parseInt($box.data('min'), 10) || step;
            var max = parseInt(res.max_qty || 0, 10); // 0 means unknown/unbounded

            // Persist max for future clicks if provided
            if (max > 0) $box.data('max', max);

            var cur = parseInt($qty.val(), 10);
            var $minus = $box.find('[data-action="qty-decrease"]');
            var $plus = $box.find('[data-action="qty-increase"]');

            $minus.prop('disabled', cur <= min);
            if (max > 0) {
                $plus.prop('disabled', cur >= max);
            } else {
                $plus.prop('disabled', false);
            }
        }).fail(function () {
            alert('Failed to update cart item.');
        }).always(function () {
            fullScreenLoader.stopLoader();
            $('body').removeClass('cursor-wait');
        });
    }

    // Debounce guard per box
    var lock = new WeakSet();

    function handleAdjust($box, dir) {
        if (lock.has($box[0])) return;

        var step = parseInt($box.data('step'), 10) || 1; // <-- MOQ from data-step
        var min = parseInt($box.data('min'), 10) || step;
        var max = parseInt($box.data('max') || 0, 10);
        var $qty = $box.find('input.qty');
        var cur = parseInt($qty.val(), 10) || 0;

        var next = cur + (dir === 'inc' ? step : -step); // <-- add/subtract MOQ
        next = roundToStep(next, step, min);
        if (max > 0 && next > max) next = max;

        if (next === cur) return;

        lock.add($box[0]);
        sendUpdate($box, next, dir === 'inc' ? 'plus' : 'minus');
        setTimeout(function () { lock.delete($box[0]); }, 200);
    }


    // Click +/-
    $(document).on('click', '.qty-box [data-action="qty-increase"]', function () {
        handleAdjust($(this).closest('.qty-box'), 'inc');
    });
    $(document).on('click', '.qty-box [data-action="qty-decrease"]', function () {
        handleAdjust($(this).closest('.qty-box'), 'dec');
    });

    // Manual change → round up to MOQ multiple and send
    $(document).on('change blur', '.qty-box input.qty', function () {
        var $box = $(this).closest('.qty-box');
        var step = parseInt($box.data('step'), 10) || 1;
        var min = parseInt($box.data('min'), 10) || step;
        var max = parseInt($box.data('max') || 0, 10);
        var want = roundToStep($(this).val(), step, min);
        if (max > 0 && want > max) want = max;
        if (parseInt($(this).val(), 10) !== want) $(this).val(want);
        sendUpdate($box, want, 'manual');
    });

});
