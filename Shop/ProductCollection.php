<?php
namespace Shop;
use Framework\TimeStampedItem;

/**
 * Class ProductCollection
 * @package Shop
 * @persist
 */
class ProductCollection extends TimeStampedItem {
    /**
     * @var ProductType[]
     * @persist
     */
    public $products = [];
} 