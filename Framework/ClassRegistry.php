<?php

namespace Framework;

use PhpParser\Node\Name;

require_once __DIR__.'/lib/PHP-Parser/lib/bootstrap.php';

/**
 * Analyses PHP class files for annotations.
 *
 * PHP classes must begin with a capital letter and must be defined within files of the same name.
 */
class ClassRegistry {
    private static $unresolved = false;
    private static $resolved = array();
    private static $namespace = array();
    private static $classFile = array();
    private static $super = array();
    private static $classDoc = array();
    private static $memberDoc = array();
    private static $classAnnotations = array();
    private static $memberAnnotations = array();

    private static $devWarnings = false;

    /**                 
     * Registers the autoloader for all classes in the given namespace and folder.
     * 
     * If $recurse is true, any sub-folders will also be registered for autoload.
     * 
     * @param $folder
     * @param $namespace
     * @param $recursive
     */
    public static function registerFolder($folder, $namespace, $recursive) {
        self::$namespace[$namespace] = $folder;
        
        self::_registerFolder($folder, $namespace, $recursive);
    }
    private static function _registerFolder($folder, $namespace, $recursive) {
        $it = new \DirectoryIterator($folder);

        $subFolders = array();
        $found = false;
        
        while ($it->valid()) {
            $filename = $it->getFilename();
            $filePath = $folder . DIRECTORY_SEPARATOR . $filename;

            if ($filename === '.' || $filename === '..' || $filename === 'lib' || $filename === 'log' || $filename === 'res') {
                // ignore
                
            } else if (is_dir($filePath)) {
                $subFolders[$filename] = $filePath;

            } else if (preg_match('/^([A-Z][_0-9A-Za-z]*)\.php$/', $filename, $match)) {
                $className = $match[1];

                $found = self::registerClass($namespace, $className, $filePath) || $found;
            }

            $it->next();
        }
        
        if ($recursive) foreach ($subFolders as $subFolder => $subFolderPath) {
            $subNS = "$namespace\\$subFolder";

            if (self::_registerFolder($subFolderPath, $subNS, true)) {
                $found = true;
                self::$namespace[$subNS] = $subFolderPath;
//                echo "$subNS = $subFolderPath<br>";
            }
        }
        
        return $found;
    }
    
    private static function _pre_export($var,$return = null) {
        $var = htmlspecialchars(var_export($var, true));
        $var = "\n<pre class='php_export'>$var</pre>\n";

        if ($return) return $var; else echo $var;
    }


    public static function registerClass($namespace, $className, $file) {
        $code = file_get_contents($file);

        $found = false;
        try {
            $parser = new \PhpParser\Parser(new \PhpParser\Lexer\Emulative);

            $stmts = $parser->parse($code);

            foreach ($stmts as $stmt) {

                if ($stmt instanceof \PhpParser\Node\Stmt\Namespace_) {
                    if ("$stmt->name" !== $namespace) {
//                        throw new \RuntimeException("TEMP DEBUG: Expecting namespace $namespace, found $stmt->name");

                        if (self::$devWarnings) trigger_error("Expecting namespace $namespace, found $stmt->name", E_USER_WARNING);
                        continue;
                    }

                    $useAlias = array();

                    foreach ($stmt->stmts as $sub_stmt) {
                        if ($sub_stmt instanceof \PhpParser\Node\Stmt\Use_) {
//                            self::_pre_export(count($sub_stmt->uses));
//                            self::_pre_export($sub_stmt->uses);


                            foreach ($sub_stmt->uses as $useName) {
                                $useAlias["$useName->alias"] = "$useName->name";
//                                self::_pre_export("use alias $useName->name $useName->alias");
                            }

                        } else if (($sub_stmt instanceof \PhpParser\Node\Stmt\Class_
                                || $sub_stmt instanceof \PhpParser\Node\Stmt\Interface_)
                            && $sub_stmt->name === $className) {

                            $found = self::registerParsedClass($namespace, $className, $sub_stmt, $useAlias);

                            ClassRegistry::$classFile["$namespace\\$className"] = $file;

                            if ($found) break;
                        }
                        // should probably register all classes in instructed file
                    }
                } else if ($stmt instanceof \PhpParser\Node\Stmt\Class_) {
                    //if (self::$devWarnings) trigger_error("Expecting namespace $namespace, found $stmt->name", E_USER_WARNING);
                }

                if ($found) break;
            }

        } catch (\PhpParser\Error $e) {
            //$e->getMessage();
        }

        return $found;
    }

    private static function registerParsedClass($namespace, $className, $stmt, $useAlias) {
        $classOrInterface = $stmt instanceof \PhpParser\Node\Stmt\Class_
            || $stmt instanceof \PhpParser\Node\Stmt\Interface_;

        if (!$classOrInterface) return false;

        $extends_ = $stmt->extends;

        if ($extends_) {
            $superclass = self::determineFQClass($namespace, $extends_, $useAlias);

//            if (strpos($superclass,'\\') === false) {
//                echo("<h3>Cannot resolve $namespace\\$className superclass $superclass</h3><pre>");
////                echo htmlspecialchars(var_export($extends_,1)),"\n";
//                echo 'namespace = ',$namespace,"\n";
//                echo 'superclass = ',$superclass,"\n";
//                echo 'isFullyQualified = ',$extends_->isFullyQualified(),"\n";
//                echo 'isQualified = ',$extends_->isQualified(),"\n";
//                echo 'isRelative = ',$extends_->isRelative(),"\n";
//                echo 'isUnqualified = ',$extends_->isUnqualified(),"\n";
//                echo '</pre>';
//            } else {
//                echo("<h3>OK - $namespace\\$className superclass $superclass</h3><pre>");
////                echo htmlspecialchars(var_export($extends_,1)),"\n";
//                echo 'namespace = ',$namespace,"\n";
//                echo 'superclass = ',$superclass,"\n";
//                echo 'isFullyQualified = ',$extends_->isFullyQualified(),"\n";
//                echo 'isQualified = ',$extends_->isQualified(),"\n";
//                echo 'isRelative = ',$extends_->isRelative(),"\n";
//                echo 'isUnqualified = ',$extends_->isUnqualified(),"\n";
//                echo '</pre>';
//            }
        } else {
            $superclass = null;
        }

//        if ($className === 'Page') {
//
//
//            var_dump($stmt->extends->toString());
////            var_dump($stmt->extends);
////            die('var_dump');
//        }


        /** @var \PhpParser\Node\Stmt\Class_ $stmt */

        $classDoc = Annotation::parsePHPDoc($stmt->getDocComment());
        $memberDoc = array();



        if ($classDoc['Framework']) {
            foreach ($stmt->stmts as $member) {
                $name = null;

                if ($member instanceof \PhpParser\Node\Stmt\Property) {
                    $name = '$'.$member->props[0]->name;
                } else if ($member instanceof \PhpParser\Node\Stmt\ClassMethod) {
                    $name = $member->name.'()';
//            } else if ($member instanceof \PhpParser\Node\Stmt\ClassConst) {
//                $name = $member->name;
                }

                $doc = $member->getDocComment();

                if ($name) {
                    if ($doc) {
                        $memberDoc[$name] = Annotation::parsePHPDoc($doc);
                    } else {
                        $memberDoc[$name] = array();
                    }
                }
            }
        }

        self::registerAnnotations($namespace, $className, $superclass, $classDoc, $memberDoc);

        return true;
    }

    private static function registerAnnotations($namespace, $className, $superclass, $classDoc, $memberDoc) {
        $info = array(
            "class" => $classDoc,
            "members" => $memberDoc,
        );

        $class = "$namespace\\$className";

        self::$super[$class] = $superclass;

        if ($superclass === null) {
            self::$resolved[$class] = true;

        } else if (self::$resolved[$superclass]) {
            // inherit members from superclass
            foreach (self::$memberDoc[$superclass] as $member => $doc) {
                if (!isset($memberDoc[$member])) {
                    $memberDoc[$member] = $doc;
                }
            }

            self::$resolved[$class] = true;

        } else {
            // superclass and class will be resolved later.
            self::$resolved[$class] = false;
            self::$unresolved = true;
        }

        self::$classDoc[$class] = $classDoc;
        self::$memberDoc[$class] = $memberDoc;

        foreach ($classDoc as $annotation => $value) {
            self::$classAnnotations[$annotation][] = $class;
        }
        foreach ($memberDoc as $member => $doc) {
            foreach ($doc as $annotation => $value) {
                self::$memberAnnotations[$annotation][] = "$namespace\\$className::$member";
            }
        }

//        $framework = $classDoc['Framework'] ? 'Annotated class' : 'Non-Annotated class';

//        if ($classDoc['Framework']) {
//            echo "--- CLASS REGISTRATION ($framework) ---\n";
//            echo "$className = ";
//            var_export($info);
//            echo "\n";
//        }
    }

    public static function getClassAnnotations($class) {
        $doc = self::$classDoc["$class"];
        return $doc === false ? null : $doc;
    }

    public static function getMembers($class) {
        self::resolveAll();

        return array_keys(self::getMemberAnnotations($class));
    }

    public static function getMemberAnnotations($class, $member = null) {
        self::resolveAll();

        if ($member === null) {
            // List all annotations from class and superclasses
            return self::$memberDoc[$class];
        } else {
            // List all annotations for specific member
            return self::$memberDoc[$class][$member];
        }
/*        if ($member === null) {
            // List all annotations from class and superclasses
            $parentDocs = array();
            $alreadySeen = array($class=>true);

            $fqSuperclass = self::$super[$class];

            if ($fqSuperclass === null) {
                return self::$memberDoc[$class];
            }

            $superclasses = array();

            do {
                if ($alreadySeen[$fqSuperclass]) break; // infinite loop prevention
                $alreadySeen[$fqSuperclass] = true;

                $superclasses[$fqSuperclass] = true;

                $fqSuperclass = self::$super[$fqSuperclass];
            } while ($fqSuperclass !== null);


            $memberDoc = array();

            end($superclasses);

            do {
                $fqSuperclass = key($superclasses);
                $parentDoc = self::$memberDoc[$fqSuperclass];
                if (is_array($parentDoc)) {
                    $memberDoc += $parentDoc; // should do a deep merge here
                } else {
                    // TODO warning
                    echo "\nNO MATCH $fqSuperclass ".gettype($parentDoc)."\n";
                }
            } while (prev($superclasses));

            $memberDoc += self::$memberDoc[$class];
        } else {
            // List all annotations for specific member
            $fqSuperclass = self::$super[$class];
            if ($fqSuperclass !== null) {
                $parentMemberA = self::getMemberAnnotations($fqSuperclass, $member);

                $classMemberA = self::$memberDoc[$class][$member];

                $memberDoc = array();
                if (is_array($parentMemberA)) $memberDoc += $parentMemberA;
                if (is_array($classMemberA)) $memberDoc += $classMemberA;
                // TODO crawl superclasses
            } else {
                $memberDoc = self::$memberDoc[$class][$member];
            }
        }

//        echo "--- getMemberAnnotations($namespace, $class, $member) ---\n";
//        var_export($memberDoc);
//        echo "\n\n";

        return $memberDoc;
*/
    }

    public static function listAnnotatedClasses($annotation, $namespace = null) {
        $list = self::$classAnnotations[$annotation];

        if (is_array($list)) {
            if ($namespace !== null) {
                $search = "$namespace\\";
                $L = strlen($search);

                $list = array_filter($namespace, function($value) use($search,$L) {
                    return substr($value,0,$L) === $search
                        && strcspn($value, '\\', $L) == (strlen($value) - $L);
                });
            }
        } else {
            $list = array();
        }

//        echo "--- listAnnotatedClasses ($annotation, $namespace) ---\n";
//        var_export($list);
//        echo "\n\n";

        return $list;
    }

    /**
     * @param $annotation
     * @param string|null $search Namespace or class to search within.
     * @return array
     */
    public static function listAnnotatedMembers($annotation, $search = null) {
        $list = self::$memberAnnotations[$annotation];

        if (is_array($list)) {
            if ($search !== null) {
                if (isset(self::$namespace[$search])) {
                    $search = "$search\\";
                } else {
                    $search = "$search::";
                }
                $L = strlen($search);

                $list = array_filter($list, function($value) use($search,$L) {
                    return substr($value,0,$L) === $search
                    && strcspn($value, '\\', $L) == (strlen($value) - $L);
                });
            }
        } else {
            $list = array();
        }

//        echo "--- listAnnotatedMembers ($annotation, $namespace, $class) ---\n";
//        var_export($list);
//        echo "\n\n";

        return $list;
    }

    /**
     * Handles autoloading of classes.
     *
     * @see __autoload.php
     * @param string $class A class name.
     */
    static public function autoload($class) {
//        echo "~~~ ",__CLASS__,'::',__FUNCTION__," ($class) ~~~ \n";
        if (isset(self::$classFile[$class])) {
            $fileName = self::$classFile[$class];

//            echo __CLASS__," AUTOLOADING $fileName \n";

            require self::$classFile[$class];
        }
    }

    private static function resolveAll() {
        if (self::$unresolved) {
            foreach (array_keys(self::$resolved, false, true) as $unresolvedClass) {
                self::resolveFrameworkClass($unresolvedClass);
            }

            self::$unresolved = in_array(false, self::$resolved, true);

            if (self::$unresolved) {
                echo "<pre>",var_export(self::$unresolved,true);

                throw new \RuntimeException("Cannot resolve all classes in class registry.");
            }
        }
    }

    private static function resolveFrameworkClass($class) {
        if (self::$resolved[$class]) return;

        self::resolveSuperclass($class);

        $superclass = self::$super[$class];
        $memberDoc = self::$memberDoc[$class];
        $newMemberDoc = array();

        // BEGIN inherit members from superclass
        foreach (self::$memberDoc[$superclass] as $member => $doc) {
            if (!isset($memberDoc[$member])) {
                $memberDoc[$member] = $doc;
                $newMemberDoc[$member] = $doc;
            }
        }

        self::$memberDoc[$class] = $memberDoc;

        foreach ($newMemberDoc as $member => $doc) {
            foreach ($doc as $annotation => $value) {
                self::$memberAnnotations[$annotation][] = "$class::$member";
            }
        }
        // END inherit members from superclass

        self::$resolved[$class] = true;
    }

    private static function resolveSuperclass($class) {
        $superclass = self::$super[$class];

        if (!self::$resolved[$superclass]) {
            list($superNamespace) = NamespaceUtil::splitClass($superclass);

            if (!isset(self::$namespace[$superNamespace])) {
                // If the namespace is not registered with ClassRegistry,
                // the superclass is not checked for annotations.
                self::resolveNonFrameworkClass($superclass);

            } else if (isset(self::$classFile[$superclass])) {
                // recursively resolve superclass
                self::resolveFrameworkClass($superclass);

            } else {
                // The superclass should be registered with ClassRegistry, but cannot be found.
                throw new \RuntimeException("Cannot find required superclass $superclass for class $class.");
            }
        }
    }


    private static function resolveNonFrameworkClass($class) {
        if (self::$resolved[$class]) return;

        $superclass = get_parent_class($class);
        if ($superclass === false) $superclass = null;

        self::$super[$class] = $superclass;

        $memberDoc = array();

        $class_vars = get_class_vars($class);
        $class_methods = get_class_methods($class);

        if ($class_vars === false || $class_methods === false) {
            throw new \RuntimeException("Cannot resolve non-Framework class $class");
        }

        foreach ($class_vars as $var) {
            $memberDoc["\$$var"] = array();
        }

        foreach ($class_methods as $method) {
            $memberDoc["$method()"] = array();
        }

        if ($superclass !== null) {
            self::resolveSuperclass($class);
//            $newMemberDoc = array();

            // inherit members from superclass
            foreach (self::$memberDoc[$superclass] as $member => $doc) {
                if (!isset($memberDoc[$member])) {
                    $memberDoc[$member] = $doc;
//                    $newMemberDoc[$member] = $doc;
                }
            }
            // No entries for $memberAnnotations, even inherited ones
        }

        self::$classDoc[$class] = false;
        self::$memberDoc[$class] = $memberDoc;

        self::$resolved[$class] = true;

    }

    public static function determineFQClass($namespace, Name $name, $useAlias) {
        $className = $name->toString();

        if ($name->isFullyQualified()) {
            $class = strpos($className, '\\') === false
                ? "\\$className"
                : $className;

        } else if ($name->isQualified()) {
            $localName = NamespaceUtil::firstName($className);
            $nsName = $useAlias[$localName];

            if (isset($nsName)) {
                $class = $nsName . substr($className, strlen($localName));
            } else {
                // Implicitly relative to the current namespace
                $class = "$namespace\\$className";
            }

        } else if ($name->isRelative()) {
            // Explicitly relative to the current namespace
            $class = "$namespace\\$className";

        } else { // $extends->isUnqualified();
            $class = $useAlias[$className];

            if (!isset($class)) $class = "$namespace\\$className";
        }
        return $class;
    }
}

