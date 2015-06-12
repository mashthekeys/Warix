PHP = (function() {
    if (window.PHP) return PHP;

    // unique internal markers to signal class definition
    var __DECLARE_CLASS__ = new (function(){});
    var __DECLARE_SUBCLASS__ = new (function(){});

    var PHP_OBJECT = function(){};

    var PHP_CLASS = function (nsObject, _class, superclassObj, _implements) {
        if (this === window) {
            // To ensure this function does not execute in the global scope
            return this;
        }

        // The function below is run to construct instance objects and to define subclass prototypes
        var CONSTRUCTOR = function() {
            if (arguments.length && arguments[0] === __DECLARE_SUBCLASS__) {
                // No PHP code should be run; this call is to declare
                // JS inheritance.
            } else {
                if (typeof this.__construct === 'function') {
                    this.__construct.apply(this, arguments)
                } else {
                    // No PHP constructor code to run
                }
            }
        };

        if (superclassObj == null) {
            CONSTRUCTOR.prototype = new PHP_OBJECT();
        } else {
            CONSTRUCTOR.prototype = new superclassObj(__DECLARE_SUBCLASS__);
        }
        CONSTRUCTOR.prototype.constructor = CONSTRUCTOR;

        return CONSTRUCTOR;
    };

    var PHP_NAMESPACE = function(fqName) {
        if (typeof fqName !== 'string') {
            throw 'PHP_NAMESPACE: fqName must be string';
//        } else if (fqName.indexOf('\\') == -1) {
//            throw 'PHP_NAMESPACE: fqName must contain \\';
        }

        this.name = fqName;

        // Override prototype implementation of class() with this bound closure.
        // This allows the class() function to be aliased (e.g. var _class = LOCAL_NS.class;)
        this.class = (function(nsObject) {
            PHP_NAMESPACE.prototype.class.apply(nsObject,arguments);
        })(this);
    };
    //noinspection ReservedWordAsName
    PHP_NAMESPACE.prototype.class = function (nsObject) {
        // The function below is run to declare a new class
        return function(_class, _extends, _implements) {
            var superclassObj = _extends == null ? null : PHP.classDefinition(_extends);

            var fqName = nsObject.name + '\\' + _class;

            var classObj = function() {
                if (arguments.length && arguments[0] === __DECLARE_SUBCLASS__) {
                    // No PHP code should be run; this call is to declare
                    // JS inheritance.
                } else if (typeof this.__construct === 'function') {
                    //superclassObj.call(this);
                    this.__construct.apply(this, arguments)
                } else {
                    // No PHP constructor code to run
                }
            };

            // Setting classObj.constructor should ensure that (classObj instanceof PHP_CLASS) === true
            classObj.constructor = PHP_CLASS;
            classObj.PHP_CLASS = fqName;
            classObj.PHP_SUPERCLASS = _extends == null ? null : _extends;
            classObj.PHP_IMPLEMENTS = _implements == null || !_implements.length ? null : [].concat(_implements);

            if (superclassObj != null) {
                classObj.prototype = new superclassObj(__DECLARE_SUBCLASS__);
                classObj.prototype.constructor = classObj;
            }

            classObj.prototype.__parent = superclassObj;
            classObj.prototype.__self = classObj;

            nsObject[_class] = classObj;
            PHP[fqName] = classObj;

            return classObj;
        };
    };

    function PHP_ARRAY() {
        this.__keys = [];
        this.__values = [];
        
        this.__lookup = {};
        this.__index = {};
        this.__lastIndex = -1;

        if (arguments.length) this.__push(arguments);
    }
    
    /** Used to implement PHP's reset(), next(), each(), etc. */
    PHP_ARRAY.prototype.__position = -1;
    
    PHP_ARRAY.prototype.__get = function($name) {
        return this.__lookup[$name];
    };
    PHP_ARRAY.prototype.__set = function($name, $value) {
        var index_;

        if (this.__index.hasOwnProperty($name)) {
            index_ = this.__index[$name];

        } else {
            this.__index[$name] = index_ = this.__keys.length;
            this.__keys[index_] = $name;
        }
        this.__values[index_] = $value;
        this.__lookup[$name] = $value;
        this.length = this.__keys.length;
    };
    PHP_ARRAY.prototype.__unset = function($name) {
        if (this.__index.hasOwnProperty($name)) {
            var index = this.__index[$name];
            this.splice(index,1);

            delete this.__keys[index];
            delete this.__values[index];
            delete this.__index[$name];
            delete this.__lookup[$name];

            this.length = this.__keys.length;
        }
    };
    PHP_ARRAY.prototype.__isset = function($name) {
        return this.index.hasOwnProperty($name);
    };
    PHP_ARRAY.prototype.__push = function() {
        var N = arguments.length,
            lastIndex = this.__lastIndex;

        for (var n = 0; n < N; ++n) {
            var arg = arguments[n],
                key,
                keyAsInt,
                value;

            if (Array.isArray(arg)) {
                // Import [ key, value ]
                key = ''+arg[0];

                if ((''+(keyAsInt = parseInt(key))) === key) {
                    if (keyAsInt > lastIndex) {
                        lastIndex = keyAsInt;
                    }
                }

                value = arg[1];
            } else {
                // Import value (key is implicit)
                key = ++lastIndex;
                value = arg;
            }

            this.__set(key, value);
        }

        this.__lastIndex = lastIndex;
    };
    // end PHP_ARRAY methods

    return {
        NS: {},
        CONSTANTS: {},
        TYPES: {
            PHP_ARRAY: PHP_ARRAY, // A javascript object operating as PHP array
            PHP_OBJECT: PHP_OBJECT, // A javascript object operating as  PHP object instance
            PHP_NAMESPACE: PHP_NAMESPACE // A javascript object used to define classes
        },

        isPHPArray: function(a) {
            return a instanceof PHP_ARRAY;
        },
        isPHPObject: function(a) {
            return a instanceof PHP_OBJECT;
        },
        array: function() {
            var phpArray = new PHP_ARRAY();
            if (arguments.length) phpArray.__push.apply(phpArray, arguments);
            return phpArray;
        },
        foreach: function(traversable, callback) {
            // PHP.foreach($array) :- Returns an iterator supporting current, key, next and hasNext
            // PHP.foreach($array, $callback) :- Iterates through callback
            var useIterator = arguments.length < 2;

            var n, N, keys, values, key, setFn, retVal;

            if (traversable == null || typeof traversable !== 'object') {
                // Ignore non-array values, as PHP would.
                keys = values = [];

            } else if (traversable instanceof PHP_ARRAY) {
                // iterate PHP array
                keys = traversable.__keys.concat();
                values = traversable.__values.concat();

            } else if (traversable instanceof PHP_OBJECT) {
                // iterate PHP object: only keys beginning $ are traversed
                keys = [];
                values = [];
                for (key in traversable) if (traversable.hasOwnProperty(key)) {
                    if (typeof key === 'string' && key.charAt(0) === '$') {
                        keys.push(key.substr(1));
                        values.push(traversable[key]);
                    }
                }

            } else if (Array.isArray(traversable)) {
                // iterate plain JS array
                keys = [];
                values = [];
                N = traversable.length;
                for (n = 0; n < N; ++n) {
                    keys.push(n);
                    values.push(traversable[n]);
                }

            } else {
                // iterate plain JS object
                keys = [];
                values = [];
                for (key in traversable) if (traversable.hasOwnProperty(key)) {
                    keys.push(key);
                    values.push(traversable[key]);
                }
            }

            if (useIterator) {
                return (function (keys, values, length) {
                    var pos = 0;

                    this.current = function () {
                        return values[pos];
                    };
                    this.next = function () {
                        return values[++pos];
                    };
                    this.key = function () {
                        return keys[pos];
                    };
                    this.hasNext = function () {
                        return pos < length;
                    };
                    return this;
                })(keys, values, N);
            } else {
                N = values.length;
                
                if (typeof callback !== 'function') {
                    callback = PHP.__lookup_user_func(callback);
                }

                for (n = 0; n < N; ++n) {
                    retVal = callback(keys[n], values[n]);
                }
            }
        },

        /**
         * @phpKeyword
         */
        namespace: function (fqNamespace, declaration) {
            var nsObject;

            if (PHP.NS.hasOwnProperty(fqNamespace)) {
                nsObject = PHP.NS[fqNamespace];
            } else {
                nsObject = new PHP_NAMESPACE(fqNamespace);
                PHP.NS[fqNamespace] = nsObject;
            }

            if (arguments.length > 1) {
                declaration.call(nsObject, nsObject);
            }

            return nsObject;
        },
        /**
         * Calling PHP.class declares a global (un-namespaced) class.
         *
         * To define a namespaced class, use nsObject.class(...);
         *
         * @phpKeyword
         */
        'class': function (_class, _extends, _implements) {
            return PHP.namespace('\\').class(_class, _extends, _implements);

//            PHP.namespace('\\',function(GLOBAL_NS){
//                return GLOBAL_NS.class(_class, _extends, _implements);
//            });
        },
        
        /**
         * @jsOnly
         */
        classDefinition: function(fqClass) {
            if (typeof fqClass === 'function') {
                if (fqClass.hasOwnProperty('PHP_CLASS')) {
                    if (fqClass !== PHP[fqClass.PHP_CLASS]) throw 'classDefinition: fqClass must be registered in the PHP library.';

                    return fqClass;
                } else {
                    throw 'classDefinition: fqClass must be PHP_CLASS object';
                }
            }

            if (typeof fqClass !== 'string') {
                throw 'classDefinition: fqClass must be PHP_CLASS function or string '+typeof  fqClass;
            } else if (fqClass.indexOf('\\') == -1) {
                throw 'classDefinition: fqClass must contain \\';
            }

            if (!PHP.hasOwnProperty(fqClass)) {
                throw 'classDefinition: NO DEFINITION FOR '+fqClass;
            }

            return PHP[fqClass];
        }
    };
})();
