<?hh //strict
namespace GraphQL\Language\AST;

class FragmentDefinitionNode extends Node implements DefinitionNode, HasSelectionSet
{
    public function __construct(
        public NameNode $name,
        public NamedTypeNode $typeCondition,
        public array<DirectiveNode> $directives,
        public SelectionSetNode $selectionSet,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::FRAGMENT_DEFINITION);
    }
}
