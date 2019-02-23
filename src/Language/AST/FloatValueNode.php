<?hh //strict
namespace GraphQL\Language\AST;

class FloatValueNode extends Node implements ValueNode<?string>
{
    public string $kind = NodeKind::FLOAT;

    public function __construct(
		public ?string $value,
		?Location $loc
	) {
		parent::__construct($loc);
	}

	public function getValue():?string
	{
		return $this->value;
	}
}
