<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 24/07/2014
 * Time: 16:27
 */

namespace Shop;


class CustomerOrder {
    /**
     * The customer who placed this order.
     *
     * If the order was placed without registration, this will be null.
     *
     * Values such as name and address remain as placed at the time of the order,
     * and do not update when if the customer later changes them in the settings panel.
     *
     * @var null|Customer
     * @persist
     */
    public $customer;

    /**
     * @var string
     * @persist
     */
    public $billingName;

    /**
     * @var CustomerAddress
     * @persist
     */
    public $billingAddress;

    /**
     * @var string
     * @persist
     */
    public $deliveryName;

    /**
     * @var CustomerAddress
     * @persist
     */
    public $deliveryAddress;

    //...
}