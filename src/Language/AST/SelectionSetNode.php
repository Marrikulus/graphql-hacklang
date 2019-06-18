<?hh
namespace GraphQL\Language\AST;

class SelectionSetNode extends Node
{
    public string $kind = NodeKind::SELECTION_SET;

    public function __construct(
        public array<Node> $selections,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
