<?hh
namespace GraphQL\Language\AST;

class TypeExtensionDefinitionNode extends Node implements TypeSystemDefinitionNode
{
    public string $kind = NodeKind::TYPE_EXTENSION_DEFINITION;

    /**
     * @var ObjectTypeDefinitionNode
     */
    public $definition;

    public function __construct(
        public ObjectTypeDefinitionNode $definition,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
