<?hh //partial

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Executor\Values;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\Visitor;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Validator\ValidationContext;

class QueryComplexity extends AbstractQuerySecurity
{
    private int $maxQueryComplexity = 0;

    private $rawVariableValues = [];

    private $variableDefs;

    private $fieldNodeAndDefs;

    /**
     * @var ValidationContext
     */
    private $context;

    public function __construct($maxQueryComplexity)
    {
        $this->setMaxQueryComplexity($maxQueryComplexity);
    }

    public static function maxQueryComplexityErrorMessage($max, $count)
    {
        return \sprintf('Max query complexity should be %d but got %d.', $max, $count);
    }

    /**
     * Set max query complexity. If equal to 0 no check is done. Must be greater or equal to 0.
     *
     * @param $maxQueryComplexity
     */
    public function setMaxQueryComplexity($maxQueryComplexity)
    {
        $this->checkIfGreaterOrEqualToZero('maxQueryComplexity', $maxQueryComplexity);

        $this->maxQueryComplexity = (int) $maxQueryComplexity;
    }

    public function getMaxQueryComplexity()
    {
        return $this->maxQueryComplexity;
    }

    public function setRawVariableValues(?array $rawVariableValues = null)
    {
        $this->rawVariableValues = $rawVariableValues ?? [];
    }

    public function getRawVariableValues()
    {
        return $this->rawVariableValues;
    }

    public function getVisitor(ValidationContext $context)
    {
        $this->context = $context;

        $this->variableDefs = new \ArrayObject();
        $this->fieldNodeAndDefs = new \ArrayObject();
        $complexity = 0;

        return $this->invokeIfNeeded(
            $context,
            [
                NodeKind::SELECTION_SET => function (SelectionSetNode $selectionSet) use ($context) {
                    $this->fieldNodeAndDefs = $this->collectFieldASTsAndDefs(
                        $context,
                        $context->getParentType(),
                        $selectionSet,
                        null,
                        $this->fieldNodeAndDefs
                    );
                },
                NodeKind::VARIABLE_DEFINITION => function ($def) {
                    $this->variableDefs[] = $def;
                    return Visitor::skipNode();
                },
                NodeKind::OPERATION_DEFINITION => [
                    'leave' => function (OperationDefinitionNode $operationDefinition) use ($context, &$complexity) {
                        $errors = $context->getErrors();

                        if (empty($errors))
                        {
                            $complexity = $this->fieldComplexity($operationDefinition->getSelectionSet(), $complexity);

                            if ($complexity > $this->getMaxQueryComplexity()) {
                                $context->reportError(
                                    new Error(QueryComplexity::maxQueryComplexityErrorMessage($this->getMaxQueryComplexity(), $complexity))
                                );
                            }
                        }
                    },
                ],
            ]
        );
    }

    private function fieldComplexity(SelectionSetNode $selectionSet, int $complexity = 0):int
    {
        foreach ($selectionSet->selections as $childNode)
        {
            $complexity = $this->nodeComplexity($childNode, $complexity);
        }

        return $complexity;
    }

    private function nodeComplexity(Node $node, @int $complexity = 0)
    {
        switch ($node->kind)
        {
            case NodeKind::FIELD:
            if ($node instanceof FieldNode)
            {
                /* @var FieldNode $node */
                // default values
                $args = [];
                $complexityFn = FieldDefinition::DEFAULT_COMPLEXITY_FN;

                // calculate children complexity if needed
                $childrenComplexity = 0;

                // node has children?
                $selectionSet = $node->selectionSet;
                if ($selectionSet !== null)
                {
                    $childrenComplexity = $this->fieldComplexity($selectionSet);
                }

                $astFieldInfo = $this->astFieldInfo($node);
                $fieldDef = $astFieldInfo[1];

                if ($fieldDef instanceof FieldDefinition)
                {
                    if ($this->directiveExcludesField($node))
                    {
                        break;
                    }

                    $args = $this->buildFieldArguments($node);
                    //get complexity fn using fieldDef complexity
                    if (\method_exists($fieldDef, 'getComplexityFn')) {
                        $complexityFn = $fieldDef->getComplexityFn();
                    }
                }

                $complexity += \call_user_func_array($complexityFn, [$childrenComplexity, $args]);
            }
                break;

            case NodeKind::INLINE_FRAGMENT:
            if ($node instanceof InlineFragmentNode)
            {
                /* @var InlineFragmentNode $node */
                // node has children?
                $selectionSet = $node->selectionSet;
                if ($selectionSet !== null)
                {
                    $complexity = $this->fieldComplexity($selectionSet, $complexity);
                }
            }break;

            case NodeKind::FRAGMENT_SPREAD:
            if ($node instanceof FragmentSpreadNode)
            {
                /* @var FragmentSpreadNode $node */
                $fragment = $this->getFragment($node);

                if (null !== $fragment)
                {
                    $complexity = $this->fieldComplexity($fragment->getSelectionSet(), $complexity);
                }
            }break;
        }

        return $complexity;
    }

    private function astFieldInfo(FieldNode $field)
    {
        $fieldName = $this->getFieldName($field);
        $astFieldInfo = [null, null];
        if (isset($this->fieldNodeAndDefs[$fieldName]))
        {
            foreach ($this->fieldNodeAndDefs[$fieldName] as $astAndDef)
            {
                if ($astAndDef[0] == $field) {
                    $astFieldInfo = $astAndDef;
                    break;
                }
            }
        }

        return $astFieldInfo;
    }

    private function buildFieldArguments(FieldNode $node)
    {
        $rawVariableValues = $this->getRawVariableValues();
        $astFieldInfo = $this->astFieldInfo($node);
        $fieldDef = $astFieldInfo[1];

        $args = [];

        if ($fieldDef instanceof FieldDefinition) {
            $variableValues = Values::getVariableValues(
                $this->context->getSchema(),
                $this->variableDefs,
                $rawVariableValues
            );
            $args = Values::getArgumentValues($fieldDef, $node, $variableValues);
        }

        return $args;
    }

    private function directiveExcludesField(FieldNode $node)
    {
        foreach ($node->directives as $directiveNode)
        {
            if ($directiveNode->name->value === 'deprecated') {
                return false;
            }

            $variableValues = Values::getVariableValues(
                $this->context->getSchema(),
                $this->variableDefs,
                $this->getRawVariableValues()
            );

            if ($directiveNode->name->value === 'include')
            {
                $directive = Directive::includeDirective();
                $directiveArgs = Values::getArgumentValues($directive, $directiveNode, $variableValues);

                return !$directiveArgs['if'];
            }
            else
            {
                $directive = Directive::skipDirective();
                $directiveArgs = Values::getArgumentValues($directive, $directiveNode, $variableValues);

                return $directiveArgs['if'];
            }
        }
    }

    protected function isEnabled():bool
    {
        return $this->getMaxQueryComplexity() !== static::DISABLED;
    }
}
