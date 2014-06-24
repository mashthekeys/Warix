<?php
namespace Framework;

/**
 * Class TimeStampedItem
 * @Framework
 */
abstract class TimeStampedItem {
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
     * @label Modified
     * @var int
     * @content date
     * @persist onStore=\Framework\TimeStampedItem::persistence_stamp_modified
     * @editor readonly
     */
    public $stamp_modified;

    public static function persistence_stamp_created($item) {
        if (!($item->stamp_created > 0)) {
            $item->stamp_created = time();
        }
    }

    public static function persistence_stamp_modified($item) {
        $item->stamp_modified = time();
    }
}