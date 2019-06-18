<?hh
namespace GraphQL\Language\AST;

class UnionTypeDefinitionNode extends Node implements TypeDefinitionNode
{
    public string $kind = NodeKind::UNION_TYPE_DEFINITION;

    public function __construct(
        public NameNode $name,
        public array<DirectiveNode> $directives,
        public array<NamedTypeNode> $types,
        public ?string $description,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
