<?hh //strict
namespace GraphQL\Language\AST;

class DirectiveDefinitionNode extends Node implements TypeSystemDefinitionNode
{
    public string $kind = NodeKind::DIRECTIVE_DEFINITION;

    public function __construct(
        public NameNode $name,
        public NodeList $arguments,
        public array<NameNode> $locations,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
