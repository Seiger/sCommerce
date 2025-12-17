@use(Seiger\sCommerce\Facades\sCommerce)
<script>
    if (!window.sCommerce) {
        window.sCommerce = {};
    }

    window.sCommerce.trigger = function(event, data) {
        const callback = this[`on${event}`];
        if (typeof callback === 'function') {
            try {
                callback(data);
            } catch (error) {
                console.error(`sCommerce.on${event} error:`, error);
            }
        }

        // ============================================
        // @deprecated
        // @since 1.0
        // @todo [remove@1.5] Remove in sCommerce v1.5
        // Backward compatibility for CustomEvent API
        // Migration: Use sCommerce.onEventName = (data) => {}
        // instead of document.addEventListener('sCommerceEventName', ...)
        // ============================================
        if (typeof console !== 'undefined' && console.warn) {
            const eventMap = {
                'AddedToCart': 'sCommerceAddedToCart',
                'RemovedFromCart': 'sCommerceRemovedFromCart',
                'UpdatedCart': 'sCommerceUpdatedCart',
                'FastOrder': 'sCommerceAddedFastOrder',
                'Wishlist': 'sCommerceSetWishlist',
            };
            const oldEventName = eventMap[event];
            if (oldEventName) {
                const listeners = document._customEventListeners?.[oldEventName];
                if (listeners && listeners.length > 0) {
                    console.warn(
                        `[sCommerce Deprecation Warning] CustomEvent '${oldEventName}' is deprecated and will be removed in v1.5. ` +
                        `Use sCommerce.on${event} = (data) => {} instead.`
                    );
                }
            }
        }
        try {
            document.dispatchEvent(new CustomEvent(`sCommerce${event}`, { detail: data }));
        } catch (error) {
            console.error(`CustomEvent sCommerce${event} error:`, error);
        }
        // ============================================
        // END DEPRECATED CODE - REMOVE IN v1.5
        // ============================================
    };

    // ============================================
    // Event Listeners
    // ============================================
    document.addEventListener('click', (e) => {
        const target = e.target.closest(
            '[data-sc-buy], [data-sc-fast-buy], [data-sc-remove], ' +
            // TODO: REMOVE IN v1.5 - Deprecated camelCase attributes (data-s*)
            '[data-sBuy], [data-sFastBuy], [data-sRemove], [data-sbuy], [data-sfastbuy], [data-sremove]'
        );
        if (!target) return;

        const ds = target.dataset;
        let pId;

        // TODO: REMOVE IN v1.5 - Deprecated: Check for old camelCase attributes and warn
        const {scBuy, sBuy, scFastBuy, sFastBuy, scRemove, sRemove, sbuy, sfastbuy, sremove} = ds;
        if (typeof console !== 'undefined' && console.warn) {
            const deprecatedMap = {
                sBuy:{old:'data-sBuy', new:'data-sc-buy'},
                sbuy:{old:'data-sBuy', new:'data-sc-buy'},
                sFastBuy:{old:'data-sFastBuy', new:'data-sc-fast-buy'},
                sfastbuy:{old:'data-sFastBuy', new:'data-sc-fast-buy'},
                sRemove:{old:'data-sRemove', new:'data-sc-remove'},
                sremove:{old:'data-sRemove', new:'data-sc-remove'}
            };

            for (const [key, {old, new: newAttr}] of Object.entries(deprecatedMap)) {
                if (ds[key]) {
                    console.warn(
                        `[sCommerce Deprecation Warning] Attribute '${old}' is deprecated and will be removed in v1.5. ` +
                        `Use '${newAttr}' instead.`
                    );
                    break;
                }
            }
        }

        // Buy
        if (pId = ds.scBuy ?? ds.sBuy ?? ds.sbuy) {
            const quantity = parseInt(target.parentElement?.querySelector('[type="number"]')?.value || 1);
            return addToCart(e, parseInt(pId), quantity, 'buy');
        }

        // Fast Buy
        if (pId = ds.scFastBuy ?? ds.sFastBuy ?? ds.sfastbuy) {
            const inputs = target.parentElement?.querySelectorAll('input');
            return fastOrder(e, parseInt(pId), inputs);
        }

        // Remove
        if (pId = ds.scRemove ?? ds.sRemove ?? ds.sremove) {
            return removeFromCart(e, pId);
        }
    });

    document.addEventListener('change', (e) => {
        const {scQuantity, sQuantity, squantity} = e.target.dataset;
        const pId = scQuantity ?? sQuantity;

        // TODO: REMOVE IN v1.5 - Deprecated: Check for old camelCase attribute and warn
        if (typeof console !== 'undefined' && console.warn && sQuantity && !scQuantity) {
            console.warn(
                `[sCommerce Deprecation Warning] Attribute 'data-s-quantity' is deprecated and will be removed in v1.5. ` +
                `Use 'data-sc-quantity' instead.`
            );
        }

        if (pId) {
            return addToCart(e, parseInt(pId), parseInt(e.target.value), 'quantity');
        }
    });

    // ============================================
    // Cart Functions
    // ============================================
    function addToCart(e, productId, quantity, trigger) {
        e.preventDefault();
        e.target.disabled = true;

        let form = new FormData();
        form.append('productId', productId);
        form.append('quantity', quantity);
        form.append('trigger', trigger);

        fetch('{{route('sCommerce.addToCart')}}', {
            method: "post",
            cache: "no-store",
            headers: {"X-Requested-With": "XMLHttpRequest"},
            body: form
        }).then((response) => {
            return response.json();
        }).then((data) => {
            sCommerce.trigger(trigger === 'quantity' ? 'UpdatedCart' : 'AddedToCart', data);
            e.target.disabled = false;
        }).catch(function(error) {
            if (error === 'SyntaxError: Unexpected token < in JSON at position 0') {
                console.error('Request failed SyntaxError: The response must contain a JSON string.');
            } else {
                console.error('Request failed', error, '.');
            }
            e.target.disabled = false;
        });
    }
    function fastOrder(e, productId, inputs) {
        e.preventDefault();
        e.target.disabled = true;

        let form = new FormData();
        form.append('productId', productId);
        inputs.forEach((input) => {
            form.append(input.name, input.value);
        });

        fetch('{{route('sCommerce.quickOrder')}}', {
            method: "post",
            cache: "no-store",
            headers: {"X-Requested-With": "XMLHttpRequest"},
            body: form
        }).then((response) => {
            return response.json();
        }).then((data) => {
            sCommerce.trigger('FastOrder', data);
            e.target.disabled = false;
        }).catch(function(error) {
            if (error === 'SyntaxError: Unexpected token < in JSON at position 0') {
                console.error('Request failed SyntaxError: The response must contain a JSON string.');
            } else {
                console.error('Request failed', error, '.');
            }
            e.target.disabled = false;
        });
    }
    function removeFromCart(e, productId) {
        e.preventDefault();
        e.target.disabled = true;

        let form = new FormData();
        form.append('productId', productId);

        fetch('{{route('sCommerce.removeFromCart')}}', {
            method: "post",
            cache: "no-store",
            headers: {"X-Requested-With": "XMLHttpRequest"},
            body: form
        }).then((response) => {
            return response.json();
        }).then((data) => {
            sCommerce.trigger('RemovedFromCart', data);
            e.target.disabled = false;
        }).catch(function(error) {
            if (error === 'SyntaxError: Unexpected token < in JSON at position 0') {
                console.error('Request failed SyntaxError: The response must contain a JSON string.');
            } else {
                console.error('Request failed', error, '.');
            }
            e.target.disabled = false;
        });
    }
</script>