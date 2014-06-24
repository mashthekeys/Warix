<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 17/06/2014
 * Time: 19:50
 */

namespace Framework\mysqli;

/**
 * SCRAPPED. Comparison to SecureField2_0 shows that very similar effects can be achieved simply using
 * a private static array field on a class, providing the class's own code does not expose the
 * sensitive values.
 * ----------------------------------------------------------------------------------------------------
 * EXPERIMENTAL
 *
 * Encapsulates a single item of data, such that it cannot be accidentally be dumped to screen,
 * even within a development environment.
 *
 * It should be noted that the value can still be accessed by other PHP code, even without
 * direct access to SecureField.  This is intended to prevent accidentally leaking critical data
 * such as passwords, even in the even of fatal error being fully logged to disk.
 *
 * It is also not recommended to store large amounts of data (kilobytes at a time) using this class.
 *
 * @package Framework\mysqli
 */
class SecureField {
    /**
     * Because the data is encapsulated within an anonymous function, there is no way to access it
     * except by calling the function.
     *
     * @var callable
     */
    private $value;

    function __construct($value) {
        if (!is_scalar($value)) {
            throw new \InvalidArgumentException("Cannot secure a value of type ".gettype($value));
        }

        // Dev note:
        // Closure functions ( function()use(){} ) are unsuitable for this purpose.
        // Their implementation as objects is transparent to print_r and var_dump.
        //
        // Only create_function provides sufficient obscurity.
        //
        // It does so at the cost of exposing sensitive items at fairly standardised
        // addresses, making it easy for other PHP code to get access to the data.
        // However, it is no longer accessible to any form of debug output.

        $this->value = create_function('','return '.var_export($value,true).';');
    }

    function value() {
        $v = $this->value;
        return $v();
    }

    function __toString() {
        $v = $this->value;
        return (string)( $v() );
    }
}