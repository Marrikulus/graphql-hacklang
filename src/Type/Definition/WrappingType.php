<?hh //partial
namespace GraphQL\Type\Definition;

interface WrappingType
{
	require extends GraphQlType;
    /**
     * @param bool $recurse
     * @return ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType
     */
    public function getWrappedType(@bool $recurse = false);
}
