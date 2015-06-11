<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 24/07/2014
 * Time: 14:25
 */

namespace Shop;

/**
 * Stores an abstract set of data values needed to identify a product selection of some kind.
 *
 * TODO The persistence framework will store this in 'embedded' mode, which means that it its values
 * are stored directly in the table which uses it, rather than being loaded by its ID.
 *
 * @package Shop
 * @persist embedded
 */
class ProductQuantity extends ProductType {
    /**
     * @var int
     * @persist
     */
    public $quantity;
}