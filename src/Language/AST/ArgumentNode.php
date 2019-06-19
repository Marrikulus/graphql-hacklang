<?hh //strict
namespace GraphQL\Language\AST;

class ArgumentNode extends Node
{
    public string $kind = NodeKind::ARGUMENT;

    public function __construct(
        public NameNode $name,
        public Node $value,
        ?Location $loc = null)
	{
		parent::__construct($loc);
	}
}
