<?hh
namespace GraphQL\Language\AST;

class InputValueDefinitionNode extends Node implements HasDirectives
{
    public function __construct(
        public NameNode $name,
        public Node $type,
        public ?Node $defaultValue,
        public array<DirectiveNode> $directives,
        public ?string $description,
        ?Location $loc = null)
    {
        parent::__construct($loc,NodeKind::INPUT_VALUE_DEFINITION);
    }

    public function getDirectives():array<DirectiveNode>
    {
        return $this->directives;
    }
}
