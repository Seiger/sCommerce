@use(Seiger\sCommerce\Facades\sWishlist)
<script>
    document.addEventListener("click", async function(e) {
        const target = e.target.closest(
            '[data-sc-wishlist], ' +
            // TODO: REMOVE IN v1.5 - Deprecated camelCase attributes (data-s*)
            '[data-sWishlist], [data-swishlist]'
        );
        if (!target) return;

        const ds = target.dataset;
        let pId;

        // TODO: REMOVE IN v1.5 - Deprecated: Check for old camelCase attributes and warn
        const {scWishlist, sWishlist, swishlist} = ds;
        if (typeof console !== 'undefined' && console.warn) {
            const deprecatedMap = {
                sWishlist:{old:'data-sWishlist', new:'data-sc-wishlist'},
                swishlist:{old:'data-sWishlist', new:'data-sc-wishlist'}
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

        if (pId = ds.scWishlist ?? ds.sWishlist ?? ds.swishlist) {
            e.preventDefault();
            if ('disabled' in e.target) e.target.disabled = true;

            let form = new FormData();
            form.append('product', parseInt(pId));
            let result = await callApi('{{route('sCommerce.wishlist')}}', form);

            if (result.success == 1) {
                // TODO: REMOVE IN v1.5 - Deprecated: Check for old camelCase attributes and warn
                target?.classList?.remove('sWishlist');

                target?.classList?.remove('sc-wishlist');
                setWishlist(result.products);
            }

            sCommerce.trigger('Wishlist', result);
            if ('disabled' in e.target) e.target.disabled = false;
        }
    });
    setWishlist(@json(sWishlist::getWishlist()));
    function setWishlist(products) {
        const selectors = [
            '[data-sc-wishlist]',
            // TODO: REMOVE IN v1.5 - Deprecated camelCase attributes
            '[data-sWishlist]',
            '[data-swishlist]'
        ].join(', ');

        document.querySelectorAll(selectors).forEach((el) => {
            const pId = el.dataset.scWishlist ?? el.dataset.sWishlist ?? el.dataset.swishlist;
            if (!pId) return;

            products.forEach((product) => {
                if (parseInt(pId) === parseInt(product)) {
                    // TODO: REMOVE IN v1.5 - Deprecated class name
                    el.classList.add('sWishlist');
                    el.classList.add('sc-wishlist');
                }
            });
        });
    }
</script>