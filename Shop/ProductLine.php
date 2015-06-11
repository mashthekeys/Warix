<?php
namespace Shop;

use Framework\TimeStampedItem;

/**
 * Class ProductLine
 * @package Shop
 * @persist
 */
class ProductLine extends TimeStampedItem {
    /**
     * TODO unique should cause the MM table to have a unique index on (local, foreign)
     * TODO indexReverse should cause the MM table to be indexed on (foreign)
     * @var ProductCategory[]
     * @persist unique,indexReverse
     */
    public $categories = [];
} 