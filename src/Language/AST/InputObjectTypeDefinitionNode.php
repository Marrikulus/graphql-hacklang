<?hh
namespace GraphQL\Language\AST;

class InputObjectTypeDefinitionNode extends Node implements TypeDefinitionNode, HasDirectives
{
    public function __construct(
        public NameNode $name,
        public array<DirectiveNode> $directives,
        public array<InputValueDefinitionNode> $fields,
        public ?string $description,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::INPUT_OBJECT_TYPE_DEFINITION);
    }

    public function getDirectives():array<DirectiveNode>
    {
    	return $this->directives;
    }
}
