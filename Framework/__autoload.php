<?php
namespace Framework;

class BootstrapAutoloader {
    /**
     * Registers Framework\BootstrapAutoloader as an SPL autoloader.
     *
     * @param bool $prepend Whether to prepend the autoloader instead of appending
     */
    static public function register($prepend = false) {
        ini_set('unserialize_callback_func', 'spl_autoload_call');
        spl_autoload_register(array(__CLASS__, 'autoload'), true, $prepend);
    }

    /**
     * Registers Framework\ClassRegistry as an SPL autoloader, then
     * removes Framework\BootstrapAutoloader from the SPL autoloader list.
     *
     * @param bool $prepend Whether to prepend the autoloader instead of appending
     */
    static public function handover($prepend = false) {
        // Add the ClassRegistry-based autoloader...
        ini_set('unserialize_callback_func', 'spl_autoload_call');
        spl_autoload_register(array('Framework\ClassRegistry', 'autoload'), true, $prepend);

        //...which removes the need for the Framework bootstrap loader:
        spl_autoload_unregister(array(__CLASS__, 'autoload'));
    }

    /**
     * Handles autoloading of classes.
     *
     * @param string $class A class name.
     */
    static public function autoload($class) {
//        echo "~~~ ",__CLASS__,'::',__FUNCTION__," ($class) ~~~ \n";

        if (0 === strpos($class, 'Framework\\') && $class !== 'Framework\__autoload') {
            $class = substr($class,10);

            $fileName = __DIR__ . '/' . strtr($class, '\\', '/') . '.php';

            if (file_exists($fileName)) {
//                echo "~~~ ",__CLASS__,'::',__FUNCTION__," FOUND $fileName \n";
                require $fileName;
            }
        }
    }
}

// Framework bootstrap
// ===================
// First the basic autoloader is registered.
// It loads ClassRegistry and any other required classes
// as ClassRegistry scans the framework.
// ClassRegistry then takes over class loading.
BootstrapAutoloader::register();

ClassRegistry::registerFolder(__DIR__, __NAMESPACE__,true);

BootstrapAutoloader::handover();

