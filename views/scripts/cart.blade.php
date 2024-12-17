@php use Seiger\sCommerce\Facades\sCommerce; @endphp
<script>
    document.addEventListener("click", function(e) {
        if (e.target) {
            switch(true) {
                case Boolean(e.target.closest('button')?.hasAttribute("data-sBuy")):
                    let productId = parseInt(e.target.closest('button').getAttribute('data-sBuy'));
                    let quantity = 1;
                    let trigger = 'buy';
                    addToCart(e, productId, quantity, trigger);
                    break;
            }
        }
    });
    document.addEventListener("change", function(e) {
        if (e.target) {
            switch(true) {
                case Boolean(e.target?.hasAttribute("data-sQuantity")):
                    let productId = parseInt(e.target.getAttribute('data-sQuantity'));
                    let quantity = parseInt(e.target.value);
                    let trigger = 'quantity';
                    addToCart(e, productId, quantity, trigger);
                    break;
            }
        }
    });
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
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            },
            body: form
        }).then((response) => {
            return response.json();
        }).then((data) => {
            document.dispatchEvent(new CustomEvent('sCommerceAddedToCart', {detail: data}));
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