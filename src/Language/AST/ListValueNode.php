<?hh

namespace GraphQL\Language\AST;

class ListValueNode extends Node implements ValueNode
{
    public string $kind = NodeKind::LST;

    public function __construct(
        public NodeList $values,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
