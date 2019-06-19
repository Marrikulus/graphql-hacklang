<?hh
namespace GraphQL\Language\AST;

class ScalarTypeDefinitionNode extends Node implements TypeDefinitionNode
{
    public function __construct(
        public NameNode $name,
        public array<DirectiveNode> $directives = [],
        public ?string $description,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::SCALAR_TYPE_DEFINITION);
    }
}
