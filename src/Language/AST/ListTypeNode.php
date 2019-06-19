<?hh
namespace GraphQL\Language\AST;

class ListTypeNode extends Node implements TypeNode
{
    public function __construct(
        public Node $type,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::LIST_TYPE);
    }
}
