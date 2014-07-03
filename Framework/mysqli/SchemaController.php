<?php
namespace Framework\mysqli;


use Framework\InvalidAnnotationException;
use Framework\NamespaceUtil;
use Framework\PersistenceDB;
use Framework\TypeUtil;

class SchemaController {
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

    public static function exportFieldToDB($fieldValue, $fieldDoc) {
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
                $v = PersistenceDB::escapeString((string)$fieldValue);
                return "'$v'";
        }
    }

    public static function stringFieldDBType($fieldPersistDoc, $binary, $collation = self::COLLATION) {
        if ($fieldPersistDoc['length'] >= 1) {
            if ($fieldPersistDoc['length'] <= 0xFF) {

                $L = (int)$fieldPersistDoc['length'];
                $type = $binary ? "varbinary($L)" : "varchar($L)" . ' COLLATE '. $collation;

            } else if ($fieldPersistDoc['length'] <= 0xFFFF) {
                $type = $binary ? 'blob' : 'text' . ' COLLATE '. $collation;
            } else if ($fieldPersistDoc['length'] <= 0xFFFFFF) {
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

            default:
                throw new InvalidAnnotationException("@$annotationKey - $table $field has type $simpleType.");
        }

        if ($simpleType === 'null' || $typeDescriptor['null']) {
            $sqlType .= ' NULL';
        } else {
            $sqlType .= ' NOT NULL';
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
}