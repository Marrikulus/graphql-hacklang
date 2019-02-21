<?hh //strict
namespace GraphQL\Language\AST;

class FloatValueNode extends Node implements ValueNode
{
    public string $kind = NodeKind::FLOAT;

    public function __construct(
		public ?string $value,
		?Location $loc
	) {
		parent::__construct($loc);
	}
}
