<?php
namespace Shop;

use Framework\TimeStampedItem;

/**
 * Class VisitorSession
 * @package Shop
 * @persist
 */
class VisitorSession {
    /**
     * @var string
     * @persist
     * @role id
     * @editor readonly
     */
    public $sessionId;

    /* DEV NOTE:
     * VisitorSession does not use integer IDs, so it does not extend Framework\TimeStampedItem.
     * Instead, it implements its own versions of $stamp_created and $stamp_modified, using
     * the callbacks supplied in the TimeStampedItem class.
     */

    /**
     * @label Created
     * @var int
     * @content date
     * @persist onStore=\Framework\TimeStampedItem::persistence_stamp_created
     * @editor readonly
     */
    public $stamp_created;

    /**
     * @label Modified
     * @var int
     * @content date
     * @persist onStore=\Framework\TimeStampedItem::persistence_stamp_modified
     * @editor readonly
     */
    public $stamp_modified;

    /**
     * Stores the cookie persistence mode for the session.
     *
     * null for default behaviour.
     * false to delete cookie after an hour of inactivity.
     * true to keep cookie and session indefinitely.
     *
     * @var null|boolean
     * @persist
     */
    public $persistentCookie = null;

    /**
     * @var null|Customer
     * @persist
     * @editor readonly
     */
    public $customer = null;

    /**
     * @var null|ShoppingBasket
     * @persist
     * @editor readonly
     */
    public $shoppingBasket = null;

    /**
     * @var null|WishList
     * @persist
     * @editor readonly
     */
    public $wishList = null;
}