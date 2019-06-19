<?hh //strict
namespace GraphQL\Language\AST;

class FragmentSpreadNode extends Node implements SelectionNode
{
    public function __construct(
        public NameNode $name,
        public array<DirectiveNode> $directives,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::FRAGMENT_SPREAD);
    }
}
