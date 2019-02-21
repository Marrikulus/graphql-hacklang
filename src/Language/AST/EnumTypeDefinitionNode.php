<?hh //strict
namespace GraphQL\Language\AST;

class EnumTypeDefinitionNode extends Node implements TypeDefinitionNode
{
    public string $kind = NodeKind::ENUM_TYPE_DEFINITION;

    public function __construct(
        public NameNode $name,
        public NodeList $directives,
        public NodeList $values,
        public ?string $description,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
