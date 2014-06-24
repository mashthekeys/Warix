<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 21/05/2014
 * Time: 01:05
 */

namespace Framework;


use Framework\TypeUtil;

class PersistenceDB {
    // Maximum recursion limit when loading objects from the database.
    const STACK_LIMIT = 5;

    /** Do not make queries on this field.  Use PersistenceDB::query() instead.
     * @var \mysqli
     */
    private static $db;

    private static $namespaceShorten = array();
    private static $namespaceShortenLookup = array();
    private static $dbFilters = array();
    private static $dbLoggers = array();
    private static $cache = array();


    public static function setDB(\mysqli $db) {
        if (!self::$db) {
            if ($db->connect_errno) {
                throw new \LogicException("Cannot set PersistenceDB to a failed connection.");
            } else {
                self::$db = $db;
            }
        } else {
            throw new \LogicException("Cannot set PersistenceDB twice.");
        }
    }

    /**
     * Calling connect() guarantees that either a successful connection to the DB server is made, or an exception is thrown.
     *
     * If possible, it will automatically connect to the server, which is not possible.  Under these circumstances, an
     * exception will be thrown.
     */
    public static function connect() {
        if (!self::$db) {
            throw new \LogicException("PersistenceDB cannot auto-connect.");
//            self::$db = new \mysqli($host, $username, $password, $db_name);
        }
    }

    public static function connected() {
        return self::$db && !self::$db->connect_errno;
    }


    /**
     * @param callable $callback
     * @param string|null $uniqueKey
     */
    public static function addDBFilter($callback, $uniqueKey = null) {
        CallbackUtil::addCallback(self::$dbFilters, $callback, $uniqueKey);
    }

    /**
     * @param string|callable $listener Either the callback or the unique key used when originally registered.
     * @return bool TRUE if the requested listener was removed from the list. FALSE if listener could not be found.
     */
    public static function removeDBFilter($listener) {
        return CallbackUtil::removeCallback(self::$dbFilters, $listener);
    }

    /**
     * @param callable $callback
     * @param string|null $uniqueKey
     */
    public static function addDBLogger($callback, $uniqueKey = null) {
        CallbackUtil::addCallback(self::$dbLoggers, $callback, $uniqueKey);
    }

    /**
     * @param string|callable $listener Either the callback or the unique key used when originally registered.
     * @return bool TRUE if the requested listener was removed from the list. FALSE if listener could not be found.
     */
    public static function removeDBLogger($listener) {
        return CallbackUtil::removeCallback(self::$dbLoggers, $listener);
    }

    /**
     * @param $query
     * @return bool|\mysqli_result
     * @throws QueryPreventedException
     */
    public static function query($query) {
        $params = compact('query');

        $prevented = false;

        foreach (self::$dbFilters as $callback) {
            if (false === call_user_func($callback, $params)) {
                $prevented = true;
            }
        }

        if ($prevented) {
            throw new QueryPreventedException();
        } else {
            $result = self::$db->query($query);

            $params['result'] = $result;

            if ($result === false) {
                $params['error'] = self::$db->error;
            }

            foreach (self::$dbLoggers as $listener) {
                call_user_func($listener, $params);
            }
        }

        return $result;
    }

    public static function createTables() {
//        echo "\nCREATE TABLES\n";
        self::connect();

        $persistClasses = ClassRegistry::listAnnotatedClasses('persist');
//        echo "\$persistClasses = (",var_dump($persistClasses),")\n";

        foreach ($persistClasses as $class) {
            $classInfo = self::getMemberPersistenceInfo($class);
//            echo "\$classInfo = (",var_dump($classInfo),")\n";

            $table = $classInfo['table'];

            $fields = array();
            $keys = array();

            $matchTable = strtr($table, array('_'=>'\\\\_','%'=>'\\\\%'));

            $exists = self::query("SHOW TABLES LIKE '$matchTable'")->num_rows;

            if (!$exists) {
                $primary = null;

                foreach ($classInfo['persistent_fields'] as $field => $doc) {
                    $idProxyDoc = $classInfo['foreign_fields'][$field];

                    if ($idProxyDoc) {
                        // should probably utilise doc of foreign field as well
                        $sqlType = self::getSQLDeclaration($idProxyDoc, 'persist', $table, $field, $keys);

                    } else {
                        $sqlType = self::getSQLDeclaration($doc, 'persist', $table, $field, $keys);

                        if ($doc['role']['id']) {
                            $primary = $field;
                            if (TypeUtil::getSimpleType($doc['var']) === 'int') {
                                $sqlType .= ' AUTO_INCREMENT';
                            }
                        }
                    }

                    $fields[$field] = "$field $sqlType";
                }

                foreach ($classInfo['sensitive_fields'] as $field => $doc) {
                    $sqlType = self::getSQLDeclaration($doc, 'persistSensitive', $table, $field, $keys);

                    $fields[$field] = "$field $sqlType";
                }

                $keys[] = "PRIMARY KEY (`$primary`)";

                if (count($fields) == 0) {
                    throw new InvalidAnnotationException("@persist - $table HAS NO FIELDS\n");
                } else if (!strlen($primary)) {
                    throw new InvalidAnnotationException("@persist - $table HAS NO PRIMARY KEY\n");
                } else {
                    $ok = self::query("CREATE TABLE IF NOT EXISTS `$table` (\n"
                        .implode(",\n", $fields).",\n"
                        .implode(",\n", $keys)
                        ."\n) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;"
                    );

                    if (!$ok) {
                        throw new \ErrorException("PersistenceDB error: ".self::$db->error);
                    }
                }
            }
        }
//        echo "\nEND CREATE TABLES\n";
        return true;
    }

    /**
     * @param string $type
     * @param string|null $field
     * @return array|bool
     */
    public static function getMemberPersistenceInfo($type, $field = null) {
//        list($namespace, $class) = NamespaceUtil::splitClass($type);


        $classInfo = self::getClassPersistenceInfo($type);

        if (!$classInfo) return false;

        if ($field === null) {
            $memberDoc = ClassRegistry::getMemberAnnotations($type);
            $persistent_fields = array();
            $sensitive_fields = array();
            $foreign_fields = array();

            foreach ($memberDoc as $member => $doc) if ($member{0}==='$') {
                if (substr($member,1,2) === '__' && substr($member,-3) === '_id') {
                    // persistent fields in the form $__..._id must implement the ID lookup of a persistent object field
                    // of the same name, less the __ prefix and __id suffix.
                    $foreign_fields[substr($member, 3, -3)] = $doc;

                } else if ($doc['persistSensitive']) {
                    $sensitive_fields[substr($member, 1)] = $doc;
                } else if ($doc['persist']) {
                    $persistent_fields[substr($member, 1)] = $doc;
                }
            }

            $classInfo['persistent_fields'] = $persistent_fields;
            $classInfo['sensitive_fields'] = $sensitive_fields;
            $classInfo['foreign_fields'] = $foreign_fields;
            return $classInfo;
        } else {
            $doc = ClassRegistry::getMemberAnnotations($type, $field);

            if (!($doc['persist'] || $doc['persistSensitive'])) {
                return false;
            }

//            if (strlen($doc['var'])) {
//                $class = preg_split('/\s*\|\s*/u', trim($doc['var']));
//            } else {
//                $class = array('mixed');
//            }

            return $doc;
        }
    }


    /**
     * @param $type
     * @return array|bool
     */
    public static function getClassPersistenceInfo($type) {
//        list($namespace, $class) = NamespaceUtil::splitClass($type);

        $doc = ClassRegistry::getClassAnnotations($type);

        if ($doc['Framework'] && $doc['persist']) {
            return array(
                'class' => $type,
                'table' => self::getTableName($type),
            );
        } else {
            return false;
        }
    }

    public static function findById($type, $id) {
        $stack = array(array(__FUNCTION__, $type, 'query'));

        return self::_findById($type, $id, $stack);
    }
    public static function _findById($type, $id, $stack) {
        $idField = ltrim(ClassRegistryUtils::findMemberWithRole('id', $type), '$');
        if (!strlen($idField)) {
            throw new \RuntimeException("Cannot find items of class $type by id.");
        }

        $classCache = self::$cache[$type];
        if (isset($classCache) && array_key_exists($id, $classCache)) {
            return $classCache[$id];
        } else {
            $items = self::_findItems($type, Query::compareField($idField, '=', $id), 1, $stack);

            $item = is_array($items) && count($items) ? reset($items) : null;

            self::$cache[$type][$id] = $item;

            return $item;
        }
    }
    public static function findItem($type, Query $expression) {
        $stack = array(array(__FUNCTION__, $type, 'query'));

        $items = self::_findItems($type, $expression, 1, $stack);
        return is_array($items) && count($items) ? reset($items) : null;
    }
    public static function findItems($type, Query $expression, $limit = 0) {
        $stack = array(array(__FUNCTION__, $type, 'query'));
        return self::_findItems($type, $expression, $limit, $stack);
    }
    private static function _findItems($type, Query $expression, $limit, $stack) {
        $info = self::getMemberPersistenceInfo($type);

        if (!$info) {
            trigger_error("PersistenceDB cannot handle '$type'", E_USER_WARNING);
            return null;
        }

        $class = $info['class'];

        // Check for fully-qualified class name
        if (strpos($class, '\\') === false) {
            trigger_error("PersistenceDB needs fully-qualified '$class'", E_USER_WARNING);
            return null;
        }

        $sql = "SELECT * FROM `$info[table]` WHERE " . $expression->makeSQL($class);

        if ($limit > 0) $sql .= " LIMIT " . (int)$limit;

        self::connect();

        /** @var \mysqli_result $res */
        $res = self::query($sql);
        if (!$res) {
            trigger_error("PersistenceDB query failed for items of '$type'", E_USER_WARNING);
            return null;
        }

        $foreign_fields = $info['foreign_fields'];
        $persistent_fields = $info['persistent_fields'];
        $sensitive_fields = $info['sensitive_fields'];
        $idField = ltrim(ClassRegistryUtils::findMemberWithRole('id', $type),'$');

        $items = array();
        $lookup = array();

        while ($row = $res->fetch_assoc()) {
            // Store vars in array. TODO Only public fields will be seen, therefore
            // persistable classes must initialize all private fields in __wakeup
            // (the class parser could theoretically provide this information)
            $itemFields = get_class_vars($class);
            $objectFields = array();
            $item = null;

            if (!strlen($idField)) {
                $id = count($items);
            } else {
                $id = $row[$idField];  // should be parsed before using in code below
                $cachedItem = self::$cache[$type][$id];
                if (isset($cachedItem)) {
                    // should allow item to recurse through any objects
                    $item = $cachedItem;
                }
            }

            if ($item === null) {
                foreach ($foreign_fields as $fieldName => $fieldDoc) {
                    $itemFields["__{$fieldName}_id"] = self::parseDBField($row[$fieldName], $fieldDoc);
                }
                foreach ($persistent_fields as $fieldName => $fieldDoc) {
                    $idProxyDoc = $foreign_fields[$fieldName];
                    if ($idProxyDoc) {
                        $objectFields[$fieldName] = self::parseDBField($row[$fieldName], $fieldDoc, $idProxyDoc);
                    } else {
                        $itemFields[$fieldName] = self::parseDBField($row[$fieldName], $fieldDoc);
                    }
                }
                foreach ($sensitive_fields as $fieldName => $fieldDoc) {
                    $itemFields[$fieldName] = self::parseDBField($row[$fieldName], $fieldDoc);
                }

                // Instead of calling e.g. $item = new $class()
                // we create a serialized object, allowing private
                // fields to be written at creation time.
                //
                // For example, s:7:"myClass";
                //         and              a:1:{s:3:"foo";s:3:"bar";}
                //      become  O:7:"myClass":1:{s:3:"foo";s:3:"bar";}

                $label = serialize($class);

                $array = serialize($itemFields);

                $object = 'O' . substr($label, 1, -1) . substr($array, 1);

                $item = unserialize($object);
            }

            $items[$id] = $item;
            if (count($objectFields)) $lookup[$id] = $objectFields;
        }

//        $num_rows = $res->num_rows;

        // Free resource before looking up other items
        $res->free();
        $res = null;

        if (count($lookup)) {
            if (count($stack) >= self::STACK_LIMIT) {
                $stackTrace = implode('',array_map(function($a){return implode(' ',$a).",<br>\n";},$stack));

                $lookupInfo = reset($lookup);
                trigger_error("PersistenceDB found a chain of objects that was too long:<br>\n$stackTrace- STOP - $lookupInfo[class] $lookupInfo[id]", E_USER_WARNING);

                foreach ($items as $index => $item) {
                    $item->__persistenceDB_incomplete = true;
                }

                // do not cache this item as it is incompletely reconstructed

            } else foreach ($lookup as $index => $objectFields) {
                $item  = $items[$index];
                $newStack = $stack;
                $newStack[] = array(__FUNCTION__, $type, $item->$idField);

                foreach ($objectFields as $fieldName => $lookupInfo) {
                    $object = PersistenceDB::_findById($lookupInfo['class'],$lookupInfo['id'], $newStack);

                    $item->$fieldName = $object;
                }

                if (strlen($idField)) {
                    $id = $index;
                    self::$cache[$type][$id] = $item;
                }
            }
        }
//        if (empty($items)) throw new \RuntimeException("No items of '$type' $num_rows :-[");
        return $items;
    }

    /**
     * Stores the given object in the Persistence Database.
     *
     * If the object has a property with <tt>@role id</tt>
     * and no ID has yet been assigned, the object will be
     * updated with the row ID.
     *
     * @param object $item
     * @throws PersistTypeException       If object is not persistent.
     * @throws \InvalidArgumentException  If object is null.
     * @throws \ErrorException            In weird and wonderful circumstances.
     * @return bool
     */
    public static function storeItem($item) {
        if (!is_object($item)) {
            throw new \InvalidArgumentException();
        }
        $class = get_class($item);
        $info = self::getMemberPersistenceInfo($class);

        if (!$info) {
            throw new PersistTypeException('Class is not persistent: '. $class);
        }

        $update = array();
        $idField = ltrim(ClassRegistryUtils::findMemberWithRole('id', $class),'$');

        foreach ($info['persistent_fields'] as $fieldName => $fieldDoc) {
            if (strlen($fieldDoc['persist']['onStore'])) {
                call_user_func($fieldDoc['persist']['onStore'], $item);
            }

            $idProxyDoc = $info['foreign_fields'][$fieldName];
            if ($idProxyDoc) {
//                if (is_object($item->$fieldName)) {
//                    $item->$fieldName
//                }

                //TODO object is ignored, the __..._id field determines the field by itself
                $idProxyField = "__{$fieldName}_id";
                $update[$fieldName] = self::exportFieldToDB($item->$idProxyField, $idProxyDoc);

//                if (!$item->$idProxyField) {
//                    echo \CMS\HTMLUtils::pre_export($item);
//                    throw new \ErrorException("$idProxyField empty: '{$item->$idProxyField}''");
//                }
            } else {
                $update[$fieldName] = self::exportFieldToDB($item->$fieldName, $fieldDoc);
            }
        }

        foreach ($info['sensitive_fields'] as $fieldName => $fieldDoc) {
            if (strlen($fieldDoc['persistSensitive']['onStore'])) {
                call_user_func($fieldDoc['persistSensitive']['onStore'], $item);
            }

            // TODO how do we read the private field ?!
            // AKA, hello Andy, I see you're dealing with the User class
            $update[$fieldName] = self::exportFieldToDB($item->$fieldName, $fieldDoc);
        }

        if (!strlen($idField)) {
            $command = 'INSERT';
            $updateId = false;

        } else if (!($update[$idField] > 0)) {
            unset($update[$idField]);
            $command = 'INSERT';
            $updateId = true;

        } else {
            $command = 'REPLACE';
            $updateId = false;
        }

        $fields = array();

        foreach ($update as $fieldName => $value) {
            $fields[] = "`$fieldName`=$value";
        }

        $query = "$command INTO `$info[table]` SET ".implode(',',$fields);

        $ok = !!self::query($query);

        if ($ok) {
            if ($updateId) {
                $item->$idField = self::$db->insert_id;
            }

            $classDoc = ClassRegistry::getClassAnnotations($class);
            $onStoreComplete = $classDoc['persist']['onStoreComplete'];
            if (isset($onStoreComplete)) {
                call_user_func($onStoreComplete, $item);
            }
        }

        return $ok;
    }

    /**
     * Deletes the given object from the Persistence Database.
     * Instances of this object in memory continue to exist
     * until the end of the script, so the deleted item can
     * still be retrieved by calling PersistenceDB::findById
     * for the remainder of this request.
     *
     * In order to be deleted, the object must have a field with
     * <tt>@role id</tt>.
     *
     * @param object $item
     * @throws PersistTypeException       If object is not persistent, or has no ID field.
     * @throws \InvalidArgumentException  If object is null.
     * @throws \ErrorException            In weird and wonderful circumstances.
     * @return bool
     */
    public static function deleteItem($item) {
        if (!is_object($item)) {
            throw new \InvalidArgumentException();
        }
        $class = get_class($item);
        $info = self::getMemberPersistenceInfo($class);

        if (!$info) {
            throw new PersistTypeException('Class is not persistent: '. $class);
        }

        $idField = ltrim(ClassRegistryUtils::findMemberWithRole('id', $class),'$');

        if (!strlen($idField)) {
            throw new PersistTypeException('Class has no ID field: '. $class);
        }

        $id = $item->$idField;

        if (empty($id)) {
            // should check if empty ID allowed
            throw new \ErrorException("Item ID is empty");
        }

        $query = "DELETE FROM `$info[table]` WHERE `$info[table]`.`$idField`=".self::$db->real_escape_string($id);

        $ok = !!self::query($query);

        if ($ok) {
            $classDoc = ClassRegistry::getClassAnnotations($class);
            $onDelete = $classDoc['persist']['onDelete'];
            if (isset($onDelete)) {
                call_user_func($onDelete, $item);
            }
        }

        return $ok;
    }

    public static function getTableName($type) {
        list($namespace, $class) = NamespaceUtil::splitClass($type);

        $short = self::$namespaceShorten[$namespace];
        if (strlen($short)) {
            $namespace = $short;
        }
//        return strtr("$namespace\\$class",array('¦'=>'¦¦','\\'=>'¦'));
        return strtr("$namespace\\$class",array('\\'=>'$'));
    }

    public static function getFQClassFromTableName($tableName) {
//        $tableName = strtr($tableName,array('¦'=>'\\'));
//        $tableName = strtr($tableName,array('\\\\'=>'¦'));
        $tableName = strtr($tableName,array('$'=>'\\'));

        $split = strrpos($tableName, '\\');

        if ($split === false) {
            $namespace = self::$namespaceShortenLookup[''];
            $class = $tableName;
        } else {
            $namespace = substr($tableName, 0, $split);
            $class = substr($tableName, $split+1);

            $short = self::$namespaceShortenLookup[$namespace];
            if (strlen($short)) {
                $namespace = $short;
            }
        }

        return "$namespace\\$class";
    }

    public static function getClassFromTableName($tableName) {
//        $tableName = strtr($tableName,array('¦'=>'\\'));
//        $tableName = strtr($tableName,array('\\\\'=>'¦'));
        $tableName = strtr($tableName,array('$'=>'\\'));

        $split = strrpos($tableName, '\\');

        if ($split === false) {
            $namespace = self::$namespaceShortenLookup[''];
            $class = $tableName;
        } else {
            $namespace = substr($tableName, 0, $split);
            $class = substr($tableName, $split+1);

            $short = self::$namespaceShortenLookup[$namespace];
            if (strlen($short)) {
                $namespace = $short;
            }
        }

        return compact('namespace','class');
    }

    private static function parseDBField($dbValue, $fieldDoc, $proxyFieldDoc = null) {
        switch (TypeUtil::getSimpleType($fieldDoc['var'])) {
            case 'object':
                if ($proxyFieldDoc) {
                    if ($fieldDoc['var']['null'] && !$dbValue) {
                        return null;
                    } else {
                        $classes = $fieldDoc['var']['object'];
                        $id = $dbValue;

                        if (empty($classes)) {
                            trigger_error("Cannot instantiate object without any class name.", E_USER_WARNING);
                            return null;
                        } else if ($classes['*']) {
                            trigger_error("Cannot instantiate object without class name.", E_USER_WARNING);
                            return null;
                        }

                        reset($classes);
                        $class = key($classes);

                        if (!class_exists($class)) {
                            trigger_error("Cannot instantiate non-existent class $class.", E_USER_WARNING);
                            return null;
                        }

                        if (!PersistenceDB::getClassPersistenceInfo($class)) {
                            trigger_error("Cannot instantiate non-persistent class $class.", E_USER_WARNING);
                            return null;
                        }

//                        trigger_error("DUH $class $id DUH.", E_USER_WARNING);
                        return compact('class', 'id');
                    }
                } else {
                    trigger_error("Cannot instantiate object without __..._id field.", E_USER_WARNING);
                }
                return null;
            case 'array':
                trigger_error("Cannot populate array for field.", E_USER_WARNING);
                return array();
            case 'int':
                return (int)$dbValue;
            case 'float':
                return (float)$dbValue;
            case 'bool':
                return (bool)$dbValue;
            case 'null':
                return null;
//            case 'mixed':
//            case 'string':
            default:
                return $dbValue;
        }
    }

    private static function exportFieldToDB($fieldValue, $fieldDoc) {
        switch (TypeUtil::getSimpleType($fieldDoc['var'])) {
            case 'object':
                trigger_error("Cannot export object for field.", E_USER_WARNING);
                return null;
            case 'array':
                trigger_error("Cannot export array for field.", E_USER_WARNING);
                return array();
            case 'int':
                return (int)$fieldValue;
            case 'float':
                return sprintf("%37.32E", $fieldValue);
            case 'bool':
                return $fieldValue ? 1 : 0;
            case 'null':
                return 'NULL';
//            case 'mixed':
//            case 'string':
            default:
                $v = self::$db->real_escape_string((string)$fieldValue);
                return "'$v'";
        }
    }

    private static function stringFieldDBType($fieldPersistDoc, $binary) {
        if ($fieldPersistDoc['length'] >= 1) {
            if ($fieldPersistDoc['length'] <= 0xFF) {

                $L = (int)$fieldPersistDoc['length'];
                return $binary ? "varbinary($L)" : "varchar($L)";

            } else if ($fieldPersistDoc['length'] <= 0xFFFF) {
                return $binary ? 'blob' : 'text';
            } else if ($fieldPersistDoc['length'] <= 0xFFFFFF) {
                return $binary ? 'mediumblob' : 'mediumtext';
            } else {//if ($fieldPersistDoc['length'] <= 0xFFFFFFFF) {
                return $binary ? 'longblob' : 'longtext';
            }
        } else {
            return $binary ? 'blob' : 'text';
        }
    }

    private static function getSQLDeclaration($doc, $annotationKey, $table, $field, &$keys) {
        $persistVars = $doc[$annotationKey];

        if ($persistVars === true) {
            $persistVars = array();
        }

        $typeDescriptor = $doc['var'];
        $simpleType = TypeUtil::getSimpleType($typeDescriptor);

//                    echo "\n---createTables $class $field---\n";
//                    var_dump($simpleType);
//                    var_dump($typeDescriptor);

        switch ($simpleType) {
            case 'mixed':
            case 'string':
                $sqlType = self::stringFieldDBType($persistVars, false);
                break;

            case 'null':
                $sqlType = self::stringFieldDBType(array('length' => 1), false);
                break;

            case 'float':
                $sqlType = 'DOUBLE PRECISION';
                break;

            case 'int':
                $sqlType = 'INT';
                break;

            case 'bool':
                $sqlType = 'TINYINT';
                break;

            default:
                throw new InvalidAnnotationException("@$annotationKey - $table $field has type $simpleType.");
        }

        if ($simpleType === 'null' || $typeDescriptor['null']) {
            $sqlType .= ' NULL';
        } else {
            $sqlType .= ' NOT NULL';
        }

        if ($doc['persist']['unique']) {
            $keys[] = "UNIQUE `unique_$field` ($field)";

        } else if ($doc['persist']['index']) {
            $keys[] = "INDEX `index_$field` ($field)";
        }

        return $sqlType;
    }
}