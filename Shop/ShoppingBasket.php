<?php
namespace Shop;

use Framework\TimeStampedItem;

/**
 * Class ShoppingBasket
 * @package Shop
 * @persist
 */
class ShoppingBasket extends TimeStampedItem {
    /**
     * @var ProductQuantity[]
     * @persist
     */
    public $contents = [];
}