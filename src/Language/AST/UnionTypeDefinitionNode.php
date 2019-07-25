<?hh
namespace GraphQL\Language\AST;

class UnionTypeDefinitionNode extends Node implements TypeDefinitionNode, HasDirectives
{
    public function __construct(
        public NameNode $name,
        public array<DirectiveNode> $directives,
        public array<NamedTypeNode> $types,
        public ?string $description,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::UNION_TYPE_DEFINITION);
    }

    public function getDirectives():array<DirectiveNode>
    {
    	return $this->directives;
    }
}
