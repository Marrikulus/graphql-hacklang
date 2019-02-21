<?hh
namespace GraphQL\Language\AST;

class OperationDefinitionNode extends Node implements DefinitionNode, HasSelectionSet
{
    public string $kind = NodeKind::OPERATION_DEFINITION;

    public function __construct(
        public ?NameNode $name,
        public string $operation,
        public ?NodeList $variableDefinitions,
        public NodeList $directives,
        public SelectionSetNode $selectionSet,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
