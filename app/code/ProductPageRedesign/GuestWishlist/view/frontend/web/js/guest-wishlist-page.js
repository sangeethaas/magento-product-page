define(['jquery', 'mage/cookies'], function ($) {
    'use strict';

    var STORAGE_KEY = 'guest_wishlist_products';

    function read() {
        try {
            return JSON.parse(window.localStorage.getItem(STORAGE_KEY)) || [];
        } catch (e) {
            return [];
        }
    }

    function write(ids) {
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
        } catch (e) {
            // storage unavailable
        }
    }

    return function (config, element) {
        var $root = $(element),
            labels = config.labels;

        function renderEmpty() {
            $root.empty().append(
                $('<p class="guest-wishlist__empty">').text(labels.empty),
                $('<a class="action primary guest-wishlist__continue">').attr('href', config.homeUrl).text(labels.continue)
            );
        }

        function addToCart(item) {
            var $form = $('<form method="post">').attr('action', config.addToCartUrl).hide();

            $form.append(
                $('<input type="hidden" name="product">').val(item.id),
                $('<input type="hidden" name="qty" value="1">'),
                $('<input type="hidden" name="form_key">').val($.mage.cookies.get('form_key'))
            ).appendTo('body').trigger('submit');
        }

        function card(item) {
            var $card = $('<li class="guest-wishlist__item">'),
                $price = $('<div class="guest-wishlist__price">'),
                $actions = $('<div class="guest-wishlist__actions">');

            if (item.old_price) {
                $price.append($('<span class="guest-wishlist__old">').text(item.old_price));
            }
            $price.append($('<span class="guest-wishlist__now">').text(item.price));

            if (!item.saleable) {
                $actions.append($('<span class="guest-wishlist__oos">').text(labels.outOfStock));
            } else if (item.quick_add) {
                $actions.append(
                    $('<button type="button" class="action primary tocart">').text(labels.addToCart).on('click', function () {
                        addToCart(item);
                    })
                );
            } else {
                $actions.append($('<a class="action primary">').attr('href', item.url).text(labels.view));
            }

            $actions.append(
                $('<button type="button" class="guest-wishlist__remove">').text(labels.remove).on('click', function () {
                    var ids = read().filter(function (id) {
                        return id !== item.id;
                    });

                    write(ids);
                    $card.remove();

                    if (!ids.length) {
                        renderEmpty();
                    }
                })
            );

            return $card.append(
                $('<a class="guest-wishlist__image">').attr('href', item.url).append($('<img>').attr({src: item.image, alt: item.name})),
                $('<a class="guest-wishlist__name">').attr('href', item.url).text(item.name),
                $price,
                $actions
            );
        }

        function render(items) {
            var $list = $('<ul class="guest-wishlist__list">');

            if (!items.length) {
                renderEmpty();

                return;
            }

            items.forEach(function (item) {
                $list.append(card(item));
            });

            $root.empty().append(
                $('<p class="guest-wishlist__signin">').append(
                    document.createTextNode(labels.signIn + ' '),
                    $('<a>').attr('href', config.loginUrl).text(labels.signInLink)
                ),
                $list
            );
        }

        var ids = read();

        if (!ids.length) {
            renderEmpty();

            return;
        }

        $.getJSON(config.productsUrl, {products: ids}).done(function (response) {
            var found = (response.items || []).map(function (item) {
                return item.id;
            });

            // forget products that no longer exist or are disabled
            write(ids.filter(function (id) {
                return found.indexOf(id) !== -1;
            }));
            render(response.items || []);
        }).fail(renderEmpty);
    };
});
