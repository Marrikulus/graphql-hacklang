<?hh //strict
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ValueNode;

/**
 * Class BooleanType
 * @package GraphQL\Type\Definition
 */
/* HH_FIXME[4110]*/
class BooleanType extends ScalarType<bool>
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
        if ($value is bool)
        {
            return (bool)$value;
        }
        return null;
    }

    /**
     * @param $ast
     * @return bool|null
     */
    public function parseLiteral(ValueNode<bool> $ast):?bool
    {
        if ($ast instanceof BooleanValueNode) {
            return (bool) $ast->getValue();
        }
        return null;
    }

    public function isValidValue(mixed $value):bool
    {
        return null !== $this->parseValue($value);
    }

    public function isValidLiteral(ValueNode<bool> $valueNode):bool
    {
        return null !== $this->parseLiteral($valueNode);
    }
}
