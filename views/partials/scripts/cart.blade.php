@php use Seiger\sCommerce\Facades\sCommerce; @endphp
<script>
    document.addEventListener("click", function(e) {
        if (e.target) {
            switch(true) {
                case Boolean(e.target.closest('button')?.hasAttribute("data-buy")):
                    addToCart(e);
                    break;
            }
        }
    });
    function addToCart(e) {
        e.preventDefault();
        e.target.disabled = true;
        let count = 1;
        let productId = parseInt(e.target.closest('button').getAttribute('data-buy'));

        let form = new FormData();
        form.append('productId', productId);
        form.append('count', count);

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
            document.dispatchEvent(new CustomEvent('sCommerce.addedToCart', {detail: data}));
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