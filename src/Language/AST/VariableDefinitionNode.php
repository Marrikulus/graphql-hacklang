<?hh
namespace GraphQL\Language\AST;

class VariableDefinitionNode extends Node implements DefinitionNode
{
    public string $kind = NodeKind::VARIABLE_DEFINITION;

    public function __construct(
        public VariableNode $variable,
        public Node $type,
        public ?Node $defaultValue,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
