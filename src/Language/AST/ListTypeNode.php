<?hh
namespace GraphQL\Language\AST;

class ListTypeNode extends Node implements TypeNode
{
    public string $kind = NodeKind::LIST_TYPE;

    public function __construct(
        public Node $type,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
