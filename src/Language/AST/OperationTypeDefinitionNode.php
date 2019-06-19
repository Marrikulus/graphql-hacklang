<?hh
namespace GraphQL\Language\AST;

class OperationTypeDefinitionNode extends Node
{
    public function __construct(
        public string $operation,
        public NamedTypeNode $type,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::OPERATION_TYPE_DEFINITION);
    }
}
