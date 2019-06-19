<?hh //strict
namespace GraphQL\Language\AST;

class DirectiveDefinitionNode extends Node implements TypeSystemDefinitionNode
{
    public function __construct(
        public NameNode $name,
        public array<InputValueDefinitionNode> $arguments,
        public array<NameNode> $locations,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::DIRECTIVE_DEFINITION);
    }
}
