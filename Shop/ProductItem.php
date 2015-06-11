<?php
namespace Shop;

use Framework\TimeStampedItem;

/**
 * Class ProductItem
 * @package Shop
 * @persist
 */
class ProductItem extends TimeStampedItem {
    /**
     * @var ProductType
     * @persist
     */
    public $productType;


}