<?hh //strict
namespace GraphQL\Language\AST;

class FragmentDefinitionNode extends Node implements DefinitionNode, HasSelectionSet
{
    public string $kind = NodeKind::FRAGMENT_DEFINITION;

    public function __construct(
        public NameNode $name,
        public NamedTypeNode $typeCondition,
        public NodeList $directives,
        public SelectionSetNode $selectionSet,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
