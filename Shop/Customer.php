<?php
namespace Shop;

use Framework\TimeStampedItem;

/**
 * Class Customer
 * @package Shop
 * @persist
 */
class Customer extends TimeStampedItem {
    /**
     * @var string
     * @persist
     */
    public $name;

    /**
     * @var CustomerAddress[]
     * @persist reflect=CustomerAddress::$customer
     */
    public $addresses;


    /**
     * Stores the default cookie persistence preference for the user.
     *
     * null for default behaviour.
     * false to delete cookie after an hour of inactivity.
     * true to keep cookie and session indefinitely.
     *
     * When the customer logs in, the session persistence will be set to this,
     * unless the user already selected a different persistence mode before
     * logging in.
     *
     * @var null|boolean
     * @persist
     */
    public $persistentCookie = null;

    /**
     * @var WishList[]
     * @persist
     */
    public $wishLists;


} 