<?hh
namespace GraphQL\Language\AST;

class StringValueNode extends Node implements ValueNode
{
    public string $kind = NodeKind::STRING;

    public function __construct(
		public ?string $value,
		?Location $loc
	) {
		parent::__construct($loc);
	}
}
