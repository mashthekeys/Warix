if (!PHP) throw "PHP.functions.js must be loaded AFTER PHP.js";
(function() {

/*===========================================================================
 Class/Object Information
 http://php.net/manual/en/book.classobj.php
 ============================================================================*/
//PHP.__autoload - Not supported
PHP.call_user_method_array = function(ZZZZZZZZZ) {};
PHP.call_user_method = function(ZZZZZZZZZ) {};
PHP.class_alias = function($original, $alias, $autoload) {
    var origClass = PHP[$original];
    if (!PHP.hasOwnProperty($alias) && PHP.isPHPClass(origClass)) {
        PHP[$alias] = origClass;
        return true;
    } else {
        return false;
    }
};
PHP.class_exists = function($class_name, $autoload) {
    return PHP.isPHPClass(PHP[$class_name]);
};
//PHP.get_called_class - Not supported
PHP.get_class_methods = function($class_name) {
    if (PHP.isPHPObject($class_name) || PHP.isPHPClass($class_name)) {
        $class_name = $class_name.__CLASS__;
    }

    var c = PHP[$class_name];

    if (PHP.isPHPClass(c)) {
        var methods = PHP.array();
        for (var key in c) {//noinspection JSUnfilteredForInLoop
            if (key.charAt(0) !== '$' && typeof c[key] === 'function') {//noinspection JSUnfilteredForInLoop
                methods.push(key);
            }
        }
        return methods;
    } else {
        return null;
    }
};

PHP.get_class_vars = function($class_name) {
    if (PHP.isPHPObject($class_name) || PHP.isPHPClass($class_name)) {
        $class_name = $class_name.__CLASS__;
    }

    var c = PHP[$class_name];

    if (PHP.isPHPClass(c)) {
        var defaultVars = PHP.array();
        for (var key in c) {//noinspection JSUnfilteredForInLoop
            if (key.charAt(0) === '$') {//noinspection JSUnfilteredForInLoop
                defaultVars.push([key,c[key]]);
            }
        }
        return defaultVars;
    } else {
        return null;
    }
};

PHP.get_class = function($object) {
    if (PHP.isPHPObject($object)) {
        return $class_name.__CLASS__;
    }
    return false;
};

PHP.get_declared_classes = function() {
    return PHP.__classes__.__clone();
};

PHP.get_declared_interfaces = function() { return [] };

PHP.get_declared_traits = function() { return [] };

PHP.get_parent_class = function($class_name) {
    if (PHP.isPHPObject($class_name) || PHP.isPHPClass($class_name)) {
        $class_name = $class_name.__CLASS__;
    }
    var c = PHP[$class_name];

    if (PHP.isPHPClass(c)) {
        return c.__SUPERCLASS__;
    }
};

PHP.get_object_vars = function($object) {
    if (!PHP.isPHPObject($object)) {
        return null;
    }
    var vars = PHP.array();
    for (var key in $object) {
        if (key.charAt(0) === '$' && $object.hasOwnProperty(key)) {
            vars.push([key,$object[key]]);
        }
    }
    return vars;
};

PHP.interface_exists = function($class_name) { return false; };

PHP.is_a = function($object, $class_name, $allow_string) {
    var $superclass = PHP[$class_name];
    if ($superclass == null) {
        return false;
    }
    if (typeof $object === 'string') {
        if (!$allow_string) {
            return false;
        } else {
            var $class = PHP[$object];
            // I *think* this will work, given the double-layer effect of adding Classes to a prototype-based inheritance
            return $class === $superclass || $class instanceof $superclass;
        }
    }
    return $object instanceof $superclass;
};

PHP.is_subclass_of = function($object, $class_name, $allow_string) {
    if (arguments.length < 3) {
        $allow_string = true;
    }

    var $superclass = PHP[$class_name];
    if ($superclass == null) {
        return false;
    }
    if (typeof $object === 'string') {
        if (!$allow_string) {
            return false;
        } else {
            var $class = PHP[$object];
            // I *think* this will work, given the double-layer effect of adding Classes to a prototype-based inheritance
            return $class instanceof $superclass;
        }
    }
    return $object.prototype instanceof $superclass;
};

PHP.method_exists = function($object, $method_name) {
    var $class = (typeof $object === 'string') ? PHP[$object]
                    : PHP[$object.__CLASS__];

    return typeof $class[$method_name] === 'function';
};

PHP.property_exists = function($object, $property) {
    var $class = (typeof $object === 'string') ? PHP[$object]
        : PHP[$object.__CLASS__];

    return typeof $class["$"+$property] !== 'undefined';
};

PHP.trait_exists = function($trait_name, $autoload) { return false; };

/*===========================================================================
 Casts - implemented as to<Type> functions

 and

 Variable handling
 http://php.net/manual/en/book.var.php
 ============================================================================*/

/*
 Array and Object casts
 */

PHP.toArray = function(castVar) {
    if (PHP.is_array(castVar)) {
        return castVar;
    } else if (typeof castVar !== 'object') {
        return castVar == null ? PHP.array() : PHP.array(castVar);
    } else if (Array.isArray(castVar)) {
        return PHP.array.apply(PHP, castVar);
    } else {
        var args = [];

        if (PHP.isPHPObject(castVar)) {
            // iterate PHP object: only keys beginning $ are traversed
            for (key in castVar) if (castVar.hasOwnProperty(key)) {
                if (typeof key === 'string' && key.charAt(0) === '$') {
                    args.push([key.substr(1), castVar[key]]);
                }
            }
            return PHP.array.apply(PHP, args);
        } else {
            for (var key in castVar) if (castVar.hasOwnProperty(key)) {
                args.push([key, castVar[key]]);
            }
        }
        return PHP.array.apply(PHP, args);
    }
};
PHP.toObject = function(castVar) {
    if (PHP.isPHPObject(castVar)) {
        return castVar;
    }

    var obj = new PHP.stdClass();

    if (PHP.is_array(castVar)) {
        for (var n = 0, N = castVar.length, k = castVar.__keys, v = castVar.__values; n < N; ++n) {
            obj[k[n]] = v[n];
        }
    } else if (typeof castVar === 'object') {
        for (var key in castVar) if (castVar.hasOwnProperty(key)) {
            obj[key] = castVar[key];
        }
    } else if (castVar != null) {
        obj.scalar = castVar;
    }

    return obj;
};

/*
 Variable functions and scalar casts
 */

PHP.boolval = PHP.toBool = function(castVar) {
    if (typeof castVar === 'boolean') {
        return castVar;
    } else if (typeof castVar === 'object') {
        if (PHP.is_array(castVar) || Array.isArray(castVar)) {
            // Empty arrays are true in JS but false in PHP
            return castVar.length ? true : false;
        } else {
            // SHOULD DO: empty SimpleXML are false in PHP
            return true;
        }
    } else if (typeof castVar === 'number' && isNaN(castVar)) {
        return true; // NaN is false in JS but true in PHP
    } else if (castVar === '0') {
        return false; // '0' is true in JS but false in PHP
    } else {
        return castVar ? true : false;
    }
};


//PHP.debug_zval_dump - not supported

PHP.doubleval = PHP.floatval = PHP.toFloat = function(castVar) {
    if (typeof castVar === 'undefined') {
        return 0;
    } else if (typeof castVar === 'string') {
        var cc = castVar.charCodeAt(0);
        // If string starts - or 0 to 9:
        if (0x2d == cc || (0x30 <= cc && cc <= 0x39)) {
            // Parse numeric strings
            return parseFloat(castVar);
        } else {
            // Return 0 for non-numeric strings
            return 0;
        }
    }
    return Number(castVar);
};

PHP.empty = (function(toBool){return function($var) {
    return !toBool($var);
}})(PHP.toBool);

//PHP.get_defined_vars - not supported

//PHP.get_resource_type - not supported

PHP.gettype = function($var) {
    if ($var == null) {
        return 'NULL';

    } else if (typeof $var === 'boolean') {
        return 'boolean';

    } else if (typeof $var === 'number') {
        return $var==($var|0) ? 'integer' : 'double';

    } else if (typeof $var === 'string') {
        return 'string';

    } else if (typeof $var === 'object') {
        return PHP.is_array($var)
            ? 'array'
            : 'object';

    } else {
        return 'unknown type';
    }
};

PHP.intval = function($var, $base) {
    if (arguments.length < 2) {
        $base = 10;
    }

    if (typeof $var === 'string' && $var.length > 1) {
        var int = parseInt($var, $base);
        return isNaN(int) ? 0 : int;
    }
    return $var | 0;
};
PHP.toInt = function(castVar) {
    // This function only parses base-10 integers, PHP (int) style
    if (typeof castVar === 'string' && castVar.length > 1) {
        var int = parseInt(castVar, 10);
        return isNaN(int) ? 0 : int;
    }
    return castVar | 0;
};

/*
//This function parses all valid PHP integers:
PHP.parseValidInt = function(castVar) {
     if (typeof castVar === 'string' && castVar.length > 1) {
     var int;
     if (castVar.charAt(0) === '0') {
         var c = castVar.charAt(1);
         int = c === 'x' || c === 'X'
             ? parseInt(castVar.substr(2), 16)
             : c === 'b'
             ? parseInt(castVar.substr(2), 2)
             : //octal
             parseInt(castVar, 8);
     } else {
         int = parseInt(castVar,10);
     }
         return isNaN(int) ? 0 : int;
     }
    return castVar | 0;
};
*/

//PHP.is_array - core function, defined in PHP.js

PHP.is_bool = function($var) { return typeof $var === 'boolean'; };

PHP.is_callable = function($name, $syntax_only, CALLBACK_$callable_name) {
    var obj = null,class_,method,valid,callable;

    if (PHP.is_array($name)) {
        valid = ($name.length == 2
            && $name.__keys[0] == 0
            && $name.__keys[1] == 1
            && typeof (method = $name.__values[1]) === 'string'
            && (PHP.is_object(obj = $name.__values[0]) || typeof obj === 'string')
        );
    } else {
        valid = typeof $name === 'string';
    }

    if (typeof CALLBACK_$callable_name === 'function') {
        CALLBACK_$callable_name(obj === null
                ? PHP.toString($name)
                : (class_ || obj.__CLASS__ || 'stdClass') + '::' + method
        );
    }
    if (!valid) {
        return false;
    }
    if ($syntax_only) {
        return true;
    }

    if (obj === null) {
        // Check for Class::method string
        var split = $name.indexOf('::');
        if (split != -1) {
            obj = $name.substr(0, split);
            method = $name.substr(split + 2);
        }
    }

    if (obj === null) {
        // Check for existence of function
        var fn = PHP[$name];
        callable = (typeof fn === 'function') && !PHP.isPHPClass(fn);
    } else if (typeof obj === 'string') {
        class_ = PHP[obj];
        callable = class_.hasOwnProperty(method);
    } else if (!PHP.isPHPObject(obj)) {
        // All non-PHP objects are considered to be stdClass with no methods
        callable = false;
    } else {
        callable = typeof (obj[method]) === 'function';
    }

    return callable;
};

PHP.is_double = PHP.is_float = PHP.is_real
    = function($var) { return typeof $var === 'number'; };

PHP.is_int = PHP.is_integer = PHP.is_long
    = function($var) { return typeof $var === 'number' && $var == ($var|0); };

PHP.is_null = function($var) { return $var == null; };

// Construct regexp once rather than re-creating it every call
// Matches a plain hex integer (0x0), decimal (+0.0),
// or exponential (+0.0e0) format number.
var NUMERIC = /^(?:0[Xx][0-9A-Fa-f]+)|(?:[+-]?[0-9]+(?:\.[0-9]*)(?:[Ee][+-]?[0-9]+))$/;

PHP.is_numeric = function ($var) {
    return typeof $var === 'number'
        || (typeof $var === 'string' && NUMERIC.test($var));
};

PHP.is_object = function($var) { return typeof $var === 'object' && !PHP.is_array($var); };

PHP.is_resource = function($var) { return false; };

PHP.is_scalar = function($var) {
    return typeof $var === 'string'
        || typeof $var === 'number'
        || typeof $var === 'boolean';
};

PHP.is_string = function($var) { return typeof $var === 'string'; };

PHP.isset = function($var) { return $var != null; };

//PHP.print_r - see var_export

PHP.serialize = function serialize($value) {
    var $s;

    if ($value == null) {
        $s = 'N;';

    } else if (typeof $value === 'string') {
        $s = 's:' + $value.length + ':' + $value + ';';

    } else if (typeof $value === 'boolean') {
        $s = 'b:' + Number($value) + ';';

    } else if (typeof $value === 'number') {
        if (PHP.is_int($value)) {
            $s = 'i:' + $value.toFixed(0) + ';';
        } else {
            $s = 'd:' + $value.toPrecision(17).toUpperCase() + ';';
        }

    } else if (typeof $value === 'object') {
        var $export;

        if ($value instanceof PHP["\\Serializable"]) {
            $export = $value.serialize();

            $s = 'C:'
                + $value.__CLASS__.length + ':' + $value.__CLASS__ + ':'
                + $export.length + ':{' + $value + '}';

        } else {
            if (PHP.is_array($value)) {
                $export = $value;
                $s = 'a:' + $value.length + ':{';

            } else {
                var class_ = PHP.isPHPObject($value)
                    ? $value.__CLASS__
                    : 'stdClass';

                $export = PHP.toArray($value);
                $s = 'O:'
                    + class_.length + ':' + class_ + ':'
                    + $export.length + ':{';
            }

            PHP.foreach($export, function ($key, $value) {
                $s += serialize($key) + serialize($value);
            });

            $s += '}';
        }

    } else {
        throw 'PHP.serialize: found unknown javascript type '+typeof $value;
    }

    return $s;
};

//PHP.settype - cannot be supported in JS environment

PHP.strval = PHP.toString = function(){};
PHP.strval = PHP.toString = PHPString;

var unserialize_ANY = /^(?:(N);|(b):([01]);|(i):[0-9]+;|(d):([+-]?[0-9]+(?:\.[0-9]*)(?:[Ee][+-]?[0-9]+))|(a):|([sOC]):([0-9]+):)/;
var unserialize_ARRAY = /^([0-9]+):\{/;

PHP.unserialize = function($str) {
    var $unparsed = $str;
    var $error = false;
    var $value = unserialize_worker();

    return $error ? false : $value;

    function unserialize_worker() {
        var $match = $unparsed.match(unserialize_ANY);
        var $value;

        if (!$match) {
            $error = true;
            return false;
        } else {
            $unparsed = $unparsed.substr($match[0].length);

            if ($match[1] === 'N') {
                return null;

            } else if ($match[2] === 'b') {
                return !!($match[3]);

            } else if ($match[4] === 'i') {
                return Number($match[5]);

            } else if ($match[6] === 'd') {
                return Number($match[7]);

            } else if ($match[8] === 'a') {
                return unserialize_array_worker();

            } else {
                var readLength = Number($match[10]);
                var readStr = $unparsed.substr(0, readLength);
                var delimiter = $unparsed.substr(readLength, 1);

                $unparsed = $unparsed.substr(readLength + 1);

                if ($match[9] === 's') {
                    if (delimiter === ';') {
                        return readStr;
                    } else {
                        $error = true;
                        return false;
                    }
                }

                // $match[9] is 'O' or 'C'
                if (delimiter !== ':') {
                    $error = true;
                    return false;
                }

                var CLASS = PHP[readStr];

                if (!PHP.isPHPClass(class_)) {
                    CLASS = PHP.stdClass;
                }

                var $properties = unserialize_array_worker();

                if ($error) {
                    return false;
                }

                $value = new CLASS();

                PHP.foreach($properties, function($key,$value) {
                    $value["$"+$key] = $value;
                });

                return $value;
            }
        }
    }

    function unserialize_array_worker() {
        var $match = $unparsed.match(unserialize_ARRAY);
        var $value;

        if (!$match) {
            $error = true;
            return false;
        } else {
            $unparsed = $unparsed.substr($match[0].length);

            var readItems = 2 * $match[1];

            $value = PHP.array();

            for (var n = 0; n < readItems; ++n) {
                $value.__set(unserialize_worker(), unserialize_worker());
                if ($error) {
                    return false;
                }
            }

            var delimiter = $unparsed.charAt(0);
            $unparsed = $unparsed.substr(1);

            if (delimiter !== '}') {
                $error = true;
                return false;
            }

            return $value;
        }
    }
};


//PHP.unset - use delete instead

PHP.print_r = PHP.var_dump = PHP.var_export
    = function var_export($var, $return)
{
    var $php;

    if ($var == null) {
        $php = 'NULL';

    } else if (typeof $var === 'string') {
        $php = "'" + $var.replace(/\\/g,"\\\\").replace(/'/g,"\\'").replace(/\x00/g,"' . \"\\0\" . '") + "'";

    } else if (typeof $var === 'number' || typeof $var === 'boolean') {
        $php = $var.toString();

    } else {
        if (PHP.is_array($var)) {
            $php = "array (\n";
        } else if (PHP.isPHPObject($var)) {
            $php = $var.__CLASS__ + "::__set_state(array(\n";
        } else {
            $php = "stdClass::__set_state(array(\n";
        }

        PHP.foreach($var, function($key, $value) {
            $php += "  " + var_export($key) + " => " + var_export($value) + ",\n";
        });

        $php += ")";
    }

    if ($return) {
        return $php;
    } else {
        alert($php);
    }
};


/*===========================================================================
 ============================================================================*/
})();

