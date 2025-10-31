<?php namespace Seiger\sCommerce\Wishlist;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Seiger\sCommerce\Facades\sCommerce;

class sWishlist
{
    protected $data;

    public function __construct()
    {
        $this->data = $this->loadData();
    }

    /**
     * Retrieves the list of user's wishlist products.
     *
     * @return array
     */
    public function getWishlist(): array
    {
        return $this->data ?? [];
    }

    /**
     * Adds a product to the user's wishlist.
     */
    public function updateWishlist(array $data): array
    {
        $product = (int)$data['product'];
        $validator = Validator::make($data, [
            'product' => 'nullable|integer|exists:s_products,id' . ($product == 0 ? ',0' : null),
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => 'Invalid data provided.',
                'errors' => $validator->errors(),
            ];
        }

        if (in_array($product, $this->data)) {
            $flipped = array_flip($this->data);
            unset($flipped[$product]);
            $this->data = array_keys($flipped);
            $event = 'remove';
            $message = 'Product remove from wishlist';
        } else {
            $this->data[] = $product;
            $event = 'add';
            $message = 'Product added to wishlist';
        }

        $userId = evo()->getLoginUserID('web') ?: evo()->getLoginUserID('mgr');

        if ((int)$userId) {
            DB::table('user_attributes')->where('internalKey', $userId)->update(['wishlist' => json_encode($this->data)]);
        }

        $_SESSION['sWishlist'] = $this->data;

        return [
            'success' => true,
            'event' => $event,
            'message' => $message,
            'products' => $this->data,
        ];
    }

    /**
     * Load wishlist data from the session or database.
     *
     * @return array The wishlist data.
     */
    protected function loadData(): array
    {
        if (isset($_SESSION['sWishlist']) && !empty($_SESSION['sWishlist'])) {
            return $_SESSION['sWishlist'];
        }

        $userId = evo()->getLoginUserID('web') ?: evo()->getLoginUserID('mgr');

        if ((int)$userId) {
            $user = evo()->getUserInfo($userId ?: 0) ?: [];
            $wishlist = json_decode($user['wishlist'] ?? '', true) ?? [];
            $_SESSION['sWishlist'] = $wishlist;
        }

        return $_SESSION['sWishlist'] ?? [];
    }
}
