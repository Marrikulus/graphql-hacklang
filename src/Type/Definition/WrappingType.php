<?hh //decl
namespace GraphQL\Type\Definition;

interface WrappingType
{
    /**
     * @param bool $recurse
     * @return ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType
     */
    public function getWrappedType(@bool $recurse = false);
}
