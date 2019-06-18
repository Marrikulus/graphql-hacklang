<?hh //strict
//partial
namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Utils\Utils;

/**
 * Class NoNull
 * @package GraphQL\Type\Definition
 */
class NoNull extends GraphQlType implements WrappingType, OutputType, InputType
{
    /**
     * @var ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType
     */
    private $ofType;

    /**
     * @param callable|Type $type
     * @throws \Exception
     */
    public function __construct($type)
    {
        if (!$type instanceof GraphQlType && !\is_callable($type)) {
            throw new InvariantViolation(
                'Can only create NoNull of a Nullable GraphQLType but got: ' . Utils::printSafe($type)
            );
        }
        if ($type instanceof NoNull) {
            throw new InvariantViolation(
                'Can only create NoNull of a Nullable GraphQLType but got: ' . Utils::printSafe($type)
            );
        }

        Utils::invariant(
            !($type instanceof NoNull),
            'Cannot nest NoNull inside NoNull'
        );
        $this->ofType = $type;
    }

    /**
     * @param bool $recurse
     * @return ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType
     * @throws InvariantViolation
     */
    public function getWrappedType(@bool $recurse = false)
    {
        $type = $this->ofType;

        Utils::invariant(
            !($type instanceof NoNull),
            'Cannot nest NoNull inside NoNull'
        );

        return ($recurse && $type instanceof WrappingType) ? $type->getWrappedType($recurse) : $type;
    }

    /**
     * @return string
     */
    public function toString()
    {
        return $this->getWrappedType()->toString() . '!';
    }
}
