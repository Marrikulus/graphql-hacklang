<?hh
namespace GraphQL\Language\AST;

class NullValueNode extends Node implements ValueNode<?string>
{
    public string $kind = NodeKind::NULL;

    public function __construct(
        ?Location $loc = null
    ) {
		parent::__construct($loc);
    }

    public function getValue():?string
	{
		return null;
	}
}
