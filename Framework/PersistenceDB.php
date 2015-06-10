<?php
namespace Framework;


use Framework\mysqli\SchemaController;
use Framework\TypeUtil;

class PersistenceDB {
    /** Maximum recursion limit when loading objects from the database. */
    const STACK_LIMIT = 5;

    /** Do not make queries on this field.  Use PersistenceDB::query() instead.
     * @var \mysqli
     */
    private static $db;

    public static $namespaceShorten = array();
    public static $namespaceShortenLookup = array();
    private static $dbFilters = array();
    private static $dbLoggers = array();
    private static $cache = array();
    private static $classPersistence = array();


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

    public static function createTables($checkStructure = false) {
//        echo "\nCREATE TABLES\n";
        self::connect();

        $persistClasses = ClassRegistry::listAnnotatedClasses('persist');
//        echo "\$persistClasses = (",var_dump($persistClasses),")\n";

        $table_exists = array();
        $res = self::query('SHOW TABLES');
        while ($row = $res->fetch_row()) {
            $table_exists[$row[0]] = true;
        }

        foreach ($persistClasses as $class) {
            $classInfo = self::getMemberPersistenceInfo($class);
//            echo "\$classInfo = (",var_dump($classInfo),")\n";

            $table = $classInfo['table'];

            $fields = array();
            $keys = array();

//            $matchTable = strtr($table, array('_'=>'\\\\_','%'=>'\\\\%'));

            $exists = $table_exists[$table];
//            $exists = self::query("SHOW TABLES LIKE '$matchTable'")->num_rows;

            if ($checkStructure || !$exists) {
                $primary = ltrim(ClassRegistryUtils::findMemberWithRole('id', $class),'$');
                if (!strlen($primary)) {
                    throw new InvalidAnnotationException("@persist - $table HAS NO PRIMARY KEY\n");
                }

                $keys['PRIMARY KEY'] = $primary;

                foreach ($classInfo['foreign_fields'] as $field => $doc) {
                    $sqlType = SchemaController::getSQLDeclaration($field, 'persist', $table, $field, $keys);
                    $fields[$field] = "`$field` $sqlType";
                }
                foreach ($classInfo['persistent_fields'] as $field => $doc) {
                    $sqlType = SchemaController::getSQLDeclaration($doc, 'persist', $table, $field, $keys);
                    $fields[$field] = "`$field` $sqlType";
                }
                foreach ($classInfo['sensitive_fields'] as $field => $doc) {
                    $sqlType = SchemaController::getSQLDeclaration($doc, 'persistSensitive', $table, $field, $keys);
                    $fields[$field] = "`$field` $sqlType";
                }


                if (count($fields) == 0) {
                    throw new InvalidAnnotationException("@persist - $table HAS NO FIELDS\n");
                }

                $options = SchemaController::tableOptions();

                if ($exists) {
                    SchemaController::alterTable($table, $fields, $keys, $options);
                } else {
                    SchemaController::createTable($table, $fields, $keys, $options);
                }
            }
        }
//        echo "\nEND CREATE TABLES\n";
        return true;
    }

    /**
     * @param $class
     * @return array|bool
     */
    public static function getClassPersistenceInfo($class) {
        $doc = ClassRegistry::getClassAnnotations($class);

//        if ($doc['Framework'] && $doc['persist']) {
        if ($doc['persist']) {
            return array(
                'class' => $class,
                'doc' => $doc,
                'table' => SchemaController::getTableName($class),
            );
        } else {
            return false;
        }
    }

    /**
     * @param string $class
     * @param string|null $field
     * @return array|bool
     */
    public static function getMemberPersistenceInfo($class, $field = null) {
//        list($namespace, $class) = NamespaceUtil::splitClass($type);

        if (self::$classPersistence[$class]) {
            $classInfo = self::$classPersistence[$class];
        } else {
            $classInfo = self::getClassPersistenceInfo($class);

            if (!$classInfo) return false;

            $memberDoc = ClassRegistry::getMemberAnnotations($class);
            $persistMembers = array();
            $persistent_fields = array();
            $sensitive_fields = array();
            $foreign_field_overrides = array();
            $auto_foreign_fields = array();

            foreach ($memberDoc as $member => $doc) {
                if ($doc['persistSensitive'] || $doc['persist']) {
                    if ($member{0} !== '$') {
                        // ignore const and functions
                    } else if (substr($member, 1, 2) === '__' && substr($member, -3) === '_id') {
                        // Fields in the form $__..._id usually represent the ID
                        // of a persistent object field. The field does not normally
                        // need an @persist declaration.  Any @persist declaration
                        // will override the persist parameters of the object's id
                        // field.
                        $fieldName = substr($member, 3, -3);
                        $foreign_field_overrides[$fieldName] = $doc;

                        if ($doc['persistSensitive']) {
                            $sensitive_fields[$fieldName] = $doc;
                        } else if ($doc['persist']) {
                            $persistent_fields[$fieldName] = $doc;
                        }
                    } else {
                        $persistMembers[$member] = $memberDoc;
                    }
                }
            }
            foreach ($persistMembers as $member => $doc) {
                $fieldName = substr($member, 1);
                if ($doc['persistSensitive']) {
                    $sensitive_fields[$fieldName] = $doc;
                } else if ($doc['persist']) {
                    $persistent_fields[$fieldName] = $doc;

                    $objectClass = $doc['type']['object'];
                    if ($objectClass) {
                        $fieldClass = array_pop($objectClass);

                        $classPersistenceInfo = PersistenceDB::getClassPersistenceInfo($fieldClass);

                        if (!$classPersistenceInfo) {
                            trigger_error("Field $class::$member cannot store non-persistent $fieldClass objects.", E_USER_WARNING);
                        } else {
                            $foreignKey = ClassRegistryUtils::findMemberWithRole($fieldClass, 'id');
                            if (!$foreignKey) {
                                trigger_error("Field $class::$member cannot find ID field for $fieldClass objects.", E_USER_WARNING);
                            } else {
                                $copyDoc = ClassRegistry::getMemberAnnotations($fieldClass, $foreignKey);
                                $copyDoc['role'] = 'objectRef';
                                $auto_foreign_fields[$fieldName] = $copyDoc;
                            }
                        }
                    }
                }
            }

            // Local parameters to @persist override those loaded from the object's class
            $foreign_fields = $foreign_field_overrides + $auto_foreign_fields;

            $classInfo['persistent_fields'] = $persistent_fields;
            $classInfo['sensitive_fields'] = $sensitive_fields;
            $classInfo['foreign_fields'] = $foreign_fields;
            self::$classPersistence[$class] = $classInfo;
        }
        if ($field === null) {
            return $classInfo;
        } else {
            $doc = $classInfo[$field];

            if (!($doc['persist'] || $doc['persistSensitive'])) {
                return false;
            }

            return $doc;
        }
    }


    public static function findById($type, $id) {
        $stack = array(array(__FUNCTION__, $type, 'query'));

        return self::_findById($type, $id, $stack);
    }
    public static function _findById($type, $id, $stack) {
        if (!strlen($type)) {
            throw new \InvalidArgumentException("Class name cannot be empty or null.");
        }
        if ($id === null) {
            throw new \InvalidArgumentException("$type ID cannot be null.");
        } else if (!is_scalar($id)) {
            throw new \InvalidArgumentException("$type ID cannot be ".gettype($id));
        }

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
        if (!strlen($type)) {
            throw new \RuntimeException("Class name cannot be empty or null.");
        }
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
                    $itemFields["__{$fieldName}_id"] = SchemaController::parseDBField($row[$fieldName], $fieldDoc);
                }
                foreach ($persistent_fields as $fieldName => $fieldDoc) {
                    $idProxyDoc = $foreign_fields[$fieldName];
                    if ($idProxyDoc) {
//                        $lookupClass = reset(array_keys($fieldDoc['var']['object']));
//
//                        $lookupId = SchemaController::parseDBField($row[$fieldName], $fieldDoc, $idProxyDoc);
//
//                        var_dump($lookupId);
//
//                        $lookupInfo = array(
//                            'class' => $lookupClass,
//                            'id' => $lookupId
//                        );
                        $lookupInfo = SchemaController::parseDBField($row[$fieldName], $fieldDoc, $idProxyDoc);
                        if ($lookupInfo === null) {
                            $itemFields[$fieldName] = null;
                        } else {
                            $objectFields[$fieldName] = $lookupInfo;
                        }
                    } else {
                        $itemFields[$fieldName] = SchemaController::parseDBField($row[$fieldName], $fieldDoc);
                    }
                }
                foreach ($sensitive_fields as $fieldName => $fieldDoc) {
                    $itemFields[$fieldName] = SchemaController::parseDBField($row[$fieldName], $fieldDoc);
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
                    if (!is_array($lookupInfo)) {
                        throw new \RuntimeException(var_export($objectFields, 1));
                    } else if (empty($lookupInfo['id'])) {
                        throw new \RuntimeException(var_export($objectFields,1));
                    } else if (empty($lookupInfo['class'])) {
                        throw new \RuntimeException(var_export($objectFields,1));
                    }

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

            $idProxyField = "__{$fieldName}_id";

            $idProxyDoc = $info['foreign_fields'][$fieldName];
            if ($idProxyDoc) {
//                if (is_object($item->$fieldName)) {
//                    $item->$fieldName
//                }

                // object is ignored, the __..._id field determines the field by itself
                $update[$fieldName] = $dbg = SchemaController::exportFieldToDB($item->$idProxyField, $idProxyDoc);

//                if ($fieldName === 'template') {
//                    $var = $idProxyDoc['var'];
//                    $type = TypeUtil::getSimpleType($var);
//                    throw new \ErrorException("[$type] __template_id={$item->__template_id} ".gettype($item->__template_id).". _template_id= {$item->_template_id} ".gettype($item->_template_id)."");
//                    throw new \ErrorException(var_export($var,1)."[$type] $fieldName = $dbg ".gettype($dbg)." = {$item->_template_id} ".gettype($item->_template_id)."");
//                    throw new \RuntimeException("TEMPLATE VALUE WRITE: $dbg /$idProxyField  {$item->$idProxyField}");
//                }

//                if (!$item->$idProxyField) {
//                    echo \CMS\HTMLUtils::pre_export($item);
//                    throw new \ErrorException("$idProxyField empty: '{$item->$idProxyField}''");
//                }
            } else {
                $update[$fieldName] = SchemaController::exportFieldToDB($item->$fieldName, $fieldDoc);
            }
        }

        foreach ($info['sensitive_fields'] as $fieldName => $fieldDoc) {
            if (strlen($fieldDoc['persistSensitive']['onStore'])) {
                call_user_func($fieldDoc['persistSensitive']['onStore'], $item);
            }

            // TODO how do we read the private field ?!
            // AKA, hello Andy, I see you're dealing with the User class
            $update[$fieldName] = SchemaController::exportFieldToDB($item->$fieldName, $fieldDoc);
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

    public static function escapeString($param) {
        return self::$db->real_escape_string($param);
    }
}