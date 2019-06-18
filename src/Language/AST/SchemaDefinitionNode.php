<?hh //strict
namespace GraphQL\Language\AST;

class SchemaDefinitionNode extends Node implements TypeSystemDefinitionNode
{
    public string $kind = NodeKind::SCHEMA_DEFINITION;

    public function __construct(
        public array<DirectiveNode> $directives,
        public array<Node> $operationTypes,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
