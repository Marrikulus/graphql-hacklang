<?hh
namespace GraphQL\Language\AST;

class VariableNode extends Node
{
    public string $kind = NodeKind::VARIABLE;

    public function __construct(
        public NameNode $name,
        ?Location $loc = null)
    {
        parent::__construct($loc);
    }
}
