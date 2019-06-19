<?hh //strict
namespace GraphQL\Language\AST;

class EnumValueNode extends Node implements ValueNode<?string>
{
    public string $kind = NodeKind::ENUM;

    public function __construct(
        public ?string $value,
        ?Location $loc = null)
    {
        parent::__construct($loc);
    }

    public function getValue():?string
	{
		return $this->value;
	}
}
