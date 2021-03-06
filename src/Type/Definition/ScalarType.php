<?hh //strict
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Utils\Utils;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ValueNode;

/**
 * Scalar Type Definition
 *
 * The leaf values of any request and input values to arguments are
 * Scalars (or Enums) and are defined with a name and a series of coercion
 * functions used to ensure validity.
 *
 * Example:
 *
 * class OddType extends ScalarType
 * {
 *     public $name = 'Odd',
 *     public function serialize($value)
 *     {
 *         return $value % 2 === 1 ? $value : null;
 *     }
 * }
 */
abstract class ScalarType<T> extends GraphQlType implements OutputType, InputType, LeafType<T>
{
    /**
     * @var ScalarTypeDefinitionNode|null
     */
    public ?Node $astNode;

    public function __construct(array<string, mixed> $config = [])
    {
        $this->name = (string)idx($config, 'name', $this->tryInferName());
        if (\array_key_exists('description', $config) && ($config['description'] is string))
        {
            $this->description = (string)$config['description'];
        }
        if(\array_key_exists('astNode', $config))
        {
            $node = $config['astNode'];
            if($node !== null && $node instanceof Node)
            {
                $this->astNode = $node;
            }
        }
        $this->config = $config;

        Utils::assertValidName($this->name);
    }

    /**
     * Determines if an internal value is valid for this type.
     * Equivalent to checking for if the parsedValue is nullish.
     *
     * @param $value
     * @return bool
     */
    public function isValidValue(mixed $value):bool
    {
        return null !== $this->parseValue($value);
    }

    /**
     * Determines if an internal value is valid for this type.
     * Equivalent to checking for if the parsedLiteral is nullish.
     *
     * @param $valueNode
     * @return bool
     */
    public function isValidLiteral(ValueNode<T> $valueNode):bool
    {
        return null !== $this->parseLiteral($valueNode);
    }
}
