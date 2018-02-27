<?hh //decl
namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Utils\Utils;

/**
 * Class ListOfType
 * @package GraphQL\Type\Definition
 */
class ListOfType extends GraphQlType implements WrappingType, OutputType, InputType
{
    /**
     * @var ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType
     */
    public $ofType;

    /**
     * @param callable|GraphQlType $type
     */
    public function __construct($type)
    {

        if (!$type instanceof GraphQlType && !is_callable($type)) {
            throw new InvariantViolation(
                'Can only create List of a GraphQLType but got: ' . Utils::printSafe($type)
            );
        }
        $this->ofType = $type;
    }

    /**
     * @return string
     */
    public function toString()
    {
        $type = $this->ofType;
        $str = $type instanceof GraphQlType ? $type->toString() : (string) $type;
        return '[' . $str . ']';
    }

    /**
     * @param bool $recurse
     * @return ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType
     */
    public function getWrappedType(@bool $recurse = false)
    {
        $type = $this->ofType;
        return ($recurse && $type instanceof WrappingType) ? $type->getWrappedType($recurse) : $type;
    }
}
