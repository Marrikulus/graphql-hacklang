<?hh
namespace GraphQL\Language\AST;

class InputValueDefinitionNode extends Node
{
    public string $kind = NodeKind::INPUT_VALUE_DEFINITION;

    public function __construct(
        public NameNode $name,
        public Node $type,
        public ?Node $defaultValue,
        public array<DirectiveNode> $directives,
        public ?string $description,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
