<?hh //partial
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Validator\ValidationContext;

class QueryDepth extends AbstractQuerySecurity
{
    /**
     * @var int
     */
    private int $maxQueryDepth = 0;

    public function __construct(int $maxQueryDepth)
    {
        $this->setMaxQueryDepth($maxQueryDepth);
    }

    /**
     * Set max query depth. If equal to 0 no check is done. Must be greater or equal to 0.
     *
     * @param $maxQueryDepth
     */
    public function setMaxQueryDepth(int $maxQueryDepth)
    {
        $this->checkIfGreaterOrEqualToZero('maxQueryDepth', $maxQueryDepth);

        $this->maxQueryDepth = (int) $maxQueryDepth;
    }

    public function getMaxQueryDepth()
    {
        return $this->maxQueryDepth;
    }

    public static function maxQueryDepthErrorMessage($max, $count)
    {
        return \sprintf('Max query depth should be %d but got %d.', $max, $count);
    }

    public function getVisitor(ValidationContext $context)
    {
        return $this->invokeIfNeeded(
            $context,
            [
                NodeKind::OPERATION_DEFINITION => [
                    'leave' => function (OperationDefinitionNode $operationDefinition) use ($context) {
                        $maxDepth = $this->fieldDepth($operationDefinition->getSelectionSet());

                        if ($maxDepth > $this->getMaxQueryDepth()) {
                            $context->reportError(
                                new Error(QueryDepth::maxQueryDepthErrorMessage($this->getMaxQueryDepth(), $maxDepth))
                            );
                        }
                    },
                ],
            ]
        );
    }

    protected function isEnabled()
    {
        return $this->getMaxQueryDepth() !== static::DISABLED;
    }

    private function fieldDepth(SelectionSetNode $selectionSet, @int $depth = 0, @int $maxDepth = 0)
    {
        foreach ($selectionSet->selections as $childNode)
        {
            $maxDepth = $this->nodeDepth($childNode, $depth, $maxDepth);
        }

        return $maxDepth;
    }

    private function nodeDepth(Node $node, @int $depth = 0, @int $maxDepth = 0)
    {
        switch ($node->kind) {
            case NodeKind::FIELD:
            if ($node instanceof FieldNode)
            {
                /* @var FieldNode $node */
                // node has children?
                $selectionSet = $node->selectionSet;
                if (null !== $selectionSet)
                {
                    // update maxDepth if needed
                    if ($depth > $maxDepth)
                    {
                        $maxDepth = $depth;
                    }
                    $maxDepth = $this->fieldDepth($selectionSet, $depth + 1, $maxDepth);
                }
            }break;

            case NodeKind::INLINE_FRAGMENT:
            if ($node instanceof InlineFragmentNode)
            {
                /* @var InlineFragmentNode $node */
                // node has children?
                $maxDepth = $this->fieldDepth($node->selectionSet, $depth, $maxDepth);
            }break;

            case NodeKind::FRAGMENT_SPREAD:
            if ($node instanceof FragmentSpreadNode)
            {
                /* @var FragmentSpreadNode $node */
                $fragment = $this->getFragment($node);

                if (null !== $fragment)
                {
                    $maxDepth = $this->fieldDepth($fragment->getSelectionSet(), $depth, $maxDepth);
                }
            }break;
        }

        return $maxDepth;
    }
}
