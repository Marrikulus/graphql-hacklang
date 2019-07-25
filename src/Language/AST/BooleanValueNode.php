<?hh //strict
namespace GraphQL\Language\AST;

class BooleanValueNode extends Node implements ValueNode<bool>
{
	public function __construct(
		public bool $value,
		?Location $loc = null
	) {
		parent::__construct($loc, NodeKind::BOOLEAN);
	}

	public function getValue():bool
	{
		return $this->value;
	}
}
