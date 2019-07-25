<?hh //partial
namespace GraphQL\Type\Definition;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Utils\Utils;

/**
 * Class EnumValueDefinition
 * @package GraphQL\Type\Definition
 */
class EnumValueDefinition
{
    /**
     * @var string
     */
    public string $name;

    /**
     * @var mixed
     */
    public mixed $value;

    /**
     * @var string|null
     */
    public ?string $deprecationReason;

    /**
     * @var string|null
     */
    public ?string $description;

    /**
     * @var EnumValueDefinitionNode|null
     */
    public ?EnumValueDefinitionNode $astNode;

    /**
     * @var array
     */
    public $config;

    public function __construct(array $config)
    {
        $this->name = \array_key_exists('name', $config) ? $config['name'] : null;
        $this->value = \array_key_exists('value', $config) ? $config['value'] : null;
        $this->deprecationReason = \array_key_exists('deprecationReason', $config) ? $config['deprecationReason'] : null;
        $this->description = \array_key_exists('description', $config) ? $config['description'] : null;
        $this->astNode = \array_key_exists('astNode', $config) ? $config['astNode'] : null;

        $this->config = $config;
    }

    /**
     * @return bool
     */
    public function isDeprecated():bool
    {
        return !!$this->deprecationReason;
    }
}
