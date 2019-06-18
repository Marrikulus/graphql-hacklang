<?hh
namespace GraphQL\Language\AST;

class InterfaceTypeDefinitionNode extends Node implements TypeDefinitionNode
{
    public string $kind = NodeKind::INTERFACE_TYPE_DEFINITION;

    public function __construct(
        public NameNode $name,
        public array<DirectiveNode> $directives,
        public array<Node> $fields,
        public ?string $description,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
