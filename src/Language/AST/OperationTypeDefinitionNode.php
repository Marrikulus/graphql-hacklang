<?hh
namespace GraphQL\Language\AST;

class OperationTypeDefinitionNode extends Node
{
    public string $kind = NodeKind::OPERATION_TYPE_DEFINITION;

    public function __construct(
        public string $operation,
        public NamedTypeNode $type,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
