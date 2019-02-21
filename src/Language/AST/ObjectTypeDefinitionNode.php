<?hh //strict
namespace GraphQL\Language\AST;

class ObjectTypeDefinitionNode extends Node implements TypeDefinitionNode
{
    public string $kind = NodeKind::OBJECT_TYPE_DEFINITION;

    public function __construct(
        public NameNode $name,
        public array<NamedTypeNode> $interfaces,
        public NodeList $directives,
        public NodeList $fields,
        public ?string $description,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
