<?hh //strict
namespace GraphQL\Language\AST;

class EnumTypeDefinitionNode extends Node implements TypeDefinitionNode
{
    public function __construct(
        public NameNode $name,
        public array<DirectiveNode> $directives,
        public array<Node> $values,
        public ?string $description,
        ?Location $loc = null)
    {
        parent::__construct($loc,NodeKind::ENUM_TYPE_DEFINITION);
    }
}
