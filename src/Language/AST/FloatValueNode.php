<?hh //strict
namespace GraphQL\Language\AST;

class FloatValueNode extends Node implements ValueNode<?string>
{
    public function __construct(
		public ?string $value,
		?Location $loc = null
	) {
		parent::__construct($loc, NodeKind::FLOAT);
	}

	public function getValue():?string
	{
		return $this->value;
	}
}
