<?hh //strict
namespace GraphQL\Language\AST;

class ObjectValueNode extends Node implements ValueNode<NodeList>
{
    public string $kind = NodeKind::OBJECT;

    public function __construct(
        public NodeList $fields,
        ?Location $loc)
    {
        parent::__construct($loc);
    }

    public function getValue():NodeList
	{
		return $this->fields;
	}
}
