<?hh //strict
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\Node;

/**
 * Class BooleanType
 * @package GraphQL\Type\Definition
 */
/* HH_FIXME[4110]*/
class BooleanType extends ScalarType
{
    /**
     * @var string
     */
    public string $name = GraphQlType::BOOLEAN;

    /**
     * @var string
     */
    public string $description = 'The `Boolean` scalar type represents `true` or `false`.';

    /**
     * @param mixed $value
     * @return bool
     */
    public function serialize(mixed $value):bool
    {
        return (bool)$value;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    public function parseValue(mixed $value):?bool
    {
        if (\is_bool($value))
        {
            return (bool)$value;
        }
        return null;
    }

    /**
     * @param $ast
     * @return bool|null
     */
    public function parseLiteral(Node $ast):?bool
    {
        if ($ast instanceof BooleanValueNode) {
            return (bool) $ast->value;
        }
        return null;
    }

    public function isValidValue(mixed $value):bool
    {
        return null !== $this->parseValue($value);
    }

    public function isValidLiteral(Node $valueNode):bool
    {
        return null !== $this->parseLiteral($valueNode);
    }
}
