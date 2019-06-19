<?hh //strict
namespace GraphQL\Language\AST;

class FieldDefinitionNode extends Node
{
    public function __construct(
        public NameNode $name,
        public array<InputValueDefinitionNode> $values,
        public Node $type,
        public array<DirectiveNode> $directives,
        public ?string $description,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::FIELD_DEFINITION);
    }
}
