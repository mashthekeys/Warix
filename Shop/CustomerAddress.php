<?php
namespace Shop;
use Framework\TimeStampedItem;

/**
 * TODO this should be immutable, and 'edits' should be saved as new addresses
 * @package Shop
 * @persist immutable
 */
class CustomerAddress {
    /**
     * @var int
     * @role id
     * @persist
     * @editor hidden,readonly
     */
    public $id;

    /**
     * @label Created
     * @var int
     * @content date
     * @persist onStore=\Framework\TimeStampedItem::persistence_stamp_created
     * @editor readonly
     */
    public $stamp_created;

    /**
     * @var null|Customer
     * @persist
     */
    public $customer;

    /**
     * @var string
     * @persist length=1024
     */
    public $address;

    /**
     * @var string
     * @persist length=100
     */
    public $contactPhone;

    /**
     * @var string
     * @persist length=1024
     */
    public $specialInstructions;
}