<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 31/05/2014
 * Time: 20:02
 */

namespace CMS;

use Framework\PersistenceDB;

class ConfigEditor {
    public static function editConfigFile($file, $newValues) {
        $php = '?php';

        if (file_exists($file)) {
            if (!is_file($file)) {
                throw new \ErrorException("Config file $file exists but is not a file.");
            }
            if (!is_readable($file)) {
                throw new \ErrorException("Config file $file is not readable.");
            }
            if (!is_writable($file)) {
                throw new \ErrorException("Config file $file is not writable.");
            }

            $code = file_get_contents($file);

            if (strlen($code) && substr($code,0,5) !== "<$php") {
                throw new \ErrorException("Config file $file is not a PHP file.");
            }

        } else {
            if (!is_writable(dirname($file))) {
                throw new \ErrorException("Config file $file cannot be created.");
            }
            $code = null;
        }

        if (!strlen($code)) {
            $code = "<$php\n
namespace CMS;

ini_set('display_errors','1');
error_reporting(E_ALL & ~(E_NOTICE | E_STRICT | E_DEPRECATED));

Config::define('site.lang','en-GB');

";
        }

        $defined = array();

        $codeWas = $code;

        $code = preg_replace_callback(
            '/(?<=<\?php|\}|;)(\s*Config::define\s*\(\s*)(\'[^\']+\'|"[^"]")\s*,\s*(\'[^\']+\'|"[^"]",\S[^)]*)\s*\)\s*;/mu',
            function($match) use($newValues, $defined) {
                $name = substr($match[2],1,-1);

                $defined[$name] = true;

                if (array_key_exists($name, $newValues)) {
                    $value = var_export($newValues[$name], true);
                } else {
                    $value = $match[3];
                }

                return "$match[1]$match[2], $value);";
            },
            $code
        );

        if (!strlen($code)) {
            throw new \ErrorException("Was: $codeWas");
        }

        $newDefinitions = '';
        foreach (array_keys($newValues) as $name) {
            if (!$defined[$name]) {
                $nameStr = var_export($name, true);
                $value = var_export($newValues[$name], true);
                $newDefinitions .= "Config::define($nameStr,$value);\n";
            }
        }

        if (strlen($newDefinitions)) {
            if (strpos($code, 'namespace CMS;') === false) {
                $code = "<$php\nnamespace CMS;\n".substr($code,5);
            }
            $code = str_replace('namespace CMS;',"namespace CMS;\n$newDefinitions\n",$code);
        }

        if (empty($code)) {
            throw new \ErrorException(var_export($code,true));
        }

        return !!file_put_contents($file, $code);
//        try {
//            $parser = new \PhpParser\Parser(new \PhpParser\Lexer\Emulative);
//
//            $stmts = $parser->parse($code);
//
//            self::analyseCode($stmts, null);
//
//
//        } catch (\PhpParser\Error $e) {
//            //$e->getMessage();
//        }
    }

    private static function analyseCode($stmts, $namespace) {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof \PhpParser\Node\Stmt\Namespace_) {

                if ($namespace !== null) {
                    throw new \PhpParser\Error('Namespace declared inside namespace');
                }

                echo "namespace \\$stmt->name\n";

                self::analyseCode($stmt->stmts, $stmt->name);

            } else if ($stmt instanceof \PhpParser\Node\Stmt\ClassMethod) {
                echo "\\$namespace ClassMethod - $stmt->name\n";
            } else if ($stmt instanceof \PhpParser\Node\Stmt\Use_) {
                echo "\\$namespace Use - $stmt->name\n";
            } else if ($stmt instanceof \PhpParser\Node\Stmt\Class_) {
            } else if ($stmt instanceof \PhpParser\Node\Stmt\Class_) {
            }

            if ($found) break;
        }
    }
} 