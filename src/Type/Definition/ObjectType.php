<?hh //partial
namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionDefinitionNode;
use GraphQL\Utils\Utils;
use GraphQL\Language\AST\Node;


/**
 * Object Type Definition
 *
 * Almost all of the GraphQL types you define will be object types. Object types
 * have a name, but most importantly describe their fields.
 *
 * Example:
 *
 *     $AddressType = new ObjectType([
 *       'name' => 'Address',
 *       'fields' => [
 *         'street' => [ 'type' => GraphQL\Type\Definition\GraphQlType::string() ],
 *         'number' => [ 'type' => GraphQL\Type\Definition\GraphQlType::int() ],
 *         'formatted' => [
 *           'type' => GraphQL\Type\Definition\GraphQlType::string(),
 *           'resolve' => function($obj) {
 *             return $obj->number . ' ' . $obj->street;
 *           }
 *         ]
 *       ]
 *     ]);
 *
 * When two types need to refer to each other, or a type needs to refer to
 * itself in a field, you can use a function expression (aka a closure or a
 * thunk) to supply the fields lazily.
 *
 * Example:
 *
 *     $PersonType = null;
 *     $PersonType = new ObjectType([
 *       'name' => 'Person',
 *       'fields' => function() use (&$PersonType) {
 *          return [
 *              'name' => ['type' => GraphQL\Type\Definition\GraphQlType::string() ],
 *              'bestFriend' => [ 'type' => $PersonType ],
 *          ];
 *        }
 *     ]);
 *
 */
class ObjectType extends GraphQlType implements OutputType, CompositeType
{
    /**
     * @var FieldDefinition[]
     */
    private $fields;

    /**
     * @var InterfaceType[]
     */
    private $interfaces;

    /**
     * @var array<string, InterfaceType>
     */
    private $interfaceMap;

    /**
     * @var ObjectTypeDefinitionNode|null
     */
    public ?Node $astNode;

    /**
     * @var TypeExtensionDefinitionNode[]
     */
    public $extensionASTNodes;

    /**
     * @var callable
     */
    public $resolveFieldFn;

    /**
     * ObjectType constructor.
     * @param array $config
     */
    public function __construct($config)
    {
        if (!\array_key_exists('name', $config))
        {
            $config['name'] = $this->tryInferName();
        }

        Utils::assertValidName($config['name'], !empty($config['isIntrospection']));

        // Note: this validation is disabled by default, because it is resource-consuming
        // TODO: add bin/validate script to check if schema is valid during development
        Config::validate($config, [
            'name' => Config::NAME | Config::REQUIRED,
            'fields' => Config::arrayOf(
                FieldDefinition::getDefinition(),
                Config::KEY_AS_NAME | Config::MAYBE_THUNK | Config::MAYBE_TYPE
            ),
            'description' => Config::STRING,
            'interfaces' => Config::arrayOf(
                Config::INTERFACE_TYPE,
                Config::MAYBE_THUNK
            ),
            'isTypeOf' => Config::CALLBACK, // ($value, $context, ResolveInfo $info) => boolean
            'resolveField' => Config::CALLBACK // ($value, $args, $context, ResolveInfo $info) => $fieldValue
        ]);

        $this->name = $config['name'];
        $this->description = \array_key_exists('description', $config) ? $config['description'] : null;
        $this->resolveFieldFn = \array_key_exists('resolveField', $config) ? $config['resolveField'] : null;
        $this->astNode = \array_key_exists('astNode', $config) ? $config['astNode'] : null;
        $this->extensionASTNodes = \array_key_exists('extensionASTNodes', $config) ? $config['extensionASTNodes'] : [];
        $this->config = $config;
    }

    /**
     * @return FieldDefinition[]
     * @throws InvariantViolation
     */
    public function getFields()
    {
        if (null === $this->fields)
        {
            $fields = \array_key_exists('fields', $this->config) ? $this->config['fields'] : [];
            $this->fields = FieldDefinition::defineFieldMap($this, $fields);
        }
        return $this->fields;
    }

    /**
     * @param string $name
     * @return FieldDefinition
     * @throws \Exception
     */
    public function getField(string $name)
    {
        if (null === $this->fields)
        {
            $this->getFields();
        }
        Utils::invariant(\array_key_exists($name, $this->fields), 'Field "%s" is not defined for type "%s"', $name, $this->name);
        return $this->fields[$name];
    }

    /**
     * @return InterfaceType[]
     */
    public function getInterfaces()
    {
        if (null === $this->interfaces)
        {
            $interfaces = \array_key_exists('interfaces', $this->config) ? $this->config['interfaces'] : [];
            $interfaces = \is_callable($interfaces) ? call_user_func($interfaces) : $interfaces;

            if (!is_array($interfaces))
            {
                throw new InvariantViolation(
                    "{$this->name} interfaces must be an Array or a callable which returns an Array."
                );
            }

            $this->interfaces = $interfaces;
        }
        return $this->interfaces;
    }

    private function getInterfaceMap()
    {
        if ($this->interfaceMap === null)
        {
            $this->interfaceMap = [];
            foreach ($this->getInterfaces() as $interface)
            {
                $this->interfaceMap[$interface->name] = $interface;
            }
        }
        return $this->interfaceMap;
    }

    /**
     * @param InterfaceType $iface
     * @return bool
     */
    public function implementsInterface($iface)
    {
        $map = $this->getInterfaceMap();
        return isset($map[$iface->name]);
    }

    /**
     * @param $value
     * @param $context
     * @param ResolveInfo $info
     * @return bool|null
     */
    public function isTypeOf($value, $context, ResolveInfo $info)
    {
        return isset($this->config['isTypeOf']) ? call_user_func($this->config['isTypeOf'], $value, $context, $info) : null;
    }

    /**
     * Validates type config and throws if one of type options is invalid.
     * Note: this method is shallow, it won't validate object fields and their arguments.
     *
     * @throws InvariantViolation
     */
    public function assertValid()
    {
        parent::assertValid();

        Utils::invariant(
            null === $this->description || ($this->description is string),
            "{$this->name} description must be string if set, but it is: " . Utils::printSafe($this->description)
        );

        Utils::invariant(
            !isset($this->config['isTypeOf']) || \is_callable($this->config['isTypeOf']),
            "{$this->name} must provide 'isTypeOf' as a function"
        );

        // getFields() and getInterfaceMap() will do structural validation
        $fields = $this->getFields();
        Utils::invariant(
            !empty($fields),
            "{$this->name} fields must not be empty"
        );
        foreach ($fields as $field) {
            $field->assertValid($this);
            foreach ($field->args as $arg) {
                $arg->assertValid($field, $this);
            }
        }

        $implemented = [];
        foreach ($this->getInterfaces() as $iface)
        {
            Utils::invariant(
                $iface instanceof InterfaceType,
                "{$this->name} may only implement Interface types, it cannot implement %s.",
                Utils::printSafe($iface)
            );
            Utils::invariant(
                !isset($implemented[$iface->name]),
                "{$this->name} may declare it implements {$iface->name} only once."
            );
            $implemented[$iface->name] = true;
            if (!isset($iface->config['resolveType']))
            {
                Utils::invariant(
                    isset($this->config['isTypeOf']),
                    "Interface Type {$iface->name} does not provide a \"resolveType\" " .
                    "function and implementing Type {$this->name} does not provide a " .
                    '"isTypeOf" function. There is no way to resolve this implementing ' .
                    'type during execution.'
                );
            }
        }
    }
}
