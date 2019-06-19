<?hh //strict
namespace GraphQL\Language\AST;

class EnumValueNode extends Node implements ValueNode<?string>
{
    public function __construct(
        public ?string $value,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::ENUM);
    }

    public function getValue():?string
	{
		return $this->value;
	}
}
