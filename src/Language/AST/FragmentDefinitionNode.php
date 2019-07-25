<?hh //strict
namespace GraphQL\Language\AST;

class FragmentDefinitionNode extends Node implements DefinitionNode, HasSelectionSet, HasDirectives
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

    public function getSelectionSet():SelectionSetNode
    {
    	return $this->selectionSet;
    }

    public function getDirectives():array<DirectiveNode>
    {
        return $this->directives;
    }
}
