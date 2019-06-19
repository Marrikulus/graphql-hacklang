<?hh
namespace GraphQL\Language\AST;

class VariableDefinitionNode extends Node implements DefinitionNode
{
    public function __construct(
        public VariableNode $variable,
        public Node $type,
        public ?Node $defaultValue,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::VARIABLE_DEFINITION);
    }
}
