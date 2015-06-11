<?php
namespace Shop;

use Framework\PersistenceDB;
use Framework\Query;
use Framework\TimeStampedItem;

/**
 * Class ProductCategory
 * @package Shop
 * @persist
 */
class ProductCategory extends TimeStampedItem {

    // DEV NOTE: This is an example of how Framework 2.0 could use persistence.
    // In this case, the missing function body would be supplied by Framework.
//    /**
//     * @persist reflect=ProductLine::$categories
//     * @return ProductLine[]
//     */
//    public function getProductLines() {}
} 