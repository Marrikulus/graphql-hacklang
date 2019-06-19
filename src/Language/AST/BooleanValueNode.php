<?hh //strict
namespace GraphQL\Language\AST;


class BooleanValueNode extends Node implements ValueNode<bool>
{
	public string $kind = NodeKind::BOOLEAN;

	public function __construct(
		public bool $value,
		?Location $loc = null
	) {
		parent::__construct($loc);
	}

	public function getValue():bool
	{
		return $this->value;
	}
}
