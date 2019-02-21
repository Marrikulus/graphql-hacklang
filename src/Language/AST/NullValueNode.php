<?hh
namespace GraphQL\Language\AST;

class NullValueNode extends Node implements ValueNode
{
    public string $kind = NodeKind::NULL;

    public function __construct(
        ?Location $loc
    ) {
		parent::__construct($loc);
    }
}
