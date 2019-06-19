<?hh
namespace GraphQL\Language\AST;

class TypeExtensionDefinitionNode extends Node implements TypeSystemDefinitionNode
{
    public function __construct(
        public ObjectTypeDefinitionNode $definition,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::TYPE_EXTENSION_DEFINITION);
    }
}
