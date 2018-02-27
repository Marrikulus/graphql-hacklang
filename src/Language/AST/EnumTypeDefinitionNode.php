<?hh
namespace GraphQL\Language\AST;

class EnumTypeDefinitionNode extends Node implements TypeDefinitionNode
{
    /**
     * @var string
     */
    public string $kind = NodeKind::ENUM_TYPE_DEFINITION;

    /**
     * @var NameNode
     */
    public $name;

    /**
     * @var DirectiveNode[]
     */
    public $directives;

    /**
     * @var EnumValueDefinitionNode[]
     */
    public $values;

    /**
     * @var string
     */
    public $description;
}
