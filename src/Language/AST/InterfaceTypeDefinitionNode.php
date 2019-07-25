<?hh
namespace GraphQL\Language\AST;

class InterfaceTypeDefinitionNode extends Node implements TypeDefinitionNode, HasDirectives
{
    public function __construct(
        public NameNode $name,
        public array<DirectiveNode> $directives,
        public array<Node> $fields,
        public ?string $description,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::INTERFACE_TYPE_DEFINITION);
    }

    public function getDirectives():array<DirectiveNode>
    {
    	return $this->directives;
    }
}
