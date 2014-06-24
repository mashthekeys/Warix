<?php
namespace Framework;

class Query {
    public static function matchAll() {
        return new Query(
//            array('cmp', '==',
//                array('value', 1),
                array('value', 1)
//            )
        );
    }

    public static function compareField($field, $comparator, $value) {
        return new Query(
            array('cmp', $comparator,
                array('field', null, $field),
                array('value', $value),
            )
        );
    }

    private $root;

    function __construct($root) {
        if (is_array($root)) {
            $this->root = $root;
        }
    }

    public function makeSQL($contextClass) {
        $info = self::exprInfo($this->root, $contextClass);
        return $info['sql'];
    }
    private static function exprInfo($expr, $contextClass) {
        $exprType = $expr[0];
        switch ($exprType) {
            case 'not':
                $argInfo = self::exprInfo($expr[1], $contextClass);

                $canBeNull = false;
                $exprType = 'bool';
                $sql = "($argInfo[sql] IS NOT TRUE)";
                break;

            case 'minus':
                $argInfo = self::exprInfo($expr[1], $contextClass);

                $canBeNull = false;
                $exprType = $argInfo['exprType'];
                $sql = "(-$argInfo[sql])";
                break;

            case 'cmp':
                $comparator = $expr[1];
                $lhs = $expr[2];
                $rhs = $expr[3];
                $lhsInfo = self::exprInfo($lhs, $contextClass);
                $rhsInfo = self::exprInfo($rhs, $contextClass);

                $canBeNull = false;
                $exprType = 'bool';
                switch ($comparator) {
                    case 'AND':
                    case 'and':
                        $sql = "($lhsInfo[sql] AND $rhsInfo[sql])";
                        break;

                    case 'OR':
                    case 'or':
                        $sql = "($lhsInfo[sql] OR $rhsInfo[sql])";
                        break;

                    case 'LIKE':
                    case 'like':
                        $sql = "($lhsInfo[sql] LIKE $rhsInfo[sql])";
                        break;

                    case 'REGEX':
                    case 'REGEXP':
                    case 'regex':
                    case 'regexp':
                        $sql = "($lhsInfo[sql] REGEX $rhsInfo[sql])";
                        break;

                    case '==':
                    case '=':
                        if ($lhsInfo['canBeNull'] && $rhsInfo['canBeNull']) {
                            //http://dev.mysql.com/doc/refman/5.0/en/comparison-operators.html#operator_equal-to
                            $sql = "($lhsInfo[sql]<=>$rhsInfo[sql])";
                        } else {
                            // IS TRUE converts NULL to FALSE, ensuring boolean type
                            $sql = "($lhsInfo[sql]=$rhsInfo[sql] IS TRUE)";
                        }
                        break;

                    case '<>':
                    case '!=':
                        if ($lhsInfo['canBeNull'] && $rhsInfo['canBeNull']) {
                            //http://dev.mysql.com/doc/refman/5.0/en/comparison-operators.html#operator_equal-to
                            $sql = "NOT($lhsInfo[sql]<=>$rhsInfo[sql])";
                        } else {
                            $sql = "($lhsInfo[sql]=$rhsInfo[sql] IS NOT TRUE)";
                        }
                        break;

                    case '>':
                    case '>=':
                    case '<':
                    case '<=':
                        $sql = "($lhsInfo[sql]$comparator$rhsInfo[sql] IS TRUE)";
                        break;

                    default:
                        throw new QueryParseException("Unknown operator: $comparator");
                }
                break;

            case 'function':
                $funcName = $expr[1];

                $args = array_values($expr);
                array_shift($args);
                array_shift($args);

                $canBeNull = true;
                $exprType = 'mixed';
                $sql = "$funcName(".implode(',',array_map(array('Query','operandInfo'),$args)).")";
                break;

            case 'field':
                $class = is_null($expr[1]) ? $contextClass : $expr[1];
                $field = $expr[2];

                $classInfo = PersistenceDB::getClassPersistenceInfo($class);
                $fieldInfo = PersistenceDB::getMemberPersistenceInfo($class,$field);

                $sql = "`$classInfo[table]`.`$field`";
                $exprType = $fieldInfo['var'];

                $allowedTypes = explode('|', $exprType);
                $canBeNull = in_array('null', $allowedTypes, true) || in_array('mixed', $allowedTypes, true);
                break;

            case 'value':
                $value = $expr[1];
                if (is_int($value) || is_float($value)) {
                    $sql = "$value"; // should check how best to convert to SQL
                    $exprType = is_int($value) ? 'int' : 'float';
                    $canBeNull = false;
                } else if (is_string($value)) {
                    $sql = "'" . mysql_escape_string($value) . "'";
                    $exprType = 'string';
                    $canBeNull = false;
                } else if (is_null($value)) {
                    $sql = 'NULL';
                    $exprType = 'null';
                    $canBeNull = true;
                }
                break;

            default:
                throw new QueryParseException("Unknown Query Expression Type: $exprType");
        }

        return compact('sql','exprType','canBeNull');
    }
}