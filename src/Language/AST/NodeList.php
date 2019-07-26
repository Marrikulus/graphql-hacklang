<?hh //strict
namespace GraphQL\Language\AST;

use GraphQL\Utils\AST;
use Countable;
use IteratorAggregate;
use ArrayAccess;

/**
 * Class NodeList
 *
 * @package GraphQL\Utils
 */
final class NodeList extends Node implements Countable//, IteratorAggregate<Node>
{
    /**
     * @param array $nodes
     * @return static
     */
    public static function create(array<Node> $nodes):NodeList
    {
        return new NodeList($nodes);
    }

    /**
     * NodeList constructor.
     * @param array $nodes
     */
    <<__Override>>
    public function __construct(
        private array<Node> $nodes
    ){
         parent::__construct(null, 'list');
    }

    <<__Override>>
    public function isList(): bool {
        return true;
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
    /*public function getIterator():Iterator<Node>
    {
        $count = \count($this->nodes);
        for ($i = 0; $i < $count; $i++) {
            yield $this->offsetGet($i);
        }
    }*/

    /**
     * @return int
     */
    public function count():int
    {
        return \count($this->nodes);
    }
}
