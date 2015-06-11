<?php
namespace Shop;

use Framework\TimeStampedItem;

/**
 * Class WishList
 * @package Shop
 * @persist
 */
class WishList extends TimeStampedItem {
    /**
     * @var ProductQuantity[]
     * @persist
     */
    public $contents = [];
} 