<?hh
namespace GraphQL\Language\AST;

use GraphQL\Language\Source;
use GraphQL\Language\Token;

/**
 * Contains a range of UTF-8 character offsets and token references that
 * identify the region of the source from which the AST derived.
 */
class Location
{
    /**
     * The character offset at which this Node begins.
     *
     * @var int
     */
    public ?int $start;

    /**
     * The character offset at which this Node ends.
     *
     * @var int
     */
    public ?int $end;

    /**
     * The Token at which this Node begins.
     *
     * @var Token
     */
    public ?Token $startToken;

    /**
     * The Token at which this Node ends.
     *
     * @var Token
     */
    public ?Token $endToken;

    /**
     * The Source document the AST represents.
     *
     * @var Source|null
     */
    public ?Source $source;

    /**
     * @param $start
     * @param $end
     * @return static
     */
    public static function create(int $start, int $end):Location
    {
        $tmp = new Location();
        $tmp->start = $start;
        $tmp->end = $end;
        return $tmp;
    }

    public function __construct(?Token $startToken = null, ?Token $endToken = null, ?Source $source = null):void
    {
        $this->startToken = $startToken;
        $this->endToken = $endToken;
        $this->source = $source;

        if ($startToken && $endToken) {
            $this->start = $startToken->start;
            $this->end = $endToken->end;
        }
    }
}
