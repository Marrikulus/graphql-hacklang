<?hh
namespace GraphQL\Language\AST;

class IntValueNode extends Node implements ValueNode
{
    public string $kind = NodeKind::INT;

    public function __construct(
		public ?string $value,
		?Location $loc
	) {
		parent::__construct($loc);
	}
}
