<?hh //strict
namespace GraphQL\Language\AST;

class SchemaDefinitionNode extends Node implements TypeSystemDefinitionNode
{
    public function __construct(
        public array<DirectiveNode> $directives,
        public array<Node> $operationTypes,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::SCHEMA_DEFINITION);
    }
}
