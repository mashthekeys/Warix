<?php
namespace Shop;
use Framework\TimeStampedItem;

/**
 * Class ProductVariant
 * @package Shop
 * @persist
 */
class ProductVariant extends TimeStampedItem {
    /**
     * @var ProductLine
     * @persist
     */
    public $productLine;

    /**
     * @var string
     * @persist
     */
    public $codename;

    /**
     * @var string
     * @persist
     */
    public $name;
} 