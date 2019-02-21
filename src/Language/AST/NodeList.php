<?hh //strict
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
    private array<int, Node> $nodes;

    /**
     * @param array $nodes
     * @return static
     */
    public static function create(array<int, Node> $nodes):NodeList
    {
        return new NodeList($nodes);
    }

    /**
     * NodeList constructor.
     * @param array $nodes
     */
    public function __construct(array<int, Node> $nodes)
    {
        $this->nodes = $nodes;
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists(int $offset):bool
    {
        return \array_key_exists($offset, $this->nodes);
    }

    /**
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet(int $offset):Node
    {
        $item = $this->nodes[$offset];

        return $item;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet(int $offset, Node $value):void
    {
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
    /* HH_FIXME[4032]*/
    public function splice(int $offset,int $length, $replacement = null):NodeList
    {
        return new NodeList(\array_splice(&$this->nodes, $offset, $length, $replacement));
    }

    /**
     * @param $list
     * @return NodeList
     */
    public function merge(NodeList $list):NodeList
    {
        return new NodeList(\array_merge($this->nodes, $list->nodes));
    }

    /**
     * @return \Generator
     */
    public function getIterator():Iterator<Node>
    {
        $count = \count($this->nodes);
        for ($i = 0; $i < $count; $i++) {
            yield $this->offsetGet($i);
        }
    }

    /**
     * @return int
     */
    public function count():int
    {
        return \count($this->nodes);
    }
}
