<?hh
namespace GraphQL\Language\AST;

class ScalarTypeDefinitionNode extends Node implements TypeDefinitionNode
{
    public string $kind = NodeKind::SCALAR_TYPE_DEFINITION;

    public function __construct(
        public NameNode $name,
        public NodeList $directives,
        public ?string $description,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
