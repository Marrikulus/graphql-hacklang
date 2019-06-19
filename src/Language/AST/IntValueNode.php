<?hh
namespace GraphQL\Language\AST;

class IntValueNode extends Node implements ValueNode<?string>
{
    public string $kind = NodeKind::INT;

    public function __construct(
		public ?string $value,
		?Location $loc = null
	) {
		parent::__construct($loc);
	}

	public function getValue():?string
	{
		return $this->value;
	}
}
