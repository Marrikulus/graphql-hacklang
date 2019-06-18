<?hh //strict
namespace GraphQL\Language\AST;

class FieldDefinitionNode extends Node
{
    public string $kind = NodeKind::FIELD_DEFINITION;

    public function __construct(
        public NameNode $name,
        public array<InputValueDefinitionNode> $values,
        public Node $type,
        public array<DirectiveNode> $directives,
        public ?string $description,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
