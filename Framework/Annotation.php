<?php
namespace Framework;


class Annotation {
    /**
     * @param string|\PhpParser\Comment\Doc $doc
     * @return array
     */
    public static function parsePHPDoc($doc) {
        $annotations = array();

        if ($doc instanceof \PhpParser\Comment\Doc) {
            $phpDoc = $doc->getText();
        } else {
            $phpDoc = (string)$doc;
        }

        if (substr($phpDoc,0,3)==='/**'||substr($phpDoc,-2)==='*/') {
            $phpDoc = substr($phpDoc, 3, -2);
        }
        $lines = preg_split('/[\r\n]+/', $phpDoc);

        foreach ($lines as $line) {
            $line = trim($line, " \t*");
            if (strlen($line) && $line{0} === '@') {
                $nameLength = strcspn($line, " \t", 1);
                $name = substr($line, 1, $nameLength);

                $argString = substr($line, $nameLength + 2);

                if ($name === 'var') {
                    $args = TypeUtil::parseVarAnnotation($argString);
                } else if (!strlen($argString)) {
                    $args = true;
                } else {
                    $args = Annotation::parseArguments($argString);
                }

                $annotations[$name] = $args;
            }
        }

        return $annotations;
    }

    public static function parseArguments($args) {
        $parsed = array();

        $pos = 0;
        $END = strlen($args);

        $pos += strspn($args, " \t\r\n", $pos); // whitespace

        while ($pos < $END) {
            $nameLen = strcspn($args, ",=", $pos + 1) + 1;

            $name = rtrim(substr($args, $pos, $nameLen));

            $pos += $nameLen; // name

            $pos += strspn($args, " \t\r\n", $pos); // whitespace

            if ($args{$pos} !== '=') {
                $value = true;
                $pos += strcspn($args, ",", $pos); // skip to next comma
            } else {
                ++$pos; // =

                $pos += strspn($args, " \t\r\n", $pos); // whitespace

                $q = $args{$pos};
                if ($q === '"' || $q === "'") {
                    ++$pos; // quote

                    $valueLen = strcspn($args, $q, $pos);

                    $value = substr($args, $pos, $valueLen);

                    $pos += $valueLen + 1; // value & quote

                    $pos += strcspn($args, ",", $pos); // skip to next comma
                } else {
                    $valueLen = strcspn($args, ",", $pos);

                    $value = rtrim(substr($args, $pos, $valueLen));

                    $pos += $valueLen; // value
                }
            }

            if ($args{$pos} === ',') {
                ++$pos; // comma
            }

            $pos += strspn($args, " \t\r\n", $pos); // whitespace

            $parsed[$name] = $value;
        }

        return $parsed;
    }
}