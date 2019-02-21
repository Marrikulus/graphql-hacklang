<?hh
namespace GraphQL\Language\AST;

class NonNullTypeNode extends Node implements TypeNode
{
    public string $kind = NodeKind::NON_NULL_TYPE;

    public function __construct(
        public Node $type,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
