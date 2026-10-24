define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'mage/cookies'
], function ($, customerData) {
    'use strict';

    var STORAGE_KEY = 'guest_wishlist_products',
        ACTION = '[data-action="add-to-wishlist"]',
        customer = customerData.get('customer'),
        merging = false;

    /**
     * @return {Number[]}
     */
    function read() {
        try {
            return JSON.parse(window.localStorage.getItem(STORAGE_KEY)) || [];
        } catch (e) {
            return [];
        }
    }

    /**
     * @param {Number[]} ids
     */
    function write(ids) {
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
        } catch (e) {
            // storage unavailable: nothing to remember
        }
    }

    /**
     * @param {HTMLElement} el
     * @return {Number|null}
     */
    function productIdOf(el) {
        var post = $(el).data('post');

        if (typeof post === 'string') {
            try {
                post = JSON.parse(post);
            } catch (e) {
                post = null;
            }
        }

        return post && post.data && post.data.product ? parseInt(post.data.product, 10) : null;
    }

    function isLoggedIn() {
        var data = customer();

        return !!(data && data.firstname);
    }

    function message(type, text) {
        customerData.set('messages', {
            messages: [{type: type, text: text}]
        });
    }

    function markSaved() {
        var ids = read();

        $(ACTION).each(function () {
            $(this).toggleClass('is-wishlisted', ids.indexOf(productIdOf(this)) !== -1);
        });
    }

    // Guests: keep the product in the browser instead of redirecting to the login page.
    document.addEventListener('click', function (event) {
        var link = event.target.closest ? event.target.closest(ACTION) : null,
            id, ids, index;

        if (!link || isLoggedIn()) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        id = productIdOf(link);
        if (!id) {
            return;
        }

        ids = read();
        index = ids.indexOf(id);

        if (index === -1) {
            ids.push(id);
            message('success', 'Saved to your wishlist. <a href="' + BASE_URL + 'guestwishlist">View wishlist</a>');
        } else {
            ids.splice(index, 1);
            message('success', 'Removed from your wishlist.');
        }

        write(ids);
        markSaved();
    }, true);

    // After login: push the saved products into the customer's wishlist.
    function mergeIfLoggedIn() {
        var ids = read();

        if (merging || !isLoggedIn() || !ids.length) {
            return;
        }

        merging = true;

        $.ajax({
            url: BASE_URL + 'guestwishlist/index/merge',
            type: 'POST',
            dataType: 'json',
            data: {
                products: ids,
                form_key: $.mage.cookies.get('form_key')
            }
        }).done(function (response) {
            write([]);
            markSaved();
            customerData.invalidate(['wishlist']);
            customerData.reload(['wishlist'], true);

            if (response && response.added) {
                message('success', response.added + ' saved item(s) added to your wishlist.');
            }
        }).always(function () {
            merging = false;
        });
    }

    customer.subscribe(mergeIfLoggedIn);
    mergeIfLoggedIn();
    $(markSaved);
    $(document).on('contentUpdated ajaxComplete', markSaved);

    return {};
});
