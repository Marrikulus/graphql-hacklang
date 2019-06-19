<?hh
namespace GraphQL\Language\AST;

class StringValueNode extends Node implements ValueNode<?string>
{
    public function __construct(
		public ?string $value,
		?Location $loc = null
	) {
		parent::__construct($loc, NodeKind::STRING);
	}

	public function getValue():?string
	{
		return $this->value;
	}
}
