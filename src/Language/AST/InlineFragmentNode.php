<?hh //strict
namespace GraphQL\Language\AST;

class InlineFragmentNode extends Node implements SelectionNode
{
    public string $kind = NodeKind::INLINE_FRAGMENT;

    public function __construct(
        public ?NamedTypeNode $typeCondition,
        public array<DirectiveNode> $directives,
        public SelectionSetNode $selectionSet,
        ?Location $loc = null)
    {
        parent::__construct($loc);
    }
}
