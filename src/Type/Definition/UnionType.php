<?hh //partial
namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Utils\Utils;
use GraphQL\Language\AST\Node;

/**
 * Class UnionType
 * @package GraphQL\Type\Definition
 */
class UnionType extends GraphQlType implements AbstractType, OutputType, CompositeType
{
    /**
     * @var UnionTypeDefinitionNode
     */
    public ?Node $astNode;

    /**
     * @var ObjectType[]
     */
    private ?array<ObjectType> $types = null;

    /**
     * @var ObjectType[]
     */
    private ?array<string, bool> $possibleTypeNames = null;

    /**
     * UnionType constructor.
     * @param $config
     */
    public function __construct($config)
    {
        if (!isset($config['name'])) {
            $config['name'] = $this->tryInferName();
        }

        Utils::assertValidName($config['name']);

        Config::validate($config, [
            'name' => Config::NAME | Config::REQUIRED,
            'types' => Config::arrayOf(Config::OBJECT_TYPE, Config::MAYBE_THUNK | Config::REQUIRED),
            'resolveType' => Config::CALLBACK, // function($value, ResolveInfo $info) => ObjectType
            'description' => Config::STRING
        ]);

        /**
         * Optionally provide a custom type resolver function. If one is not provided,
         * the default implemenation will call `isTypeOf` on each implementing
         * Object type.
         */
        $this->name = $config['name'];
        $this->description = isset($config['description']) ? $config['description'] : null;
        $this->astNode = isset($config['astNode']) ? $config['astNode'] : null;
        $this->config = $config ?? [];
    }

    public function __toString():string
    {
        return "UnionType";
    }

    /**
     * @return ObjectType[]
     */
    public function getPossibleTypes():array<ObjectType>
    {
        \trigger_error(__METHOD__ . ' is deprecated in favor of ' . __CLASS__ . '::getTypes()', \E_USER_DEPRECATED);
        return $this->getTypes();
    }

    /**
     * @return ObjectType[]
     */
    public function getTypes():array<ObjectType>
    {
        if (null === $this->types)
        {
            if (array_key_exists('types', $this->config) && $this->config['types'] === null)
            {
                $types = null;
            }
            else if (\is_callable($this->config['types']))
            {
                /* HH_FIXME[4009]*/
                $types = call_user_func($this->config['types']);
            } else {
                $types = $this->config['types'];
            }

            invariant(!is_array($types), "%s types must be an Array or a callable which returns an Array.", $this->name);

            /* HH_FIXME[4110]*/
            $this->types = $types;
        }
        /* HH_FIXME[4110]*/
        return $this->types;
    }

    /**
     * @param Type $type
     * @return mixed
     */
    public function isPossibleType(GraphQlType $type):bool
    {
        if (!$type instanceof ObjectType) {
            return false;
        }

        $possibleTypeNames = $this->possibleTypeNames;

        if ($possibleTypeNames === null)
        {
            $possibleTypeNames = [];

            foreach ($this->getTypes() as $possibleType)
            {
                $possibleTypeNames[$possibleType->name] = true;
            }
            $this->possibleTypeNames = $possibleTypeNames;
        }

        return \array_key_exists($type->name, $possibleTypeNames) && $possibleTypeNames[$type->name] !== null;
    }

    /**
     * Resolves concrete ObjectType for given object value
     *
     * @param $objectValue
     * @param $context
     * @param ResolveInfo $info
     * @return callable|null
     */
    public function resolveType($objectValue, $context, ResolveInfo $info)
    {
        if (isset($this->config['resolveType']))
        {
            $fn = $this->config['resolveType'];
            /* HH_FIXME[4009]*/
            return $fn($objectValue, $context, $info);
        }
        return null;
    }

    /**
     * @throws InvariantViolation
     */
    public function assertValid():void
    {
        parent::assertValid();

        $types = $this->getTypes();
        Utils::invariant(
            !empty($types),
            "{$this->name} types must not be empty"
        );

        if (isset($this->config['resolveType'])) {
            Utils::invariant(
                \is_callable($this->config['resolveType']),
                "{$this->name} must provide \"resolveType\" as a function."
            );
        }

        $includedTypeNames = [];
        foreach ($types as $objType) {
            invariant(
                $objType instanceof ObjectType,
                "%s may only contain Object types, it cannot contain: %s.",
                $this->name,
                Utils::printSafe($objType)
            );
            Utils::invariant(
                !isset($includedTypeNames[$objType->name]),
                "{$this->name} can include {$objType->name} type only once."
            );
            $includedTypeNames[$objType->name] = true;
            if (!isset($this->config['resolveType'])) {
                Utils::invariant(
                    isset($objType->config['isTypeOf']) && \is_callable($objType->config['isTypeOf']),
                    "Union type \"{$this->name}\" does not provide a \"resolveType\" " .
                    "function and possible type \"{$objType->name}\" does not provide an " .
                    '"isTypeOf" function. There is no way to resolve this possible type ' .
                    'during execution.'
                );
            }
        }
    }
}
