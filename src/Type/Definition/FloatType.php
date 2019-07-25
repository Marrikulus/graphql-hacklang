<?hh //strict
//partial
namespace GraphQL\Type\Definition;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Utils\Utils;

/**
 * Class FloatType
 * @package GraphQL\Type\Definition
 */
class FloatType extends ScalarType<?string>
{
    /**
     * @var string
     */
    public string $name = GraphQlType::FLOAT;

    /**
     * @var string
     */
    public ?string $description =
'The `Float` scalar type represents signed double-precision fractional
values as specified by
[IEEE 754](http://en.wikipedia.org/wiki/IEEE_floating_point). ';

    /**
     * @param mixed $value
     * @return float|null
     */
    public function serialize(mixed $value):?float
    {
        if (is_numeric($value) || $value === true || $value === false) {
            return (float) $value;
        }

        if ($value === '') {
            $err = 'Float cannot represent non numeric value: (empty string)';
        } else {
            $err = \sprintf('Float cannot represent non numeric value: %s', Utils::printSafe($value));
        }
        throw new InvariantViolation($err);
    }

    /**
     * @param mixed $value
     * @return float|null
     */
    public function parseValue(mixed $value):?float
    {
        return (is_numeric($value) && !($value is string)) ? (float) $value : null;
    }

    /**
     * @param $ast
     * @return float|null
     */
    public function parseLiteral(ValueNode<?string> $ast):?float
    {
        if ($ast instanceof FloatValueNode || $ast instanceof IntValueNode) {
            return (float) $ast->getValue();
        }
        return null;
    }

    public function isValidValue(mixed $value):bool
    {
        return null !== $this->parseValue($value);
    }

    public function isValidLiteral(ValueNode<?string> $valueNode):bool
    {
        return null !== $this->parseLiteral($valueNode);
    }
}
