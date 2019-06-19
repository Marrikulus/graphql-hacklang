<?hh
namespace GraphQL\Language\AST;

class NonNullTypeNode extends Node implements TypeNode
{
    public function __construct(
        public Node $type,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::NON_NULL_TYPE);
    }
}
