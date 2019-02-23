<?hh //decl
namespace GraphQL\Type\Definition;

use GraphQL\Utils\Utils;
use GraphQL\Language\AST\Node;

/**
 * Class CustomScalarType
 * @package GraphQL\Type\Definition
 */
class CustomScalarType extends ScalarType
{
    /**
     * @param mixed $value
     * @return mixed
     */
    public function serialize($value)
    {
        return call_user_func($this->config['serialize'], $value);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function parseValue($value)
    {
        if (isset($this->config['parseValue'])) {
            return call_user_func($this->config['parseValue'], $value);
        } else {
            return null;
        }
    }

    /**
     * @param $valueNode
     * @return mixed
     */
    public function parseLiteral(Node $valueNode):mixed
    {
        if (isset($this->config['parseLiteral'])) {
            return call_user_func($this->config['parseLiteral'], $valueNode);
        } else {
            return null;
        }
    }

    public function assertValid()
    {
        parent::assertValid();

        Utils::invariant(
            isset($this->config['serialize']) && is_callable($this->config['serialize']),
            "{$this->name} must provide \"serialize\" function. If this custom Scalar " .
            'is also used as an input type, ensure "parseValue" and "parseLiteral" ' .
            'functions are also provided.'
        );
        if (isset($this->config['parseValue']) || isset($this->config['parseLiteral'])) {
            Utils::invariant(
                isset($this->config['parseValue']) && isset($this->config['parseLiteral']) &&
                is_callable($this->config['parseValue']) && is_callable($this->config['parseLiteral']),
                "{$this->name} must provide both \"parseValue\" and \"parseLiteral\" functions."
            );
        }
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
