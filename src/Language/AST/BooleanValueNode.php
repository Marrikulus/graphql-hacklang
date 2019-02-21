<?hh //strict
namespace GraphQL\Language\AST;


class BooleanValueNode extends Node implements ValueNode
{
	public string $kind = NodeKind::BOOLEAN;

	public function __construct(
		public bool $value,
		?Location $loc
	) {
		parent::__construct($loc);
	}
}
