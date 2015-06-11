jQuery(function($){


    PHP.namespace("TestNS", function(TestNS) {

        var MyClass = TestNS.class("MyClass", null);

        MyClass.static_method = function(message) {
            return("MyClass static_method called."+(message?message:""));
        };

        MyClass.prototype.instance_method = function(message) {
            return("MyClass instance_method called."+(message?message:""));
        };

        //*********************************************************

        var PlainSubClass = TestNS.class("PlainSubClass", MyClass);

        // No new methods or overrides

        //*********************************************************

        var SubClass = TestNS.class("SubClass", MyClass);

        SubClass.static_method = function(message) {
            return("SubClass static_method called."+(message?message:""));
        };

        SubClass.prototype.instance_method = function(message) {
            return("SubClass instance_method called."+(message?message:""));
        };

        //*********************************************************

        var OtherClass = TestNS.class("OtherClass", null);

        OtherClass.static_method = function(message) {
            return("OtherClass static_method called."+(message?message:""));
        };

        OtherClass.prototype.instance_method = function(message) {
            return("OtherClass instance_method called."+(message?message:""));
        };

        //*********************************************************
    });

    // begin use
    var MyClass = PHP.classDefinition("TestNS\\MyClass");
    var SubClass = PHP.classDefinition("TestNS\\SubClass");
    var OtherClass = PHP.classDefinition("TestNS\\OtherClass");
    // end use

    var myInstance = new PHP["TestNS\\MyClass"]();
    var subclassInstance = new PHP["TestNS\\SubClass"]();
    var plainInstance = new PHP["TestNS\\PlainSubClass"]();
    var otherInstance = new PHP["TestNS\\OtherClass"]();

    var msg = (""
        +"typeof [] = "+(typeof [])
        +"\n"
        +"[] instanceof Array = "+([] instanceof Array)
        +"\n"
        +"PHP.array() instanceof Array = "+(PHP.array() instanceof Array)
//        +"\n"
//        +"PHP.array().join('...') = "+(PHP.array().join('...'))
//        +"\n"
//        +"myInstance instanceof window.PHP_CLASS = "+(myInstance instanceof window.PHP_CLASS)
//        +"\n"
//        +"MyClass instanceof window.PHP_CLASS = "+(MyClass instanceof window.PHP_CLASS)
        +"\n"
        +"OtherClass instanceof MyClass = "+(OtherClass instanceof MyClass)
        +"\n"
        +"SubClass instanceof MyClass = "+(SubClass instanceof MyClass)
        +"\n"
        +"SubClass.PHP_CLASS = "+(SubClass.PHP_CLASS)
        +"\n"
        +"SubClass === PHP[SubClass.PHP_CLASS] = "+(SubClass === PHP[SubClass.PHP_CLASS])
        +"\n"
        +"new SubClass() instanceof MyClass = "+(new SubClass() instanceof MyClass)
        +"\n"
        +"myInstance instanceof PHP['TestNS\\MyClass'] = "+(myInstance instanceof PHP["TestNS\\MyClass"])
        +"\n"
        +"subclassInstance instanceof PHP['TestNS\\MyClass'] = "+(subclassInstance instanceof PHP["TestNS\\MyClass"])
        +"\n"
        +"plainInstance instanceof PHP['TestNS\\MyClass'] = "+(plainInstance instanceof PHP["TestNS\\MyClass"])
        +"\n"
        +"otherInstance instanceof PHP['TestNS\\MyClass'] = "+(otherInstance instanceof PHP["TestNS\\MyClass"])
        +"\n"
        +"otherInstance instanceof PHP['TestNS\\OtherClass'] = "+(otherInstance instanceof PHP["TestNS\\OtherClass"])
        +"\n"
        +"myInstance.instance_method() = "+(myInstance.instance_method())
        +"\n"
        +"plainInstance.instance_method() = "+(plainInstance.instance_method())
        +"\n"
        +"subclassInstance.instance_method() = "+(subclassInstance.instance_method())
        +"\n"
        +"otherInstance.instance_method() = "+(otherInstance.instance_method())
        +"\n"
        +"PHP['TestNS\\MyClass'].static_method() = "+(typeof PHP["TestNS\\MyClass"].static_method === 'function' && PHP["TestNS\\MyClass"].static_method())
        +"\n"
        +"PHP['TestNS\\SubClass'].static_method() = "+(typeof PHP["TestNS\\SubClass"].static_method === 'function' && PHP["TestNS\\SubClass"].static_method())
        +"\n"
        +"PHP['TestNS\\PlainSubClass'].static_method() = "+(typeof PHP["TestNS\\PlainSubClass"].static_method === 'function' && PHP["TestNS\\PlainSubClass"].static_method())
        +"\n"
        +"PHP['TestNS\\OtherClass'].static_method() = "+(typeof PHP["TestNS\\OtherClass"].static_method === 'function' && PHP["TestNS\\OtherClass"].static_method())
        +"\n"
    );

    $("body").append($("<pre>").text(msg));
});