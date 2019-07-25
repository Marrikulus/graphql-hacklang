<?hh //partial
namespace GraphQL\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\AbstractType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;
use GraphQL\Utils\Utils;

/**
 * EXPERIMENTAL!
 * This class can be removed or changed in future versions without a prior notice.
 *
 * Class LazyResolution
 * @package GraphQL\Type
 */
class LazyResolution implements Resolution
{
    /**
     * @var array
     */
    private array<string, GraphQlType> $typeMap;

    /**
     * @var array
     */
    private array<string, array<string, ObjectType>> $possibleTypeMap;

    /**
     * @var callable
     */
    private (function(string):GraphQlType) $typeLoader;

    /**
     * List of currently loaded types
     *
     * @var Type[]
     */
    private array<string, GraphQlType> $loadedTypes;

    /**
     * Map of $interfaceTypeName => $objectType[]
     *
     * @var array
     */
    private array<string, array<ObjectType>> $loadedPossibleTypes;

    /**
     * LazyResolution constructor.
     * @param array $descriptor
     * @param callable $typeLoader
     */
    public function __construct(array $descriptor, (function(string):GraphQlType) $typeLoader)
    {
        Utils::invariant(
            isset($descriptor['typeMap'], $descriptor['possibleTypeMap'], $descriptor['version'])
        );
        Utils::invariant(
            $descriptor['version'] === '1.0'
        );

        $this->typeLoader = $typeLoader;
        $this->typeMap = $descriptor['typeMap'] + GraphQlType::getInternalTypes();
        $this->possibleTypeMap = $descriptor['possibleTypeMap'];
        $this->loadedTypes = GraphQlType::getInternalTypes();
        $this->loadedPossibleTypes = [];
    }

    /**
     * @inheritdoc
     */
    public function resolveType(string $name):?GraphQlType
    {
        if (!\array_key_exists($name, $this->typeMap))
        {
            return null;
        }

        if (!\array_key_exists($name, $this->loadedTypes))
        {
            $type = call_user_func($this->typeLoader, $name);
            if (!$type instanceof GraphQlType && null !== $type)
            {
                throw new InvariantViolation(
                    "Lazy Type Resolution Error: Expecting GraphQL Type instance, but got " .
                    Utils::getVariableType($type)
                );
            }

            $this->loadedTypes[$name] = $type;
        }
        return $this->loadedTypes[$name];
    }

    /**
     * @inheritdoc
     */
    public function resolvePossibleTypes(AbstractType $type):array<ObjectType>
    {
        if (!\array_key_exists($type->name, $this->possibleTypeMap))
        {
            return [];
        }

        if (!\array_key_exists($type->name, $this->loadedPossibleTypes))
        {
            $tmp = [];
            foreach ($this->possibleTypeMap[$type->name] as $typeName => $true)
            {
                $obj = $this->resolveType($typeName);
                if (!$obj instanceof ObjectType) {
                    throw new InvariantViolation(
                        "Lazy Type Resolution Error: Implementation {$typeName} of interface {$type->name} " .
                        "is expected to be instance of ObjectType, but got " . Utils::getVariableType($obj)
                    );
                }
                $tmp[] = $obj;
            }
            $this->loadedPossibleTypes[$type->name] = $tmp;
        }
        return $this->loadedPossibleTypes[$type->name];
    }
}
