PHP = (function() {
    if (window.PHP) return PHP;

    // unique internal marker to signal class definition
    var __DECLARE_SUBCLASS__ = new (function(){});

    var PHP_OBJECT = function(){};

    var PHP_CLASS = function (nsObject, __CLASS__1, superclassObject, __IMPLEMENTS__1) {
        // To ensure this function does not execute in the global scope
        if (this === window) return;

        var __CLASS__, __SUPERCLASS__, __IMPLEMENTS__;

        __CLASS__ = "" + __CLASS__1;

        __SUPERCLASS__ = (superclassObject == null) ? null
                            : superclassObject.__CLASS__;

        __IMPLEMENTS__ = [].concat(__IMPLEMENTS__1);

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

        if (superclassObject == null) {
            CONSTRUCTOR.prototype = new PHP_OBJECT();
        } else {
            CONSTRUCTOR.prototype = new superclassObject(__DECLARE_SUBCLASS__);
        }
        CONSTRUCTOR.prototype.constructor = CONSTRUCTOR;

        CONSTRUCTOR.__CLASS__ = __CLASS__;
        CONSTRUCTOR.__SUPERCLASS__ = __SUPERCLASS__;
        CONSTRUCTOR.__IMPLEMENTS__ = __IMPLEMENTS__;

        this.__CLASS__ = __CLASS__;
        this.__SUPERCLASS__ = __SUPERCLASS__;
        this.__IMPLEMENTS__ = __IMPLEMENTS__;

        return CONSTRUCTOR;
    };

    var PHP_NAMESPACE = function(fqName) {
        if (typeof fqName !== 'string') {
            throw 'PHP_NAMESPACE: fqName must be string';
//        } else if (fqName.indexOf('\\') == -1) {
//            throw 'PHP_NAMESPACE: fqName must contain \\';
        }

        var lastBackslash = fqName.lastIndexOf('\\');
        if (lastBackslash > -1) {
            var parentName = fqName.substr(0, lastBackslash);

        }

        this.name = fqName;

        // Override prototype implementation of class() with this bound closure.
        // This allows the class() function to be aliased (e.g. var _class = LOCAL_NS.class;)
        this.class = (function(nsObject) {
            PHP_NAMESPACE.prototype.class.apply(nsObject,arguments);
        })(this);
    };

    /**
     * The function below is run to declare a new class
     *
     * @param _class        Class name
     * @param _extends      Parent class extended (optional)
     * @param _implements   Array of interfaces implemented (optional)
     * @param _definition   Static constructor for the class.  It should have the form:
     *                      function(self,parent,__CLASS__)
     *
     * @returns PHP_CLASS
     */
    //noinspection ReservedWordAsName
    PHP_NAMESPACE.prototype.class = function(_class, _extends, _implements, _definition) {
        var superclassObj = _extends instanceof PHP_CLASS ? _extends : null;

        var fqName = this.name + '\\' + _class;

        var classObj = new PHP_CLASS(this, fqName, superclassObj, _implements);
        //var classObj = function() {
        //    if (arguments.length && arguments[0] === __DECLARE_SUBCLASS__) {
        //        // No PHP code should be run; this call is to declare
        //        // JS inheritance.
        //    } else if (typeof this.__construct === 'function') {
        //        //superclassObj.call(this);
        //        this.__construct.apply(this, arguments);
        //        // TODO hunt for parent constructors
        //    } else {
        //        // No PHP constructor code to run
        //    }
        //};
        //
        //// Setting classObj.constructor should ensure that (classObj instanceof PHP_CLASS) === true
        //classObj.constructor = PHP_CLASS;

        this[_class] = classObj;
        PHP[fqName] = classObj;
        PHP.__classes__[fqName] = classObj;

        if (typeof _definition === 'function') {
            //_definition(self,parent,__CLASS__,__NAMESPACE__);
            _definition(classObj, superclassObj, fqName, this.name);
        }

        return classObj;
    };

    PHP_NAMESPACE.prototype.resolve = function(name) {
        if (name == null) {
            throw 'resolve: Null or undefined name.';
        }
        if (typeof name !== 'string') {
            throw 'resolve: Invalid name type.';
        }
        if (name.charAt(0) === '\\') {
            return PHP[name];
        }
        if (name.lastIndexOf('\\') == -1) {
            return this[name];
        }
        return PHP['\\'+this.name+'\\'+name];
    };

    PHP_NAMESPACE.prototype.resolveClass = function(name) {
        var c = this.resolve(name);
        if (!(c instanceof PHP_CLASS)) throw 'resolveClass: Class definition not found.';
        return c;
    };

    PHP_NAMESPACE.prototype.resolveFunction = function(name) {
        var fn = this.resolve(name);
        if ((typeof fn !== 'function') || !(fn instanceof PHP_CLASS)) throw 'resolveFunction: Function definition not found.';
        return fn;
    };

    function PHP_ARRAY() {
        this.__keys = [];
        this.__values = [];
        
        this.__lookup = {};
        this.__index = {};
        this.__lastIndex = -1;
        this.length = 0;

        if (arguments.length) this.push(arguments);

        return this;
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
    PHP_ARRAY.prototype.push = function() {
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

                if ((''+(keyAsInt = parseInt(key,10))) === key) {
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

    PHP_ARRAY.prototype.__clone = function() {
        var array = new PHP_ARRAY();
        array.__keys = this.__keys.concat();
        array.__values = this.__values.concat();
        array.__lastIndex = this.__lastIndex;

        var index = this.__index;
        var lookup = this.__lookup;
        for (var key in index) if (index.hasOwnProperty(key)) {
            array.__index[key] = index[key];
            array.__lookup[key] = lookup[key];
        }

        return array;
    };

    PHP_ARRAY.prototype.toString = function () { return 'Array'; };
    // end PHP_ARRAY methods

    var NULL_ITERATOR = {
        current: function(){},
        hasNext: function(){ return false },
        key: function(){},
        next: function(){}
    };

    // Define basic declarative and language constructs in this file.
    return {
        NS: {},
        CONSTANTS: {},
        TYPES: {
            PHP_ARRAY: PHP_ARRAY, // A javascript object operating as PHP array
            PHP_OBJECT: PHP_OBJECT, // A javascript object operating as  PHP object instance
            PHP_NAMESPACE: PHP_NAMESPACE // A javascript object used to define classes
        },

        is_array: function(a) {
            return a instanceof PHP_ARRAY;
        },
        isPHPClass: function(c) {
            return c instanceof PHP_CLASS;
        },
        /**
         * Return true if this object was created in the PHP.js environment.
         *
         * Note that PHP.is_object treats plain javascript objects as objects,
         * but this function does not.
         * @param o
         * @returns {boolean}
         */
        isPHPObject: function(o) {
            return o instanceof PHP_OBJECT;
        },
        array: PHP_ARRAY,
        foreach: function(traversable, callback) {
            // PHP.foreach($array) :- Returns an iterator supporting current, key, next and hasNext
            // PHP.foreach($array, $callback) :- Iterates through callback
            var useIterator = arguments.length < 2;

            var n, N, keys, values, key, retVal;

            if (traversable == null || typeof traversable !== 'object') {
                // Ignore non-array values, as PHP would.
                if (useIterator) {
                    return NULL_ITERATOR;
                } else {
                    return;
                }
            }

            traversable = PHP.toArray(traversable);
            keys = traversable.__keys.concat();
            values = traversable.__values.concat();
            N = values.length;

            if (useIterator) {
                return (function (keys, values, length) {
                    var pos = 0;

                    this.current = function () {
                        return values[pos];
                    };
                    this.hasNext = function () {
                        return pos < length;
                    };
                    this.key = function () {
                        return keys[pos];
                    };
                    this.next = function () {
                        return values[++pos];
                    };
                    return this;
                })(keys, values, N);
            } else {
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
                declaration.call(nsObject, nsObject, fqNamespace);
            }

            return nsObject;
        },
        /**
         * Calling PHP.class declares a global (un-namespaced) class.
         *
         * To define a namespaced class, use PHP.namespace("Some\\Namespace").class(...)
         * or nsObject.class(...);
         *
         * @param _class        Class name
         * @param _extends      Parent class extended (optional)
         * @param _implements   Array of interfaces implemented (optional)
         * @param _definition   Static constructor for the class.  It should have the form:
         *                      function(self,parent,__CLASS__)
         *
         * @phpKeyword
         */
        'class': function (_class, _extends, _implements, _definition) {
            return PHP.namespace('\\').class(_class, _extends, _implements, _definition);
        }
    };
})();

