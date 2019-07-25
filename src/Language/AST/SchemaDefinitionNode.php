<?hh //strict
namespace GraphQL\Language\AST;

class SchemaDefinitionNode extends Node implements TypeSystemDefinitionNode, HasDirectives
{
    public function __construct(
        public array<DirectiveNode> $directives,
        public array<Node> $operationTypes,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::SCHEMA_DEFINITION);
    }

    public function getDirectives():array<DirectiveNode>
    {
    	return $this->directives;
    }
}
