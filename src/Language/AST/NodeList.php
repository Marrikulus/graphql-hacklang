<?hh //partial
namespace GraphQL\Language\AST;

use GraphQL\Utils\AST;

/**
 * Class NodeList
 *
 * @package GraphQL\Utils
 */
class NodeList implements \ArrayAccess<int, Node>, \IteratorAggregate<Node>, \Countable
{
    /**
     * @var array
     */
    private array<Node> $nodes;

    /**
     * @param array $nodes
     * @return static
     */
    public static function create(array<Node> $nodes)
    {
        return new NodeList($nodes);
    }

    /**
     * NodeList constructor.
     * @param array $nodes
     */
    public function __construct(array<Node> $nodes)
    {
        $this->nodes = $nodes;
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->nodes[$offset]);
    }

    /**
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet(int $offset):Node
    {
        $item = $this->nodes[$offset];

        if (is_array($item) && isset($item['kind'])) {
            $this->nodes[$offset] = $item = AST::fromArray($item);
        }

        return $item;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        if (is_array($value) && isset($value['kind'])) {
            $value = AST::fromArray($value);
        }
        $this->nodes[$offset] = $value;
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset(int $offset):void
    {
        unset($this->nodes[$offset]);
    }

    /**
     * @param int $offset
     * @param int $length
     * @param mixed $replacement
     * @return NodeList
     */
    public function splice(int $offset,int $length, $replacement = null):NodeList
    {
        return new NodeList(array_splice(&$this->nodes, $offset, $length, $replacement));
    }

    /**
     * @param $list
     * @return NodeList
     */
    public function merge($list):NodeList
    {
        if ($list instanceof NodeList) {
            $list = $list->nodes;
        }
        return new NodeList(array_merge($this->nodes, $list));
    }

    /**
     * @return \Generator
     */
    public function getIterator()
    {
        $count = count($this->nodes);
        for ($i = 0; $i < $count; $i++) {
            yield $this->offsetGet($i);
        }
    }

    /**
     * @return int
     */
    public function count():int
    {
        return count($this->nodes);
    }
}
