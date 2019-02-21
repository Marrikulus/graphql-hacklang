<?hh //strict
namespace GraphQL\Language\AST;

class EnumValueNode extends Node implements ValueNode
{
    public string $kind = NodeKind::ENUM;

    public function __construct(
        public ?string $value,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
