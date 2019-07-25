<?hh //strict
namespace GraphQL\Language\AST;

class ObjectTypeDefinitionNode extends Node implements TypeDefinitionNode, HasDirectives
{
    public function __construct(
        public NameNode $name,
        public array<NamedTypeNode> $interfaces,
        public array<DirectiveNode> $directives,
        public array<Node> $fields,
        public ?string $description,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::OBJECT_TYPE_DEFINITION);
    }

    public function getDirectives():array<DirectiveNode>
    {
        return $this->directives;
    }
}
