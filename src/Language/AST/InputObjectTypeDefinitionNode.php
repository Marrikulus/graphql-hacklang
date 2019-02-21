<?hh
namespace GraphQL\Language\AST;

class InputObjectTypeDefinitionNode extends Node implements TypeDefinitionNode
{
    public string $kind = NodeKind::INPUT_OBJECT_TYPE_DEFINITION;

    public function __construct(
        public NameNode $name,
        public NodeList $directives,
        public NodeList $fields,
        public ?string $description,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
