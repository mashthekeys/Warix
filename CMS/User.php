<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 03/06/2014
 * Time: 01:09
 */

namespace CMS;

use Framework\PersistenceDB;
use Framework\Query;
use \Framework\TimeStampedItem;

require_once __DIR__.DIRECTORY_SEPARATOR.'lib/phpass-0.3/PasswordHash.php';

/**
 * Class User
 * @package CMS
 * @Framework
 * @persist onStoreComplete=\CMS\User::persistence_storeComplete
 */
class User extends TimeStampedItem {
    private static $FORBIDDEN_PASSWORDS = array(
        'password', '12345678', '23456789', '34567890',
    );

    /**
     * @var string
     * @persist length=100, unique
     * @role name, moduleUrl
     */
    public $username;

    /**
     * @var string
     * @persist length=200
     */
    public $email;

    /**
     * @var string
     * @persistSensitive length=255, binary
     */
    private $passwordHash;

    /**
     * @var int
     * @content date
     * @persistSensitive onStore=\CMS\User::persistence_password
     */
    private $stamp_passwordChanged;

    private $flag_passwordChanged = false;

    function __wakeup() {
        // persistable classes must initialize all private fields in __wakeup
        $this->flag_passwordChanged = false;
    }


    public static function checkPassword($username, $passwordAttempt) {
        $user = PersistenceDB::findItem('CMS\User', Query::compareField('username','==',$username));

        if ($user instanceof User) {
            $hasher = new \PasswordHash(8, FALSE);

            $check = $hasher->CheckPassword($passwordAttempt, $user->passwordHash);
        } else {
            $check = false;
        }

        Log::recordEvent(
            $check ? 'User Login' : 'Failed Login',
            $username
        );

        return $check;
    }

    public static function validatePassword($newPassword) {
        return is_string($newPassword)
            && strlen($newPassword) >= 8
            && in_array(strtolower(substr($newPassword,0,8)),self::$FORBIDDEN_PASSWORDS,true)
            && in_array(strtolower(substr($newPassword,-8)),self::$FORBIDDEN_PASSWORDS,true);
    }

    public function setPassword($newPassword, $storeNow = true) {
        // TODO check that the signed-in user has the right permissions

        if (!self::validatePassword($newPassword)) {
            throw new \InvalidArgumentException("That password is not allowed.");
        }

        $hasher = new \PasswordHash(8, FALSE);

        $newHash = $hasher->HashPassword($newPassword);

        if ($newHash === '*') {
            throw new \RuntimeException("Cannot create new password - hashing algorithm failed.");
        }

        $this->passwordHash = $newHash;
        $this->flag_passwordChanged = false;

        if ($storeNow) {
            PersistenceDB::storeItem($this);
        }
    }

    /** @param User $user */
    public static function persistence_password($user) {
        if ($user->flag_passwordChanged) {
            $user->stamp_passwordChanged = time();
        }
    }

    /** @param User $user */
    public static function persistence_storeComplete($user) {
        if ($user->flag_passwordChanged) {
            Log::recordEvent(
                'User Password Change',
                $user->username
            );
            $user->flag_passwordChanged = false;
        }
    }
}