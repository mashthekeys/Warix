<?php
namespace Framework\mysqli;


use Framework\ClassRegistry;
use Framework\ClassRegistryUtils;
use Framework\InvalidAnnotationException;
use Framework\NamespaceUtil;
use Framework\PersistenceDB;
use Framework\TypeUtil;

class SchemaController {
    const CHARSET = 'utf8mb4';
    const COLLATION = 'utf8mb4_bin';

    public static function getTableName($type) {
        list($namespace, $class) = NamespaceUtil::splitClass($type);

        $short = PersistenceDB::$namespaceShorten[$namespace];
        if (strlen($short)) {
            $namespace = $short;
        }
//        return strtr("$namespace\\$class",array('¦'=>'¦¦','\\'=>'¦'));
        return strtr("$namespace\\$class",array('\\'=>'$'));
    }

    public static function getFQClassFromTableName($tableName) {
//        $tableName = strtr($tableName,array('¦'=>'\\'));
//        $tableName = strtr($tableName,array('\\\\'=>'¦'));
        $tableName = strtr($tableName,array('$$' => '::','$'=>'\\'));

        $split = strrpos($tableName, '\\');

        if ($split === false) {
            $namespace = PersistenceDB::$namespaceShortenLookup[''];
            $class = $tableName;
        } else {
            $namespace = substr($tableName, 0, $split);
            $class = substr($tableName, $split+1);

            $short = PersistenceDB::$namespaceShortenLookup[$namespace];
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
            $namespace = PersistenceDB::$namespaceShortenLookup[''];
            $class = $tableName;
        } else {
            $namespace = substr($tableName, 0, $split);
            $class = substr($tableName, $split+1);

            $short = PersistenceDB::$namespaceShortenLookup[$namespace];
            if (strlen($short)) {
                $namespace = $short;
            }
        }

        return compact('namespace','class');
    }

    public static function parseDBField($dbValue, $fieldDoc, $proxyFieldDoc = null) {
        switch (TypeUtil::getSimpleType($fieldDoc['var'])) {
            case 'object':
                if ($fieldDoc['var']['null'] && !$dbValue) {
                    return null;
                } else {
                    $classes = $fieldDoc['var']['object'];
                    $id = $dbValue;

                    if (count($classes) == 0) {
                        trigger_error("Cannot instantiate object without any class name.", E_USER_WARNING);
                        return null;
                    } else if (count($classes) == 0) {
                        trigger_error("Cannot instantiate object with multiple class names.", E_USER_WARNING);
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

                    return PersistenceDB::findById($class,$id);
//                    $foreignKey = ltrim(ClassRegistryUtils::findMemberWithRole($class, 'id'),'$');
//                    $foreignDoc = PersistenceDB::getMemberPersistenceInfo($class,$foreignKey);
                }
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

    public static function exportFieldToDB($fieldValue, $fieldDoc) {
        $varAnnotation = $fieldDoc['var'];
        $simpleType = TypeUtil::getSimpleType($varAnnotation);
        switch ($simpleType) {
            case 'object':
                if ($fieldValue === null) {
                    return null;
                } else {
                    $class = get_class($fieldValue);
                    $member = ClassRegistryUtils::findMemberWithRole($class, 'id');
                    $key = ltrim($member, '$');
                    if (!strlen($key)) {
                        trigger_error("Cannot export object for field.", E_USER_WARNING);
                        return null;
                    } else {
                        $exportValue = $fieldValue->$key;
                        return self::_exportScalarToDB($exportValue, ClassRegistry::getMemberAnnotations($class,$member));
                    }
                }

            case 'array':
                $arrayTypes = $varAnnotation['array'];
                $arrayType = TypeUtil::getSimpleType($arrayTypes);

                if ($arrayType === 'object') {
                    // TODO this requires MM writes!
//                    trigger_error("Cannot export object array for field.", E_USER_WARNING);

                } else if ($arrayType === 'array') {
//                    trigger_error("Cannot export nested array for field.", E_USER_WARNING);

                } else {
                    $valuesEnc = array_map(function ($value) use ($arrayTypes) {
                        return self::_exportScalarToDB($value, $arrayTypes);
                    }, $fieldValue);
                    return implode('|', $valuesEnc);
                }
                trigger_error("Cannot export array for field.", E_USER_WARNING);
                return array();

            default:
                return self::_exportScalarToDB($fieldValue, $varAnnotation);
        }
    }
    private static function _exportScalarToDB($fieldValue, $varAnnotation) {
        $simpleType = TypeUtil::getSimpleType($varAnnotation);
        switch ($simpleType) {
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
                $v = PersistenceDB::escapeString((string)$fieldValue);
                return "'$v'";
        }
    }

    public static function stringFieldDBType($fieldPersistDoc, $binary, $collation = self::COLLATION) {
        $length = $fieldPersistDoc['length'];
        if ($length >= 1) {
            if ($length <= 0xFF) {

                $L = (int)$length;
                $type = $binary ? "varbinary($L)" : "varchar($L)" . ' COLLATE '. $collation;

            } else if ($length <= 0xFFFF) {
                $type = $binary ? 'blob' : 'text' . ' COLLATE '. $collation;
            } else if ($length <= 0xFFFFFF) {
                $type = $binary ? 'mediumblob' : 'mediumtext' . ' COLLATE '. $collation;
            } else {//if ($fieldPersistDoc['length'] <= 0xFFFFFFFF) {
                $type = $binary ? 'longblob' : 'longtext' . ' COLLATE '. $collation;
            }
        } else {
            $type = $binary ? 'blob' : 'text' . ' COLLATE '. $collation;
        }
        return $type;
    }

    public static function getSQLDeclaration($doc, $annotationKey, $table, $field, &$keys) {
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
                $sqlType = self::stringFieldDBType($persistVars, $persistVars['binary']);
                break;

            case 'null':
                $sqlType = self::stringFieldDBType(array('length' => 1), $persistVars['binary']);
                break;

            case 'float':
                $sqlType = 'DOUBLE PRECISION';
                break;

            case 'int':
                $sqlType = 'int(11)';
                break;

            case 'bool':
                $sqlType = 'TINYINT';
                break;

            case 'object':
                // foreign key lookup
                $classes = $doc['var']['object'];

                if (count($classes) == 0) {
                    throw new InvalidAnnotationException("@$annotationKey - $table $field must specify object class.");
                } else if ($classes['*']) {
                    throw new InvalidAnnotationException("@$annotationKey - $table $field must specify object class.");
                } else if (count($classes) > 1) {
                    throw new InvalidAnnotationException("@$annotationKey - $table $field cannot specify multiple object classes.");
                }

                reset($classes);
                $foreignClass = key($classes);

                $foreignKey = ltrim(ClassRegistryUtils::findMemberWithRole($foreignClass, 'id'),'$');
                $memberInfo = PersistenceDB::getMemberPersistenceInfo($foreignClass);
                $foreignDoc = $memberInfo['persistent_fields'][$foreignKey];

                // could add an automatic index on the field at ths stage

                $ignored = []; // prevent keys being generated
                $foreignDoc['role'] = []; // remove the 'id' role
                return self::getSQLDeclaration($foreignDoc,'persist',$table,$field,$ignored);
                break;

            case 'array':
                $arrayTypes = $doc['var']['array'];
                $arrayType = TypeUtil::getSimpleType($arrayTypes);

                if ($arrayType === 'object') {
                    // TODO implement MM lookup...

                    reset($arrayTypes);
                    $reflectedClass = key($reflectedClasses);
                    $reflectedClassInfo = PersistenceDB::getMemberPersistenceInfo($reflectedClass);

                    if ($doc['persist']['reflect']) {
                        // TODO no local storage, so no field type to return
                        return null;
                    } else if ($reflectedClassInfo['doc']['persist']['embedded']) {
                        $mmTable = $table.'$$'.$field;

                        $embedded_fields = $reflectedClassInfo['foreign_fields'] + $reflectedClassInfo['persistent_fields'];

                        $mmTableFields = [$field => $doc] + $embedded_fields;

                    } else {
                        $mmTable = $table.'$$'.$field;

                        $mmTableFields = [
                            $field => $doc,
                            $reflectedField => $reflectedDoc,
                        ];

                        $mmTableKeys[$field] = $field;
                        $mmTableKeys[$reflectedField] = $reflectedField;
                    }

                    throw new InvalidAnnotationException("TODO implement MM lookup");

                } else if ($arrayType === 'array') {
                    throw new InvalidAnnotationException("@$annotationKey - $table $field cannot be multi-dimensional array.");

                } else {
                    // literal array is implemented as a large text field
                    $sqlType = self::stringFieldDBType($doc['persist'], false);
                }
                break;

            default:
                throw new InvalidAnnotationException("@$annotationKey - $table $field has type $simpleType.");
        }

        if ($simpleType === 'null' || $typeDescriptor['null']) {
            $sqlType .= ' NULL';
        } else {
            $sqlType .= ' NOT NULL';
        }

        if ($doc['role']['id']) {
            if (TypeUtil::getSimpleType($doc['var']) === 'int') {
                $sqlType .= ' AUTO_INCREMENT';
            }
        }

        if ($doc['persist']['unique']) {
            $keys["UNIQUE KEY `unique_$field`"] = "UNIQUE KEY `unique_$field` (`$field`)";

        } else if ($doc['persist']['index']) {
            $keys["KEY `index_$field`"] = "KEY `index_$field` (`$field`)";
        }

        return $sqlType;
    }

    public static function createTable($table, $fields, $keys, $options) {
        $expectedCreateTable = "CREATE TABLE `$table` (";
        $expectedDefinitions = array_merge($fields, $keys);
        $expectedTableOptions = ") " . $options;

        $createTable = $expectedCreateTable . "\n  "
            . implode(",\n  ", $expectedDefinitions) . "\n"
            . $expectedTableOptions;

        $ok = PersistenceDB::query($createTable);

        if (!$ok) {
            throw new \ErrorException("PersistenceDB error.");
        }
    }

    public static function alterTable($table, $fields, $keys, $options) {
        $expectedCreateTable = "CREATE TABLE `$table` (";
        $expectedDefinitions = array_merge($fields, $keys);
        $expectedTableOptions = ") " . $options;

        $createTable = $expectedCreateTable . "\n  "
            . implode(",\n  ", $expectedDefinitions) . "\n"
            . $expectedTableOptions;

        $res = PersistenceDB::query("SHOW CREATE TABLE `$table`");
        $row = $res->fetch_row();

        $currentTable = $row[1];

        $currentTable = preg_replace('~\sAUTO_INCREMENT=[0-9]+\b~iu', '', $currentTable, 1);

        $ok = strcasecmp($createTable, $currentTable) == 0;

        if (!$ok) {
            $currentParts = preg_split("~,?[ \t]*\r?\n\r?[ \t]*~", trim($currentTable));
            $currentCreateTable = array_shift($currentParts);
            $currentTableOptions = array_pop($currentParts);

            // If the CREATE TABLE... part of the query is not understood,
            // it is not worth trying to read the rest of the query.
            $understandDialect = 0 == strcasecmp($currentCreateTable, $expectedCreateTable)
                && $currentTableOptions{0} === ')';

            if (!$understandDialect) {
                throw new \ErrorException("Cannot understand the dialect of the SQL server.");
            }

            $currentDefinitions = [];
            foreach ($currentParts as $definition) {
                if (preg_match('~^(PRIMARY KEY|UNIQUE KEY *`[^` ]+`|KEY *`[^` ]+`|`([^` ]+)`)~iu', $definition, $match)) {
                    if (strlen($match[2])) {
                        $currentDefinitions[$match[2]] = $definition;
                    } else {
                        $currentDefinitions[$match[0]] = $definition;
                    }
                } else {
                    // unmatchable
                    $currentDefinitions[] = $definition;
                }
            }

            $mergedKeys = array_keys(array_merge($expectedDefinitions, $currentDefinitions));
            $dropKeyQuery = array();
            $dropFieldQuery = array();
            $createAlterFieldQuery = array();
            $createKeyQuery = array();
            $after = null;

            foreach ($mergedKeys as $name) {
                $expectedDefinition = $expectedDefinitions[$name];
                $currentDefinition = $currentDefinitions[$name];

                $isKey = strpos($name, ' ') !== false;

                if ($isKey) {
                    if ($currentDefinition === null) {
                        $createKeyQuery[] = "ALTER TABLE `$table` ADD $expectedDefinition";

                    } else if ($expectedDefinition === null) {
                        preg_match('~`([^`]+)`$~D', $name, $match);
                        $keyName = $match[1];
                        $dropKeyQuery[] = "ALTER TABLE `$table` DROP `$keyName`";

                    } else if (strcasecmp($expectedDefinition, $currentDefinition) != 0) {
                        preg_match('~`([^`]+)`$~D', $name, $match);
                        $keyName = $match[1];
                        $dropKeyQuery[] = "ALTER TABLE `$table` DROP `$keyName`";
                        $createKeyQuery[] = "ALTER TABLE `$table` ADD $expectedDefinition";
                    }


                } else {
                    if ($currentDefinition === null) {
                        $q = "ALTER TABLE `$table` ADD $expectedDefinition";

                        if ($after !== null) {
                            $q .= " AFTER `$after`";
                        }

                        $createAlterFieldQuery[] = $q;

                    } else if ($expectedDefinition === null) {
                        $dropFieldQuery[] = "ALTER TABLE `$table` DROP `$name`";

                    } else if (strcasecmp($expectedDefinition, $currentDefinition) != 0) {
                        $createAlterFieldQuery[] = "ALTER TABLE `$table` CHANGE "
                            . "`$name` $expectedDefinition";
                    }

                    $after = $name;
                }
            }

            // should check table options

            $correctiveQueries = array_merge(
                $dropKeyQuery,
                $createAlterFieldQuery,
                $createKeyQuery
            );

//            if ($AUTHORIZED_TO_DROP_FIELDS) {
//                $correctiveQueries = array_merge(
//                    $correctiveQueries,
//                    $dropFieldQuery
//                );
//            }

            if (!count($correctiveQueries)) {
                // table is correct enough!
                $ok = true;
            } else {
                $ok = true;
                foreach ($correctiveQueries as $correctiveQuery) {
                    $ok = PersistenceDB::query($correctiveQuery) && $ok;
                }
            }
        }
        return $ok;
    }

    public static function describeAlterTable($table, $fields, $keys, $options) {
        $expectedCreateTable = "CREATE TABLE `$table` (";
        $expectedDefinitions = array_merge($fields, $keys);
        $expectedTableOptions = ") " . $options;

        $createTable = $expectedCreateTable . "\n  "
            . implode(",\n  ", $expectedDefinitions) . "\n"
            . $expectedTableOptions;

        $res = PersistenceDB::query("SHOW CREATE TABLE `$table`");
        $row = $res->fetch_row();

        $currentTable = $row[1];

        $currentTable = preg_replace('~\sAUTO_INCREMENT=[0-9]+\b~iu', '', $currentTable, 1);

        $ok = strcasecmp($createTable, $currentTable) == 0;

        if (!$ok) {
            $currentParts = preg_split("~,?[ \t]*\r?\n\r?[ \t]*~", trim($currentTable));
            $currentCreateTable = array_shift($currentParts);
            $currentTableOptions = array_pop($currentParts);

            // If the CREATE TABLE... part of the query is not understood,
            // it is not worth trying to read the rest of the query.
            $understandDialect = 0 == strcasecmp($currentCreateTable, $expectedCreateTable)
                && $currentTableOptions{0} === ')';

            if (!$understandDialect) {
                $output = "Cannot understand the dialect of the SQL server.";
            }

            $expectedDisplay = '';
            $currentDisplay = '';

            list($eOut, $cOut) = self::visualCompare($expectedCreateTable, $currentCreateTable);
            $expectedDisplay .= "$eOut\n";
            $currentDisplay .= "$cOut\n";

            $currentDefinitions = [];
            foreach ($currentParts as $definition) {
                if (preg_match('~^(PRIMARY KEY|UNIQUE KEY *`[^` ]+`|KEY *`[^` ]+`|`([^` ]+)`)~iu', $definition, $match)) {
                    if (strlen($match[2])) {
                        $currentDefinitions[$match[2]] = $definition;
                    } else {
                        $currentDefinitions[$match[0]] = $definition;
                    }
                } else {
                    // unmatchable
                    $currentDefinitions[] = $definition;
                }
            }

            $mergedKeys = array_keys(array_merge($expectedDefinitions, $currentDefinitions));
            $dropKeyQuery = array();
            $dropFieldQuery = array();
            $createAlterFieldQuery = array();
            $createKeyQuery = array();
            $after = null;

            foreach ($mergedKeys as $name) {
                $expectedDefinition = $expectedDefinitions[$name];
                $currentDefinition = $currentDefinitions[$name];

                $isKey = strpos($name, ' ') !== false;

                if ($isKey) {
                    if ($currentDefinition === null) {
                        $createKeyQuery[] = "ALTER TABLE `$table` ADD $expectedDefinition";

                    } else if ($expectedDefinition === null) {
                        preg_match('~`([^`]+)`$~D', $name, $match);
                        $keyName = $match[1];
                        $dropKeyQuery[] = "ALTER TABLE `$table` DROP `$keyName`";

                    } else if (strcasecmp($expectedDefinition, $currentDefinition) != 0) {
                        preg_match('~`([^`]+)`$~D', $name, $match);
                        $keyName = $match[1];
                        $dropKeyQuery[] = "ALTER TABLE `$table` DROP `$keyName`";
                        $createKeyQuery[] = "ALTER TABLE `$table` ADD $expectedDefinition";
                    }


                } else {
                    if ($currentDefinition === null) {
                        $q = "ALTER TABLE `$table` ADD $expectedDefinition";

                        if ($after !== null) {
                            $q .= " AFTER `$after`";
                        }

                        $createAlterFieldQuery[] = $q;

                    } else if ($expectedDefinition === null) {
                        $dropFieldQuery[] = "ALTER TABLE `$table` DROP `$name`";

                    } else if (strcasecmp($expectedDefinition, $currentDefinition) != 0) {
                        $createAlterFieldQuery[] = "ALTER TABLE `$table` CHANGE "
                            . "`$name` $expectedDefinition";
                    }

                    $after = $name;
                }

                list($eOut, $cOut) = self::visualCompare($expectedDefinition, $currentDefinition);
                $expectedDisplay .= "--$name\n";
                $expectedDisplay .= "  $eOut\n";

                $currentDisplay .= "--$name\n";
                $currentDisplay .= "  $cOut\n";
            }

            // should check table options

            $correctiveQueries = array_merge(
                $dropKeyQuery,
                $createAlterFieldQuery,
                $createKeyQuery
            );

//            if ($AUTHORIZED_TO_DROP_FIELDS) {
//                $correctiveQueries = array_merge(
//                    $correctiveQueries,
//                    $dropFieldQuery
//                );
//            }

            if (!count($correctiveQueries)) {
                // table is correct enough!
                $output = "<div style='width:100%;'>"
                    . "<pre style='width:50%;float:left;'>"
                    . "MATCHED:\n"
                    . "$expectedDisplay</pre>"
                    . "<pre style='width:50%;float:right;'>"
                    . "MATCHED BY MYSQL:\n"
                    . "$currentDisplay</pre>"
                    . "<pre style='width: 100%;clear:both'></pre></div>";
            } else {
                $alterQuery = implode(";\n\n", $correctiveQueries);

                list($eOut, $cOut) = self::visualCompare($expectedTableOptions, $currentTableOptions);
                $expectedDisplay .= "$eOut\n";
                $currentDisplay .= "$cOut\n";

                $output = "<div style='width:100%;'>"
                    . "<pre style='width:50%;float:left;'>"
                    . "EXPECTED:\n"
                    . "$expectedDisplay</pre>"
                    . "<pre style='width:50%;float:right;'>"
                    . "REPORTED BY MYSQL:\n"
                    . "$currentDisplay</pre>"
                    . "<pre style='width: 100%;clear:both'>$alterQuery</pre></div>";
            }
        } else {
            $output = "<div style='width:100%;'>"
                . "<pre style='width:50%;float:left;'>"
                . "MATCHED: (identical)\n"
                . "$createTable</pre>"
                . "<pre style='width:50%;float:right;'>"
                . "MATCHED BY MYSQL:\n"
                . "$currentTable</pre>"
                . "<pre style='width: 100%;clear:both'></pre></div>";
        }
        return $output;
    }

    private static function visualCompare($expected,$current) {
        if (strcasecmp($expected,$current) == 0) {
            $expected = "<span style='color:green'>$expected</span>";
            $current = "<span style='color:green'>$current</span>";
        } else {
            $expected = "<ins style='color:#900'>$expected</ins>";
            $current = "<del style='color:#900'>$current</del>";
        }

        return array($expected,$current);
    }

    public static function tableOptions() {
        return "ENGINE=InnoDB DEFAULT CHARSET=".self::CHARSET." COLLATE=".self::COLLATION;
    }

    public static function getPrimaryKeyDeclaration($primary) {
        return "PRIMARY KEY (`$primary`)";
    }
}