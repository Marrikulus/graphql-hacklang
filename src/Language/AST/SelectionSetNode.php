<?hh
namespace GraphQL\Language\AST;

class SelectionSetNode extends Node
{
    public function __construct(
        public array<Node> $selections,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::SELECTION_SET);
    }
}
