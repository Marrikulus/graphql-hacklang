<?hh //strict
namespace GraphQL\Language\AST;

class EnumValueDefinitionNode extends Node implements HasDirectives
{
    public function __construct(
        public NameNode $name,
        public array<DirectiveNode> $directives,
        public ?string $description,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::ENUM_VALUE_DEFINITION);
    }

    public function getDirectives():array<DirectiveNode>
    {
    	return $this->directives;
    }
}
