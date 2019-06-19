<?hh //strict
namespace GraphQL\Language\AST;

class DirectiveDefinitionNode extends Node implements TypeSystemDefinitionNode
{
    public string $kind = NodeKind::DIRECTIVE_DEFINITION;

    public function __construct(
        public NameNode $name,
        public array<InputValueDefinitionNode> $arguments,
        public array<NameNode> $locations,
        ?Location $loc = null)
    {
        parent::__construct($loc);
    }
}
