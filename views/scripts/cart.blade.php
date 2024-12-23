@php use Seiger\sCommerce\Facades\sCommerce; @endphp
<script>
    document.addEventListener("click", function(e) {
        if (e.target) {
            switch(true) {
                case Boolean(e.target.closest('[data-sBuy]')?.hasAttribute("data-sBuy")):
                    productId = parseInt(e.target.closest('[data-sBuy]').getAttribute('data-sBuy'));
                    quantity = 1;
                    trigger = 'buy';
                    addToCart(e, productId, quantity, trigger);
                    break;
                case Boolean(e.target.closest('[data-sRemove]')?.hasAttribute("data-sRemove")):
                    productId = parseInt(e.target.closest('[data-sRemove]').getAttribute('data-sRemove'));
                    removeFromCart(e, productId);
                    break;
            }
        }
    });
    document.addEventListener("change", function(e) {
        if (e.target) {
            switch(true) {
                case Boolean(e.target?.hasAttribute("data-sQuantity")):
                    productId = parseInt(e.target.getAttribute('data-sQuantity'));
                    quantity = parseInt(e.target.value);
                    trigger = 'quantity';
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
            headers: {"X-Requested-With": "XMLHttpRequest"},
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
            document.dispatchEvent(new CustomEvent('sCommerceRemovedFromCart', {detail: data}));
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