<?php

namespace Framework;

use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\PrettyPrinterAbstract;
use PhpParser\Node;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\MagicConst;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Stmt;
use PhpParser\Node\Name;

class JSConverter extends PrettyPrinterAbstract
{

    protected $stack = [];

    const NAMESPACE_OBJECT = 'namespace';
    const CLASS_OBJECT = 'self';

    private function addToStack($context) {
        $stackId = count($this->stack);
        $this->stack[$stackId] = $context;

        return $stackId;
    }
    private function dropStack($stackId) {
        if (count($this->stack) - 1 > $stackId) {
            trigger_error('Stack mismatch: unclosed items.', E_USER_WARNING);

            $this->stack = array_slice($this->stack, 0, $stackId);

        } else if (!isset($this->stack[$stackId])) {
            trigger_error('Stack mismatch: item closed twice.', E_USER_WARNING);

        } else {
            unset($this->stack[$stackId]);
        }
    }

    public function prettyPrint(array $stmts, $__FILE__ = 'unknown source code') {
        $context = array(
            'type' => 'JSConverter',
            '__FILE__' => $__FILE__,
        );

        $this->preprocessNodes($stmts);

        $output = ltrim(str_replace("\n" . $this->noIndentToken, "\n",
            $this->pContext($stmts, false, $context)
        ));

        return $output;
    }

    public function prettyPrintFile(array $stmts, $__FILE__ = 'unknown source code') {
        $p = rtrim($this->prettyPrint($stmts, $__FILE__ = 'unknown source code'));

        if (0) {
            $p = preg_replace('/^\?>\n?/', '', $p, -1, $count);
            $p = preg_replace('/<\?php$/', '', $p);

            if (!$count) {
                $p = "<?php\n\n" . $p;
            }
        }

        return $p;
    }

    /**
     * Pretty prints an expression.
     *
     * Overrides the parent to allow newlines in expressions.
     *
     * @param Expr $node Expression node
     *
     * @return string Pretty printed node
     */
    public function prettyPrintExpr(Expr $node) {
        return $this->p($node);
    }

//    protected function preprocessNodes(array $nodes) {
//        parent::preprocessNodes($nodes);
//    }

//    protected function pImplodeAssign($prefix, array $nodes, $suffix = ";\n") {
//    }


    protected function pContext(array $nodes, $indent = true, array $context) {
        $stackId = $this->addToStack($context);

        $outerScope = $this->codeGen_varList;
        $this->codeGen_varList = [];

        $output = $this->pStmts($nodes, $indent);

        $this->dropStack($stackId);

        if (count($this->codeGen_varList)) {
            $vars = implode(', ', array_keys($this->codeGen_varList));
            $output = "\n    var $vars;$output";
//            $vars = var_export($this->codeGen_varList,1);
//            $output = "/*AUTO VARS*/\nvar $vars;\n/*AUTO VARS*/\n$output";
        }

        $this->codeGen_varList = $outerScope;

        return $output;
    }


    // Special nodes

    public function pParam(Node\Param $node) {
        return ($node->type ? (is_string($node->type) ? $node->type : $this->p($node->type)) . ' ' : '')
//             . ($node->byRef ? '&' : '')
//             . ($node->variadic ? '... ' : '')
             . '$' . $node->name
//             . ($node->default ? ' = ' . $this->p($node->default) : '');
        ;
    }

    public function pArg(Node\Arg $node) {
        return ($node->byRef ? '&' : '') . ($node->unpack ? '...' : '') . $this->p($node->value);
    }

    public function pConst(Node\Const_ $node) {
        return $node->name . ' = ' . $this->p($node->value);
    }

    // Names

    private static $PHP_KEYWORDS = ['null','true','false','parent'];

    public function pName(Name $node) {
        $class = $node->toString('.');

        if ($class === self::CLASS_OBJECT || $class === self::context__CLASS_VAR__()) {
            // leave as is
        } else if (in_array(strtolower($class), self::$PHP_KEYWORDS)) {
            // leave as is
        } else {
            $__USE_VARS__ = $this->context__USE_VARS__();
            if (is_array($__USE_VARS__) && in_array($class, array_keys($__USE_VARS__))) {
                // leave as is
            } else {
                $class = self::NAMESPACE_OBJECT . '.' . $class;
            }
        }
        return $class;
//        return implode('.', $node->parts);
    }

//    public function pName_PHPFullyQualified(Name $node) {
//        return 'PHP["\\\\' . implode('\\\\', $node->parts) . '"]';
//    }

    public function pName_FullyQualified(Name $node) {
//    public function pName_FullyQualified(Name\FullyQualified $node) {
        return 'PHP['.json_encode("\\$node").']';
//        return 'PHP["\\\\' . implode('\\\\', $node->parts) . '"]';
//        return '\\' . implode('\\', $node->parts);
    }

    public function pName_Relative(Name\Relative $node) {
        return self::NAMESPACE_OBJECT . '.' . $node->toString('.');
//        return self::NAMESPACE_OBJECT .'.' . implode('.', $node->parts);
    }

    // Magic Constants

    private function contextSearch($key) {
        $stackId = count($this->stack) - 1;

        while ($stackId >= 0) {
            $context = $this->stack[$stackId];
            $value = $context[$key];

            if (isset($value)) {
                return $value;
            }

            --$stackId;
        }
        return '';
    }

    public function contextSet($key, $value) {
        $stackId = count($this->stack) - 1;
        $this->stack[$stackId][$key] = $value;
    }

    public function context__USE_VARS__() {
        $__USE_VARS__ = $this->contextSearch('__USE_VARS__');
        return is_array($__USE_VARS__) ? $__USE_VARS__ : [];
    }

    public function context__CLASS_VAR__() {
        return $this->contextSearch('__CLASS_VAR__');
    }

    public function context__CLASS__() {
        return $this->contextSearch('__CLASS__');
    }

    public function pScalar_MagicConst_Class(MagicConst\Class_ $node) {
        return '__CLASS__';
//        return json_encode($this->contextSearch('__CLASS__'));
    }

    public function context__DIR__() {
        return $this->contextSearch('__DIR__');
    }

    public function pScalar_MagicConst_Dir(MagicConst\Dir $node) {
        return $this->contextSearch('__DIR__');
    }

    public function context__FILE__() {
        return $this->contextSearch('__FILE__');
    }

    public function pScalar_MagicConst_File(MagicConst\File $node) {
        return $this->contextSearch('__FILE__');
    }

    public function context__FUNCTION__() {
        return $this->contextSearch('__FUNCTION__');
    }

    public function pScalar_MagicConst_Function(MagicConst\Function_ $node) {
        return $this->contextSearch('__FUNCTION__');
    }

    // __LINE__ cannot be calculated without a Node
    //public function context__LINE__();

    public function pScalar_MagicConst_Line(MagicConst\Line $node) {
        return $node->getLine();
    }

    public function context__METHOD__() {
        return $this->contextSearch('__METHOD__');
    }

    public function pScalar_MagicConst_Method(MagicConst\Method $node) {
        return $this->contextSearch('__METHOD__');
    }

    public function context__NAMESPACE__() {
        return $this->contextSearch('__NAMESPACE__');
    }

    public function pScalar_MagicConst_Namespace(MagicConst\Namespace_ $node) {
        return $this->contextSearch('__NAMESPACE__');
    }

    public function context__TRAIT__() {
        return $this->contextSearch('__TRAIT__');
    }

    public function pScalar_MagicConst_Trait(MagicConst\Trait_ $node) {
        return $this->contextSearch('__TRAIT__');
    }

    // Scalars

    public function pScalar_String(Scalar\String $node) {
        return json_encode((string) $node->value,  JSON_UNESCAPED_SLASHES);
//        return '\'' . $this->pNoIndent(addcslashes($node->value, '\'\\')) . '\'';
    }

    public function pScalar_Encapsed(Scalar\Encapsed $node) {
        return $this->pEncapsList($node->parts, '"');
    }

    public function pScalar_LNumber(Scalar\LNumber $node) {
        return (string) $node->value;
    }

    public function pScalar_DNumber(Scalar\DNumber $node) {
        $floatValue = (float) $node->value;

        return json_encode($floatValue, JSON_UNESCAPED_SLASHES);
    }

    // Assignments

    public function pExpr_Assign(Expr\Assign $node) {
        if (self::isPlainVariable($node->var)) {
            $this->registerScopeVariable($node->var);
        }

        return $this->pInfixOp('Expr_Assign', $node->var, ' = ', $node->expr);
    }

    public function pExpr_AssignRef(Expr\AssignRef $node) {
        trigger_error("Assignment by reference is not supported in the JS Converter.", E_USER_NOTICE);

        if (self::isPlainVariable($node->var)) {
            $this->registerScopeVariable($node->var);
        }
        return $this->pInfixOp('Expr_AssignRef', $node->var, ' = ', $node->expr);
    }

    public function pExpr_AssignOp_Plus(AssignOp\Plus $node) {
        if (self::isPlainVariable($node->var)) {
            $this->registerScopeVariable($node->var);
        }
        return $this->pInfixOp('Expr_AssignOp_Plus', $node->var, ' += ', $node->expr);
    }

    public function pExpr_AssignOp_Minus(AssignOp\Minus $node) {
        if (self::isPlainVariable($node->var)) {
            $this->registerScopeVariable($node->var);
        }
        return $this->pInfixOp('Expr_AssignOp_Minus', $node->var, ' -= ', $node->expr);
    }

    public function pExpr_AssignOp_Mul(AssignOp\Mul $node) {
        if (self::isPlainVariable($node->var)) {
            $this->registerScopeVariable($node->var);
        }
        return $this->pInfixOp('Expr_AssignOp_Mul', $node->var, ' *= ', $node->expr);
    }

    public function pExpr_AssignOp_Div(AssignOp\Div $node) {
        if (self::isPlainVariable($node->var)) {
            $this->registerScopeVariable($node->var);
        }
        return $this->pInfixOp('Expr_AssignOp_Div', $node->var, ' /= ', $node->expr);
    }

    public function pExpr_AssignOp_Concat(AssignOp\Concat $node) {
        if (self::isPlainVariable($node->var)) {
            $this->registerScopeVariable($node->var);
        }

        $expr = $this->phpStringCastIfNecessary($node->expr);

        return $this->pInfixOp('Expr_AssignOp_Concat', $node->var, ' += ', $expr);
    }

    public function pExpr_AssignOp_Mod(AssignOp\Mod $node) {
        if (self::isPlainVariable($node->var)) {
            $this->registerScopeVariable($node->var);
        }
        return $this->pInfixOp('Expr_AssignOp_Mod', $node->var, ' %= ', $node->expr);
    }

    public function pExpr_AssignOp_BitwiseAnd(AssignOp\BitwiseAnd $node) {
        if (self::isPlainVariable($node->var)) {
            $this->registerScopeVariable($node->var);
        }
        return $this->pInfixOp('Expr_AssignOp_BitwiseAnd', $node->var, ' &= ', $node->expr);
    }

    public function pExpr_AssignOp_BitwiseOr(AssignOp\BitwiseOr $node) {
        if (self::isPlainVariable($node->var)) {
            $this->registerScopeVariable($node->var);
        }
        return $this->pInfixOp('Expr_AssignOp_BitwiseOr', $node->var, ' |= ', $node->expr);
    }

    public function pExpr_AssignOp_BitwiseXor(AssignOp\BitwiseXor $node) {
        if (self::isPlainVariable($node->var)) {
            $this->registerScopeVariable($node->var);
        }
        return $this->pInfixOp('Expr_AssignOp_BitwiseXor', $node->var, ' ^= ', $node->expr);
    }

    public function pExpr_AssignOp_ShiftLeft(AssignOp\ShiftLeft $node) {
        if (self::isPlainVariable($node->var)) {
            $this->registerScopeVariable($node->var);
        }
        return $this->pInfixOp('Expr_AssignOp_ShiftLeft', $node->var, ' <<= ', $node->expr);
    }

    public function pExpr_AssignOp_ShiftRight(AssignOp\ShiftRight $node) {
        if (self::isPlainVariable($node->var)) {
            $this->registerScopeVariable($node->var);
        }
        return $this->pInfixOp('Expr_AssignOp_ShiftRight', $node->var, ' >>= ', $node->expr);
    }

    public function pExpr_AssignOp_Pow(AssignOp\Pow $node) {
        list($precedence, $associativity) = $this->precedenceMap['Expr_AssignOp_Pow'];

        return $this->pPrec($node->var, $precedence, $associativity, -1)
             . ' = '
             . 'Math.pow(PHP.toFloat('
                . $this->p($node->var)
                . '), PHP.toFloat('
                . $this->p($node->expr)
             . '))';
    }

    // Binary expressions

    public function pExpr_BinaryOp_Plus(BinaryOp\Plus $node) {
        return $this->pInfixOp('Expr_BinaryOp_Plus', $node->left, ' + ', $node->right);
    }

    public function pExpr_BinaryOp_Minus(BinaryOp\Minus $node) {
        return $this->pInfixOp('Expr_BinaryOp_Minus', $node->left, ' - ', $node->right);
    }

    public function pExpr_BinaryOp_Mul(BinaryOp\Mul $node) {
        return $this->pInfixOp('Expr_BinaryOp_Mul', $node->left, ' * ', $node->right);
    }

    public function pExpr_BinaryOp_Div(BinaryOp\Div $node) {
        return $this->pInfixOp('Expr_BinaryOp_Div', $node->left, ' / ', $node->right);
    }

    public function pExpr_BinaryOp_Concat(BinaryOp\Concat $node) {
        $left = $this->phpStringCastIfNecessary($node->left);
        $right = $this->phpStringCastIfNecessary($node->right);
        return $this->pInfixOp('Expr_BinaryOp_Concat', $left, ' + ', $right);
    }

    public function pExpr_BinaryOp_Mod(BinaryOp\Mod $node) {
        return $this->pInfixOp('Expr_BinaryOp_Mod', $node->left, ' % ', $node->right);
    }

    public function pExpr_BinaryOp_BooleanAnd(BinaryOp\BooleanAnd $node) {
        return $this->pInfixOp('Expr_BinaryOp_BooleanAnd', $node->left, ' && ', $node->right);
    }

    public function pExpr_BinaryOp_BooleanOr(BinaryOp\BooleanOr $node) {
        return $this->pInfixOp('Expr_BinaryOp_BooleanOr', $node->left, ' || ', $node->right);
    }

    public function pExpr_BinaryOp_BitwiseAnd(BinaryOp\BitwiseAnd $node) {
        return $this->pInfixOp('Expr_BinaryOp_BitwiseAnd', $node->left, ' & ', $node->right);
    }

    public function pExpr_BinaryOp_BitwiseOr(BinaryOp\BitwiseOr $node) {
        return $this->pInfixOp('Expr_BinaryOp_BitwiseOr', $node->left, ' | ', $node->right);
    }

    public function pExpr_BinaryOp_BitwiseXor(BinaryOp\BitwiseXor $node) {
        return $this->pInfixOp('Expr_BinaryOp_BitwiseXor', $node->left, ' ^ ', $node->right);
    }

    public function pExpr_BinaryOp_ShiftLeft(BinaryOp\ShiftLeft $node) {
        return $this->pInfixOp('Expr_BinaryOp_ShiftLeft', $node->left, ' << ', $node->right);
    }

    public function pExpr_BinaryOp_ShiftRight(BinaryOp\ShiftRight $node) {
        return $this->pInfixOp('Expr_BinaryOp_ShiftRight', $node->left, ' >> ', $node->right);
    }

    public function pExpr_BinaryOp_Pow(BinaryOp\Pow $node) {
        return 'Math.pow(PHP.toFloat('.$this->p($node->left).'), PHP.toFloat('.$this->p($node->right).'))';
//        return $this->pInfixOp('Expr_BinaryOp_Pow', $node->left, ' ** ', $node->right);
    }

    public function pExpr_BinaryOp_LogicalAnd(BinaryOp\LogicalAnd $node) {
        return '('.$this->pInfixOp('Expr_BinaryOp_LogicalAnd', $node->left, ') && (', $node->right).')';
    }

    public function pExpr_BinaryOp_LogicalOr(BinaryOp\LogicalOr $node) {
        return '('.$this->pInfixOp('Expr_BinaryOp_LogicalOr', $node->left, ') || (', $node->right).')';
    }

    public function pExpr_BinaryOp_LogicalXor(BinaryOp\LogicalXor $node) {
        return 'PHP.xor('.$this->p($node->left).', '.$this->p($node->right).')';
//        return '('.$this->pInfixOp('Expr_BinaryOp_LogicalXor', $node->left, ') xor (', $node->right).')';
    }

    public function pExpr_BinaryOp_Equal(BinaryOp\Equal $node) {
        // should do careful context analysis here
        return $this->pInfixOp('Expr_BinaryOp_Equal', $node->left, ' == ', $node->right);
    }

    public function pExpr_BinaryOp_NotEqual(BinaryOp\NotEqual $node) {
        // should do careful context analysis here
        return $this->pInfixOp('Expr_BinaryOp_NotEqual', $node->left, ' != ', $node->right);
    }

    public function pExpr_BinaryOp_Identical(BinaryOp\Identical $node) {
        // should do careful context analysis here
        return $this->pInfixOp('Expr_BinaryOp_Identical', $node->left, ' === ', $node->right);
    }

    public function pExpr_BinaryOp_NotIdentical(BinaryOp\NotIdentical $node) {
        // should do careful context analysis here
        return $this->pInfixOp('Expr_BinaryOp_NotIdentical', $node->left, ' !== ', $node->right);
    }

    public function pExpr_BinaryOp_Greater(BinaryOp\Greater $node) {
        return $this->pInfixOp('Expr_BinaryOp_Greater', $node->left, ' > ', $node->right);
    }

    public function pExpr_BinaryOp_GreaterOrEqual(BinaryOp\GreaterOrEqual $node) {
        return $this->pInfixOp('Expr_BinaryOp_GreaterOrEqual', $node->left, ' >= ', $node->right);
    }

    public function pExpr_BinaryOp_Smaller(BinaryOp\Smaller $node) {
        return $this->pInfixOp('Expr_BinaryOp_Smaller', $node->left, ' < ', $node->right);
    }

    public function pExpr_BinaryOp_SmallerOrEqual(BinaryOp\SmallerOrEqual $node) {
        return $this->pInfixOp('Expr_BinaryOp_SmallerOrEqual', $node->left, ' <= ', $node->right);
    }

    public function pExpr_Instanceof(Expr\Instanceof_ $node) {
        // interfaces not supported
        return $this->pInfixOp('Expr_Instanceof', $node->expr, ' instanceof ', $node->class);
    }

    // Unary expressions

    public function pExpr_BooleanNot(Expr\BooleanNot $node) {
        return $this->pPrefixOp('Expr_BooleanNot', '!', $node->expr);
    }

    public function pExpr_BitwiseNot(Expr\BitwiseNot $node) {
        return $this->pPrefixOp('Expr_BitwiseNot', '~', $node->expr);
    }

    public function pExpr_UnaryMinus(Expr\UnaryMinus $node) {
        return $this->pPrefixOp('Expr_UnaryMinus', '-', $node->expr);
    }

    public function pExpr_UnaryPlus(Expr\UnaryPlus $node) {
        return $this->pPrefixOp('Expr_UnaryPlus', '+', $node->expr);
    }

    public function pExpr_PreInc(Expr\PreInc $node) {
        if (self::isPlainVariable($node->var)) {
            $this->registerScopeVariable($node->var);
        }
        return $this->pPrefixOp('Expr_PreInc', '++', $node->var);
    }

    public function pExpr_PreDec(Expr\PreDec $node) {
        if (self::isPlainVariable($node->var)) {
            $this->registerScopeVariable($node->var);
        }
        return $this->pPrefixOp('Expr_PreDec', '--', $node->var);
    }

    public function pExpr_PostInc(Expr\PostInc $node) {
        if (self::isPlainVariable($node->var)) {
            $this->registerScopeVariable($node->var);
        }
        return $this->pPostfixOp('Expr_PostInc', $node->var, '++');
    }

    public function pExpr_PostDec(Expr\PostDec $node) {
        if (self::isPlainVariable($node->var)) {
            $this->registerScopeVariable($node->var);
        }
        return $this->pPostfixOp('Expr_PostDec', $node->var, '--');
    }

    public function pExpr_ErrorSuppress(Expr\ErrorSuppress $node) {
        // not supported
        return '';
//        return $this->pPrefixOp('Expr_ErrorSuppress', '@', $node->expr);
    }

    // Casts

    public function pExpr_Cast_Int(Cast\Int $node) {
        return 'PHP.toInt('.$this->p($node->expr).')';
//        return $this->pPrefixOp('Expr_Cast_Int', '(int) ', $node->expr);
    }

    public function pExpr_Cast_Double(Cast\Double $node) {
        return 'PHP.toFloat('.$this->p($node->expr).')';
//        return $this->pPrefixOp('Expr_Cast_Double', '(double) ', $node->expr);
    }

    public function pExpr_Cast_String(Cast\String $node) {
        return 'PHP.toString('.$this->p($node->expr).')';
//        return $this->pPrefixOp('Expr_Cast_String', '(string) ', $node->expr);
    }

    public function pExpr_Cast_Array(Cast\Array_ $node) {
        return 'PHP.toArray('.$this->p($node->expr).')';
//        return $this->pPrefixOp('Expr_Cast_Array', '(array) ', $node->expr);
    }

    public function pExpr_Cast_Object(Cast\Object $node) {
        return 'PHP.toObject('.$this->p($node->expr).')';
//        return $this->pPrefixOp('Expr_Cast_Object', '(object) ', $node->expr);
    }

    public function pExpr_Cast_Bool(Cast\Bool $node) {
        return 'PHP.toBool('.$this->p($node->expr).')';
//        return $this->pPrefixOp('Expr_Cast_Bool', '(bool) ', $node->expr);
    }

    public function pExpr_Cast_Unset(Cast\Unset_ $node) {
        return 'void('.$this->p($node->expr).')';
//        return $this->pPrefixOp('Expr_Cast_Unset', '(unset) ', $node->expr);
    }

    // Function calls and similar constructs

    public function pExpr_FuncCall(Expr\FuncCall $node) {
        if ($node->name instanceof Name) {
            $name = 'PHP.' . $node->name->toString('.');
        } else {
            $name = 'PHP[' . $this->p($node->name) . ']';
        }
        return $name . '(' . $this->pCommaSeparated($node->args) . ')';
    }

    public function pExpr_MethodCall(Expr\MethodCall $node) {
        return $this->pVarOrNewExpr($node->var) . $this->pArrowObjectMethod($node->name)
             . '(' . $this->pCommaSeparated($node->args) . ')';
    }

    public function pExpr_StaticCall(Expr\StaticCall $node) {
        $class = $this->p($node->class);

        $method = $node->name instanceof Expr
              ? '[' . $this->p($node->name) . ']'
              : '.'.$node->name;
//            ? ($node->name instanceof Expr\Variable || $node->name instanceof Expr\ArrayDimFetch
//                ? '[' . $this->p($node->name) . ']')
//                : '[' . $this->p($node->name) . ']')

        $args = $this->pCommaSeparated($node->args);

        return "$class$method($args)";
    }

    public function pExpr_Empty(Expr\Empty_ $node) {
        return 'PHP.empty(' . $this->p($node->expr) . ')';
    }

    public function pExpr_Isset(Expr\Isset_ $node) {
        return 'PHP.isset(' . $this->pCommaSeparated($node->vars) . ')';
    }

    public function pExpr_Print(Expr\Print_ $node) {
        return 'PHP.print(' . $this->p($node->expr) . ')';
    }

    public function pExpr_Eval(Expr\Eval_ $node) {
        // not supported
//        return 'eval(' . $this->p($node->expr) . ')';
    }

    public function pExpr_Include(Expr\Include_ $node) {
//        static $map = array(
//            Expr\Include_::TYPE_INCLUDE      => 'include',
//            Expr\Include_::TYPE_INCLUDE_ONCE => 'include_once',
//            Expr\Include_::TYPE_REQUIRE      => 'require',
//            Expr\Include_::TYPE_REQUIRE_ONCE => 'require_once',
//        );
//
//        return $map[$node->type] . ' ' . $this->p($node->expr);
    }

    public function pExpr_List(Expr\List_ $node) {
        $pList = array();
        foreach ($node->vars as $var) {
            if (null === $var) {
                $pList[] = '';
            } else {
                $pList[] = $this->p($var);
            }
        }

        // TODO
        // not currently supported

        return 'list(' . implode(', ', $pList) . ')';
    }

    // Other

    public function pExpr_Variable(Expr\Variable $node) {
        $name = $node->name;
        if ($name instanceof Expr) {
            // TODO
//            return '${' . $this->p($node->name) . '}';
        } else if ($name === 'this') {
            return 'this';
        } else {
            return '$' . $name;
        }
    }

    public function pExpr_Array(Expr\Array_ $node) {
        return 'PHP.array(' . $this->pCommaSeparated($node->items) . ')';
    }

    public function pExpr_ArrayItem(Expr\ArrayItem $node) {
        if (null !== $node->key) {
            return '[' . $this->p($node->key) . ', ' . $this->p($node->value) . ']';
        } else {
            return $this->p($node->value);
        }
//        return (null !== $node->key ? $this->p($node->key) . ' => ' : '')
//             . ($node->byRef ? '&' : '') . $this->p($node->value);
    }

    public function pExpr_ArrayDimFetch(Expr\ArrayDimFetch $node) {
        // TODO don't know what this is
        return $this->pVarOrNewExpr($node->var)
             . '[' . (null !== $node->dim ? $this->p($node->dim) : '') . ']';
    }

    public function pExpr_ConstFetch(Expr\ConstFetch $node) {
        return $this->p($node->name);
//        return 'PHP_CF1["'.$this->p($node->name).'"]';
    }

    public function pExpr_ClassConstFetch(Expr\ClassConstFetch $node) {
        return 'PHP>>>>>>>>>["'.$this->p($node->class).'"].' . $node->name;
//        return $this->p($node->class) . '::' . $node->name;
    }

    public function pExpr_PropertyFetch(Expr\PropertyFetch $node) {
        return $this->pVarOrNewExpr($node->var) . $this->pArrowObjectProperty($node->name);
//        return $this->pVarOrNewExpr($node->var) . '->' . $this->pObjectProperty($node->name);
    }

    public function pExpr_StaticPropertyFetch(Expr\StaticPropertyFetch $node) {
        return $this->p($node->class) . $this->pArrowObjectProperty($node->name);
    }

    public function pExpr_ShellExec(Expr\ShellExec $node) {
        // not supported
        return 'void(0)';
//        return '`' . $this->pEncapsList($node->parts, '`') . '`';
    }

    public function pExpr_Closure(Expr\Closure $node) {
        // TODO the use clause is going to go wrong
//        . (!empty($node->uses) ? ' use(' . $this->pCommaSeparated($node->uses) . ')': '')

        return ($node->static ? 'static ' : '')
             . 'function '
//             . ($node->byRef ? '&' : '')
             . '(' . $this->pCommaSeparated($node->params) . ')'
             . ' {' . $this->pContext($node->stmts, true, array(
                    'type' => 'closure',
                    '__FUNCTION__' => 'closure',
                )
            ) . "\n" . '}';
    }

    public function pExpr_ClosureUse(Expr\ClosureUse $node) {
        return '$' . $node->var;
//        return ($node->byRef ? '&' : '') . '$' . $node->var;
    }

    public function pExpr_New(Expr\New_ $node) {

        $classNode = $node->class;

        if ($classNode instanceof Name) {
//            if ($classNode instanceof Name\FullyQualified) {
//                $class = 'PHP['.json_encode("\\$classNode").']';
//            } else if ($classNode instanceof Name\Relative) {
////                $class = self::NAMESPACE_OBJECT . '.' . implode('.', $classNode->parts);
//                $class = (string)$classNode;
//                $class = self::NAMESPACE_OBJECT . '.' . strtr($class,'\\','.');
//            } else {
//                $class = (string)$classNode;
//                if ($class === self::CLASS_OBJECT || $class === self::context__CLASS_VAR__()) {
//                    // leave as is
////                    $class = '__CLASS['.json_encode("\\$classNode").']';
//                } else {
//                    $__USE_VARS__ = $this->context__USE_VARS__();
//                    if (is_array($__USE_VARS__) && in_array($class, array_keys($__USE_VARS__))) {
//                        // leave as is
////                        $class = '__USE['.json_encode("\\$classNode").']';
//                    } else {
//                        $class = self::NAMESPACE_OBJECT . '.' . strtr($class,'\\','.');
//                    }
//                }
//            }

            $class = $this->p($classNode);
        } else {
            $class = '(' . self::NAMESPACE_OBJECT . '.resolveClass(' . $this->p($classNode) . '))';
        }

//        if ($class !== self::CLASS_OBJECT) {
//            $class = $classNode->getType().'_PHP['.json_encode((string)$class).']';
//        }

        $args = $this->pCommaSeparated($node->args);

        return "new $class($args)";
    }

    public function pExpr_Clone(Expr\Clone_ $node) {
        return 'PHP.clone(' . $this->p($node->expr) . ')';
    }

    public function pExpr_Ternary(Expr\Ternary $node) {
        // a bit of cheating: we treat the ternary as a binary op where the ?...: part is the operator.
        // this is okay because the part between ? and : never needs parentheses.
        return $this->pInfixOp('Expr_Ternary',
            $node->cond, ' ?' . (null !== $node->if ? ' ' . $this->p($node->if) . ' ' : '') . ': ', $node->else
        );
    }

    public function pExpr_Exit(Expr\Exit_ $node) {
        return 'throw' . (null !== $node->expr ? '(' . $this->p($node->expr) . ')' : '"PHP exited"');
    }

    public function pExpr_Yield(Expr\Yield_ $node) {
        // not supported
//        if ($node->value === null) {
//            return 'yield';
//        } else {
//            // this is a bit ugly, but currently there is no way to detect whether the parentheses are necessary
//            return '(yield '
//                 . ($node->key !== null ? $this->p($node->key) . ' => ' : '')
//                 . $this->p($node->value)
//                 . ')';
//        }
    }

    // Declarations

    public function pStmt_Namespace(Stmt\Namespace_ $node) {
        $fqNS = null !== $node->name ? (string)$node->name : '\\\\';

//        $nsObjName = null !== $node->name ? strtr($this->p($node->name),'\\','_') : 'GLOBAL_NS';
        $nsObjName = self::NAMESPACE_OBJECT;

        $context = array(
            'type' => 'namespace',
            '__NAMESPACE__' => $fqNS,
        );

        return 'PHP.namespace("' . $fqNS . '",function(' . $nsObjName . ',__NAMESPACE__) {'
             . $this->pContext($node->stmts, true, $context)
             . "\n});";


//        if ($this->canUseSemicolonNamespaces) {
//            return 'namespace ' . $this->p($node->name) . ';' . "\n" . $this->pStmts($node->stmts, false);
//        } else {
//            return 'namespace' . (null !== $node->name ? ' ' . $this->p($node->name) : '')
//                 . ' {' . $this->pStmts($node->stmts) . "\n" . '}';
//        }
    }

    public function pStmt_Use(Stmt\Use_ $node) {
        $uses = $this->context__USE_VARS__();

        $pNodes = array();
        foreach ($node->uses as $use) {
            /** @var UseUse $use */
            $pNodes[] = $this->pStmt_UseUse($use);

            $uses["$use->alias"] = true;//"$node->name";
        }

        $this->contextSet('__USE_VARS__',$uses);

        return 'var ' . implode(",\n", $pNodes) . ';';

//        return 'use '
//             . ($node->type === Stmt\Use_::TYPE_FUNCTION ? 'function ' : '')
//             . ($node->type === Stmt\Use_::TYPE_CONSTANT ? 'const ' : '')
//             . $this->pCommaSeparated($node->uses) . ';';
    }

    public function pStmt_UseUse(Stmt\UseUse $node) {
        return $node->alias . ' = ' . $this->pName_FullyQualified($node->name);
//        return $this->p($node->name)
//             . ($node->name->getLast() !== $node->alias ? ' as ' . $node->alias : '');
    }

    public function pStmt_Interface(Stmt\Interface_ $node) {
        // not supported
//        return 'interface ' . $node->name
//             . (!empty($node->extends) ? ' extends ' . $this->pCommaSeparated($node->extends) : '')
//             . "\n" . '{' . $this->pStmts($node->stmts) . "\n" . '}';
    }

    public function pStmt_Class(Stmt\Class_ $node) {
        $nsObjName = self::NAMESPACE_OBJECT;

//        $name = self::CLASS_OBJECT;
        $name = (string) $node->name;

        $args = $classNameJS = json_encode((string)$node->name, JSON_UNESCAPED_SLASHES);

        if ($node->extends !== null) {
            $args .= ', ' . $this->p($node->extends);
        } else if (!empty($node->implements)) {
            $args .= ', null';
        }

        if (!empty($node->implements)) {
            $args .= ', [' . $this->pCommaSeparated($node->implements) . ']';
        }

        $fqNS = $this->context__NAMESPACE__();
        $fqClass = !$fqNS ? $node->name : "$fqNS\\$node->name";

        $context = array(
            'type' => 'class',
            '__NAMESPACE__' => $fqNS,
            '__CLASS__' => $fqClass,
            '__CLASS_VAR__' => $name,
        );

        return "var $name = $nsObjName.class($args, function(self,parent,__CLASS__) {\n"
             . $this->pContext($node->stmts, true, $context) . "\n});\n";
    }

    public function pStmt_Trait(Stmt\Trait_ $node) {
        // not supported
//        return 'trait ' . $node->name
//             . "\n" . '{' . $this->pStmts($node->stmts) . "\n" . '}';
    }

    public function pStmt_TraitUse(Stmt\TraitUse $node) {
        // not supported
//        return 'use ' . $this->pCommaSeparated($node->traits)
//             . (empty($node->adaptations)
//                ? ';'
//                : ' {' . $this->pStmts($node->adaptations) . "\n" . '}');
    }

    public function pStmt_TraitUseAdaptation_Precedence(Stmt\TraitUseAdaptation\Precedence $node) {
        // not supported
//        return $this->p($node->trait) . '::' . $node->method
//             . ' insteadof ' . $this->pCommaSeparated($node->insteadof) . ';';
    }

    public function pStmt_TraitUseAdaptation_Alias(Stmt\TraitUseAdaptation\Alias $node) {
        // not supported
//        return (null !== $node->trait ? $this->p($node->trait) . '::' : '')
//             . $node->method . ' as'
//             . (null !== $node->newModifier ? ' ' . $this->pModifiers($node->newModifier) : '')
//             . (null !== $node->newName     ? ' ' . $node->newName                        : '')
//             . ';';
    }

    public function pStmt_Property(Stmt\Property $node) {
        $output = '';

        if ($node->isPublic()) {
//            $destination = $node->isStatic() ? self::CLASS_OBJECT : self::CLASS_OBJECT.'.prototype';
            $destination = $this->context__CLASS_VAR__() . ($node->isStatic() ? '' : '.prototype');

            foreach ($node->props as $prop) {
                /** @var Stmt\PropertyProperty $prop */
                $output .= $destination . '.' . $this->pStmt_PropertyProperty($prop) . ";\n";
            }
        } else {
            // no private methods or code are output
        }

        return $output;

//        return $this->pModifiers($node->type) . $this->pCommaSeparated($node->props) . ';';
    }

    public function pStmt_PropertyProperty(Stmt\PropertyProperty $node) {
        return '$' . $node->name
             . (null !== $node->default ? ' = ' . $this->p($node->default) : ' = null');
    }

    public function pStmt_ClassMethod(Stmt\ClassMethod $node) {
        if ($node->isPublic()) {
//            $destination = $node->isStatic() ? self::CLASS_OBJECT : self::CLASS_OBJECT.'.prototype';
            $destination = $this->context__CLASS_VAR__() . ($node->isStatic() ? '' : '.prototype');

            $fqNS = $this->context__NAMESPACE__();
            $fqClass = $this->context__CLASS__();

            $context = array(
                'type' => 'method',
                '__NAMESPACE__' => $fqNS,
                '__CLASS__' => $fqClass,
                '__FUNCTION__' => $node->name,
                '__METHOD__' => "$fqClass::$node->name",
            );


            $output = $destination . '.' . $node->name
                . ' = function(' . $this->pCommaSeparated($node->params) . ')'
                . (null !== $node->stmts
                    ? "\n" . '{' . $this->pContext($node->stmts, true, $context) . "\n" . '}'
                    : '{}')
                . ';';
        } else {
            // no private methods or code are output
            $output = '';
        }

        return $output;

//        return $this->pModifiers($node->type)
//             . 'function ' . ($node->byRef ? '&' : '') . $node->name
//             . '(' . $this->pCommaSeparated($node->params) . ')'
//             . (null !== $node->stmts
//                ? "\n" . '{' . $this->pStmts($node->stmts) . "\n" . '}'
//                : ';');
    }

    public function pStmt_ClassConst(Stmt\ClassConst $node) {
        $output = '';
//        $destination = self::CLASS_OBJECT;
        $destination = $this->context__CLASS_VAR__();
        foreach ($node->consts as $const) {
            /** @var Node\Const_ $const */
            $output .= $destination . '.' . $const->name . ' = ' . $this->p($const->value) . ";\n";
        }
        return $output;

//        return 'const ' . $this->pCommaSeparated($node->consts) . ';';
    }

    public function pStmt_Function(Stmt\Function_ $node) {
        $context = array(
            'type' => 'method',
            '__FUNCTION__' => $node->name,
        );

        return 'function ' //. ($node->byRef ? '&' : '')
             . $node->name
             . '(' . $this->pCommaSeparated($node->params) . ')'
             . "\n" . '{' . $this->pContext($node->stmts, true, $context) . "\n" . '}';
    }

    public function pStmt_Const(Stmt\Const_ $node) {
        return 'const ' . $this->pCommaSeparated($node->consts) . ';';
    }

    public function pStmt_Declare(Stmt\Declare_ $node) {
        // not supported
//        return 'declare (' . $this->pCommaSeparated($node->declares) . ') {'
//             . $this->pStmts($node->stmts) . "\n" . '}';
    }

    public function pStmt_DeclareDeclare(Stmt\DeclareDeclare $node) {
        return $node->key . ' = ' . $this->p($node->value);
    }

    // Control flow

    public function pStmt_If(Stmt\If_ $node) {
        return 'if (' . $this->p($node->cond) . ') {'
             . $this->pStmts($node->stmts) . "\n" . '}'
             . $this->pImplode($node->elseifs)
             . (null !== $node->else ? $this->p($node->else) : '');
    }

    public function pStmt_ElseIf(Stmt\ElseIf_ $node) {
        return ' else if (' . $this->p($node->cond) . ') {'
             . $this->pStmts($node->stmts) . "\n" . '}';
    }

    public function pStmt_Else(Stmt\Else_ $node) {
        return ' else {' . $this->pStmts($node->stmts) . "\n" . '}';
    }

    public function pStmt_For(Stmt\For_ $node) {
        return 'for ('
             . $this->pCommaSeparated($node->init) . ';' . (!empty($node->cond) ? ' ' : '')
             . $this->pCommaSeparated($node->cond) . ';' . (!empty($node->loop) ? ' ' : '')
             . $this->pCommaSeparated($node->loop)
             . ') {' . $this->pStmts($node->stmts) . "\n" . '}';
    }

    public function pStmt_Foreach(Stmt\Foreach_ $node) {
        $usesBreakContinueOrReturn = true;

        if ($usesBreakContinueOrReturn) {
            // if contained statements utilise continue, break or return,
            // use a for loop with an array iterator
            $it = $this->codeGen_nextIterator();

            $assign = "$it = PHP.foreach(" . $this->p($node->expr) . "), "
                    . (null !== $node->keyVar
                        ? $this->p($node->keyVar) . " = $it.key(), "
                        : '')
                    . $this->p($node->valueVar) . " = $it.current()";

            $update = $this->p($node->valueVar) . " = $it.next()"
                    . (null !== $node->keyVar
                        ? ', ' . $this->p($node->keyVar) . " = $it.key()"
                        : '');

            return "for (var $assign; $it.hasNext(); $update)\n{"
                 . $this->pStmts($node->stmts)
                 . "\n}";
        } else {
            // convert non-breaking foreach loops to closures
            return 'PHP.foreach(' . $this->p($node->expr) . ', function('
            . (null !== $node->keyVar ? $this->p($node->keyVar) . ', ' : '__unused__, ')
            . $this->p($node->valueVar) . ') {'
            . $this->pContext($node->stmts, true, ['type' => 'foreach_closure'])
            . "\n});";
        }

//        return 'foreach (' . $this->p($node->expr) . ' as '
//             . (null !== $node->keyVar ? $this->p($node->keyVar) . ' => ' : '')
//             . ($node->byRef ? '&' : '') . $this->p($node->valueVar) . ') {'
//             . $this->pStmts($node->stmts) . "\n" . '}';
    }

    public function pStmt_While(Stmt\While_ $node) {
        return 'while (' . $this->p($node->cond) . ') {'
             . $this->pStmts($node->stmts) . "\n" . '}';
    }

    public function pStmt_Do(Stmt\Do_ $node) {
        return 'do {' . $this->pStmts($node->stmts) . "\n"
             . '} while (' . $this->p($node->cond) . ');';
    }

    public function pStmt_Switch(Stmt\Switch_ $node) {
        return 'switch (' . $this->p($node->cond) . ') {'
             . $this->pStmts($node->cases) . "\n" . '}';
    }

    public function pStmt_Case(Stmt\Case_ $node) {
        return (null !== $node->cond ? 'case ' . $this->p($node->cond) : 'default') . ':'
             . $this->pStmts($node->stmts);
    }

    public function pStmt_TryCatch(Stmt\TryCatch $node) {
        // TODO try / catch will need some work
        return 'try {' . $this->pStmts($node->stmts) . "\n" . '}'
             . $this->pImplode($node->catches)
             . ($node->finallyStmts !== null
                ? ' finally {' . $this->pStmts($node->finallyStmts) . "\n" . '}'
                : '');
    }

    public function pStmt_Catch(Stmt\Catch_ $node) {
        return ' catch (' . ' $' . $node->var . ') {'
             . $this->pStmts($node->stmts) . "\n" . '}';
//        return ' catch (' . $this->p($node->type) . ' $' . $node->var . ') {'
//             . $this->pStmts($node->stmts) . "\n" . '}';
    }

    public function pStmt_Break(Stmt\Break_ $node) {
        $levels = $this->parseBreakOrContinueDepth($node);
        return 'break' . ($levels > 1 ? " $levels" : '') . ';';

//        return 'break' . ($node->num !== null ? ' ' . $this->p($node->num) : '') . ';';
    }

    public function pStmt_Continue(Stmt\Continue_ $node) {
        $levels = $this->parseBreakOrContinueDepth($node);
        return 'continue' . ($levels > 1 ? " $levels" : '') . ';';

//        return 'continue' . ($node->num !== null ? ' ' . $this->p($node->num) : '') . ';';
    }

    public function pStmt_Return(Stmt\Return_ $node) {
        return 'return' . (null !== $node->expr ? ' ' . $this->p($node->expr) : '') . ';';
    }

    public function pStmt_Throw(Stmt\Throw_ $node) {
        return 'throw ' . $this->p($node->expr) . ';';
    }

    public function pStmt_Label(Stmt\Label $node) {
        // not supported
//        return $node->name . ':';
    }

    public function pStmt_Goto(Stmt\Goto_ $node) {
        // not supported
//        return 'goto ' . $node->name . ';';
    }

    // Other

    public function pStmt_Echo(Stmt\Echo_ $node) {
        return 'PHP.echo(' . $this->pCommaSeparated($node->exprs) . ');';
    }

    public function pStmt_Static(Stmt\Static_ $node) {
        // not supported
//        return 'static ' . $this->pCommaSeparated($node->vars) . ';';
    }

    public function pStmt_Global(Stmt\Global_ $node) {
        // not supported
//        return 'global ' . $this->pCommaSeparated($node->vars) . ';';
    }

    public function pStmt_StaticVar(Stmt\StaticVar $node) {
        // not supported
//        return '$' . $node->name
//             . (null !== $node->default ? ' = ' . $this->p($node->default) : '');
    }

    public function pStmt_Unset(Stmt\Unset_ $node) {
        return 'delete ' . $this->pCommaSeparated($node->vars) . ';';
//        return 'unset(' . $this->pCommaSeparated($node->vars) . ');';
    }

    public function pStmt_InlineHTML(Stmt\InlineHTML $node) {
        $lines = explode("\n", (string)$node->value);

        $lines = array_map(function($line) {
            return json_encode($line,  JSON_UNESCAPED_SLASHES);
        }, $lines);

        return "PHP.inlinePrint(\n$this->noIndentToken" . implode("\n$this->noIndentToken+", $lines) . "\n);";

    }

    public function pStmt_HaltCompiler(Stmt\HaltCompiler $node) {
        return '__halt_compiler();' . $node->remaining;
    }

    // Helpers
    public function pArrowObjectMethod($node) {
        if ($node instanceof Expr) {
            return '[' . $this->p($node) . ']';
        } else {
            return ".$node";
        }
    }

    public function pArrowObjectProperty($node) {
        if ($node instanceof Expr) {
            return '["$"+' . $this->p($node) . ']';
        } else {
            return '.$' . $node;
        }
    }

//    public function pModifiers($modifiers) {
//        return ($modifiers & Stmt\Class_::MODIFIER_PUBLIC    ? 'public '    : '')
//             . ($modifiers & Stmt\Class_::MODIFIER_PROTECTED ? 'protected ' : '')
//             . ($modifiers & Stmt\Class_::MODIFIER_PRIVATE   ? 'private '   : '')
//             . ($modifiers & Stmt\Class_::MODIFIER_STATIC    ? 'static '    : '')
//             . ($modifiers & Stmt\Class_::MODIFIER_ABSTRACT  ? 'abstract '  : '')
//             . ($modifiers & Stmt\Class_::MODIFIER_FINAL     ? 'final '     : '');
//    }

    public function pEncapsList(array $encapsList, $quote) {
        $return = [];
        foreach ($encapsList as $element) {
            if (is_string($element)) {
                $return[] = json_encode($element,  JSON_UNESCAPED_SLASHES);
            } else {
                $return[] = $this->p($this->phpStringCastIfNecessary($element));
            }
        }

        return implode('+',$return);
    }

    public function pVarOrNewExpr(Node $node) {
        if ($node instanceof Expr\New_) {
            return '(' . $this->p($node) . ')';
        } else {
            return $this->p($node);
        }
    }

    private function parseBreakOrContinueDepth(Stmt $node) {
        if ($node->num === null) {
            return 1;
        } else if ($node->num instanceof LNumber) {
            $levels = $node->num->value;
            if ($levels < 1) {
                trigger_error(strtolower($node->getType())." 0 is not allowed: ", E_USER_ERROR);
            }
            return $levels;
        } else {
            trigger_error(strtolower($node->getType())." ????? is not allowed: ", E_USER_ERROR);
        }
    }

    private $codeGen_varList = [];
    private $_codeGen_lastIterator = -1;
    private function codeGen_nextIterator() {
        $value = ++$this->_codeGen_lastIterator;
        return 'it'.strtoupper(base_convert($value, 10, 36));
    }

    public static function isPlainVariable(Expr $node) {
        return $node instanceof Expr\Variable && !($node->name instanceof Expr);
    }

    public function registerScopeVariable(Expr $node) {
        $this->codeGen_varList['$'.$node->name] = true;
    }

    public function phpStringCastIfNecessary(Expr $expr) {
        if ($expr instanceof Scalar\String) {
            return $expr;
        } else {
            // wrap in PHP.toString
            $expr = new Cast\String($expr);
            return $expr;
        }
    }
}
