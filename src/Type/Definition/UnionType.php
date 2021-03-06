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
    /* HH_FIXME[4032]*/
    public function __construct($config)
    {
        if (!\array_key_exists('name', $config))
        {
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
        $this->description = \array_key_exists('description', $config) ? $config['description'] : null;
        $this->astNode = \array_key_exists('astNode', $config) ? $config['astNode'] : null;
        $this->config = $config ?? [];
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
            $types = null;
            if (\array_key_exists('types', $this->config) && $this->config['types'] !== null)
            {
                if (\is_callable($this->config['types']))
                {
                    /* HH_FIXME[4009]*/
                    $types = call_user_func($this->config['types']);
                }
                else
                {
                    $types = $this->config['types'];
                }
            }

            Utils::invariant(is_array($types), "%s types must be an Array or a callable which returns an Array.", $this->name);

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
        if (\array_key_exists('resolveType', $this->config))
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
            $types !== [],
            "{$this->name} types must not be empty"
        );

        if (\array_key_exists('resolveType', $this->config))
        {
            Utils::invariant(
                \is_callable($this->config['resolveType']),
                "{$this->name} must provide \"resolveType\" as a function."
            );
        }

        $includedTypeNames = [];
        foreach ($types as $objType) {
            Utils::invariant(
                $objType instanceof ObjectType,
                "%s may only contain Object types, it cannot contain: %s.",
                $this->name,
                Utils::printSafe($objType)
            );
            Utils::invariant(
                !\array_key_exists($objType->name, $includedTypeNames),
                "{$this->name} can include {$objType->name} type only once."
            );
            $includedTypeNames[$objType->name] = true;
            if (!\array_key_exists('resolveType', $this->config))
            {
                Utils::invariant(
                    \array_key_exists('isTypeOf', $objType->config) && \is_callable($objType->config['isTypeOf']),
                    "Union type \"{$this->name}\" does not provide a \"resolveType\" " .
                    "function and possible type \"{$objType->name}\" does not provide an " .
                    '"isTypeOf" function. There is no way to resolve this possible type ' .
                    'during execution.'
                );
            }
        }
    }
}
