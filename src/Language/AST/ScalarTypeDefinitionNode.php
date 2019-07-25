<?hh
namespace GraphQL\Language\AST;

class ScalarTypeDefinitionNode extends Node implements TypeDefinitionNode, HasDirectives
{
    public function __construct(
        public NameNode $name,
        public array<DirectiveNode> $directives = [],
        public ?string $description = null,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::SCALAR_TYPE_DEFINITION);
    }

    public function getDirectives():array<DirectiveNode>
    {
    	return $this->directives;
    }
}
