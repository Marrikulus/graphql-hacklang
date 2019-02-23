<?hh //partial
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ValueNode;
/*
export type GraphQLLeafType =
GraphQLScalarType |
GraphQLEnumType;
*/
interface LeafType<T>
{
    /**
     * Serializes an internal value to include in a response.
     *
     * @param mixed $value
     * @return mixed
     */
    public function serialize($value);

    /**
     * Parses an externally provided value (query variable) to use as an input
     *
     * @param mixed $value
     * @return mixed
     */
    public function parseValue($value);

    /**
     * Parses an externally provided literal value (hardcoded in GraphQL query) to use as an input
     *
     * @param \GraphQL\Language\AST\Node $valueNode
     * @return mixed
     */
    public function parseLiteral(ValueNode<T> $valueNode):mixed;

    /**
     * @param string $value
     * @return bool
     */
    public function isValidValue(mixed $value):bool;

    /**
     * @param \GraphQL\Language\AST\Node $valueNode
     * @return mixed
     */
    public function isValidLiteral(ValueNode<T> $valueNode):bool;
}
