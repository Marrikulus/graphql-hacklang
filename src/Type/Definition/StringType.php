<?hh //strict
//partial
namespace GraphQL\Type\Definition;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Utils\Utils;

/**
 * Class StringType
 * @package GraphQL\Type\Definition
 */
class StringType extends ScalarType<?string>
{
    /**
     * @var string
     */
    public string $name = GraphQlType::STRING;

    /**
     * @var string
     */
    public ?string $description =
'The `String` scalar type represents textual data, represented as UTF-8
character sequences. The String type is most often used by GraphQL to
represent free-form human-readable text.';

    /**
     * @param mixed $value
     * @return mixed|string
     */
    public function serialize(mixed $value):string
    {
        if ($value === true) {
            return 'true';
        }
        if ($value === false) {
            return 'false';
        }
        if ($value === null) {
            return 'null';
        }
        if (!\is_scalar($value)) {
            throw new InvariantViolation("String cannot represent non scalar value: " . Utils::printSafe($value));
        }
        return (string) $value;
    }

    /**
     * @param mixed $value
     * @return string
     */
    public function parseValue(mixed $value):?string
    {
        return ($value is string) ? $value : null;
    }

    /**
     * @param $ast
     * @return null|string
     */
    public function parseLiteral(ValueNode<?string> $ast):mixed
    {
        if ($ast instanceof StringValueNode) {
            return $ast->getValue();
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
