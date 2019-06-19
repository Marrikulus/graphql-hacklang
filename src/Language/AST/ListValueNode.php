<?hh

namespace GraphQL\Language\AST;

class ListValueNode extends Node implements ValueNode<array<Node>>
{
    public function __construct(
        public array<Node> $values,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::LST);
    }

    public function getValue():array<Node>
	{
		return $this->values;
	}
}
