@php use Seiger\sCommerce\Facades\sWishlist; @endphp
<script>
    document.addEventListener("click", async function(e) {
        if (e.target) {
            if (Boolean(e.target.closest('[data-sWishlist]')?.hasAttribute("data-sWishlist"))) {
                e.preventDefault();
                let clickedElement = e.target.closest('[data-sWishlist]');
                if ('disabled' in e.target) e.target.disabled = true;
                productId = parseInt(clickedElement.getAttribute('data-sWishlist')) || 0;

                let form = new FormData();
                form.append('product', productId);
                let result = await callApi('{{route('sCommerce.wishlist')}}', form);

                if (result.success == 1) {
                    clickedElement.classList.remove('sWishlist');
                    setWishlist(result.products);
                }

                document.dispatchEvent(new CustomEvent('sCommerceSetWishlist', {detail: result}));

                if ('disabled' in e.target) e.target.disabled = false;
            }
        }
    });
    setWishlist(@json(sWishlist::getWishlist()));
    function setWishlist(products) {
        document.querySelectorAll('[data-sWishlist]').forEach((el) => {
            products.forEach((product) => {
                if (parseInt(el.getAttribute('data-sWishlist')) === parseInt(product)) {
                    el.classList.add('sWishlist');
                }
            });
        });
    }
</script>