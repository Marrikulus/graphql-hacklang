<?hh //strict
//decl
namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Utils\Utils;


/**
 * Class FieldArgument
 *
 * @package GraphQL\Type\Definition
 */
class FieldArgument
{
    /**
     * @var string
     */
    public string $name;

    /**
     * @var mixed
     */
    public $defaultValue;

    /**
     * @var string|null
     */
    public ?string $description;

    /**
     * @var InputValueDefinitionNode|null
     */
    public $astNode;

    /**
     * @var array
     */
    public $config;

    /**
     * @var InputType
     */
    private $type;

    /**
     * @var bool
     */
    private bool $defaultValueExists = false;

    /**
     * @param array $config
     * @return array
     */
    public static function createMap(array $config)
    {
        $map = [];
        foreach ($config as $name => $argConfig) {
            if (!is_array($argConfig)) {
                $argConfig = ['type' => $argConfig];
            }
            $map[] = new self($argConfig + ['name' => $name]);
        }
        return $map;
    }

    /**
     * FieldArgument constructor.
     * @param array $def
     */
    public function __construct(array $def)
    {
        foreach ($def as $key => $value) {
            switch ($key) {
                case 'type':
                    $this->type = $value;
                    break;
                case 'name':
                    $this->name = $value;
                    break;
                case 'defaultValue':
                    $this->defaultValue = $value;
                    $this->defaultValueExists = true;
                    break;
                case 'description':
                    $this->description = $value;
                    break;
                case 'astNode':
                    $this->astNode = $value;
                    break;
            }
        }
        $this->config = $def;
    }

    /**
     * @return InputType
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

    public function assertValid(FieldDefinition $parentField, GraphQlType $parentType)
    {
        try {
            Utils::assertValidName($this->name);
        } catch (InvariantViolation $e) {
            throw new InvariantViolation(
                "{$parentType->name}.{$parentField->name}({$this->name}:) {$e->getMessage()}")
            ;
        }
        $type = $this->type;
        if ($type instanceof WrappingType) {
            $type = $type->getWrappedType(true);
        }
        Utils::invariant(
            $type instanceof InputType,
            "{$parentType->name}.{$parentField->name}({$this->name}): argument type must be " .
            "Input Type but got: " . Utils::printSafe($this->type)
        );
        Utils::invariant(
            $this->description === null || ($this->description is string),
            "{$parentType->name}.{$parentField->name}({$this->name}): argument description type must be " .
            "string but got: " . Utils::printSafe($this->description)
        );
    }
}
