<?hh
namespace GraphQL\Language\AST;

class NullValueNode extends Node implements ValueNode<?string>
{
    public function __construct(
        ?Location $loc = null
    ) {
		parent::__construct($loc, NodeKind::NULL);
    }

    public function getValue():?string
	{
		return null;
	}
}
