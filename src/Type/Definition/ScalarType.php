<?hh //strict
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Utils\Utils;
use GraphQL\Language\AST\Node;

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
/* HH_FIXME[4110]*/
abstract class ScalarType extends GraphQlType implements OutputType, InputType, LeafType
{
    /**
     * @var ScalarTypeDefinitionNode|null
     */
    public ?Node $astNode;

    public function __construct(array<string, mixed> $config = [])
    {
        $this->name = (string)idx($config, 'name', $this->tryInferName());
        if (\array_key_exists('description', $config) && is_string($config['description']))
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
    public function isValidLiteral(Node $valueNode):bool
    {
        return null !== $this->parseLiteral($valueNode);
    }
}
