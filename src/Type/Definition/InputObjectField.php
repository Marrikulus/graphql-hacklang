<?hh //partial
namespace GraphQL\Type\Definition;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\Node;

/**
 * Class InputObjectField
 * @package GraphQL\Type\Definition
 */
class InputObjectField
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var mixed|null
     */
    public $defaultValue;

    /**
     * @var string|null
     */
    public ?string $description;

    /**
     * @var callback|InputType
     */
    public $type;

    /**
     * @var InputValueDefinitionNode|null
     */
    public ?Node $astNode;

    /**
     * @var array
     */
    public $config;

    /**
     * Helps to differentiate when `defaultValue` is `null` and when it was not even set initially
     *
     * @var bool
     */
    private bool $defaultValueExists = false;

    /**
     * InputObjectField constructor.
     * @param array $opts
     */
    public function __construct(array $opts)
    {
        foreach ($opts as $k => $v) {
            switch ($k) {
                case 'defaultValue':
                    $this->defaultValue = $v;
                    $this->defaultValueExists = true;
                    break;
                case 'defaultValueExists':
                    break;
                default:
                    /* HH_FIXME[1002]*/
                    $this->{$k} = $v;
            }
        }
        $this->config = $opts;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return bool
     */
    public function defaultValueExists()
    {
        return $this->defaultValueExists;
    }
}
