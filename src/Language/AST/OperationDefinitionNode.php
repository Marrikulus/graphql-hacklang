<?hh
namespace GraphQL\Language\AST;

class OperationDefinitionNode extends Node implements DefinitionNode, HasSelectionSet
{
    public string $kind = NodeKind::OPERATION_DEFINITION;

    public function __construct(
        public ?NameNode $name,
        public string $operation,
        public ?array<Node> $variableDefinitions,
        public array<DirectiveNode> $directives,
        public SelectionSetNode $selectionSet,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
