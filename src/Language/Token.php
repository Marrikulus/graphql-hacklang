<?hh //strict
namespace GraphQL\Language;

/**
 * Represents a range of characters represented by a lexical token
 * within a Source.
 */
class Token
{
    // Each kind of token.
    const SOF = '<SOF>';
    const EOF = '<EOF>';
    const BANG = '!';
    const DOLLAR = '$';
    const PAREN_L = '(';
    const PAREN_R = ')';
    const SPREAD = '...';
    const COLON = ':';
    const EQUALS = '=';
    const AT = '@';
    const BRACKET_L = '[';
    const BRACKET_R = ']';
    const BRACE_L = '{';
    const PIPE = '|';
    const BRACE_R = '}';
    const NAME = 'Name';
    const INT = 'Int';
    const FLOAT = 'Float';
    const STRING = 'String';
    const COMMENT = 'Comment';

    /**
     * The kind of Token (see one of constants above).
     *
     * @var string
     */
    public string $kind;

    /**
     * The character offset at which this Node begins.
     *
     * @var int
     */
    public int $start;

    /**
     * The character offset at which this Node ends.
     *
     * @var int
     */
    public int $end;

    /**
     * The 1-indexed line number on which this Token appears.
     *
     * @var int
     */
    public int $line;

    /**
     * The 1-indexed column number at which this Token begins.
     *
     * @var int
     */
    public int $column;

    /**
     * @var string|null
     */
    public ?string $value;

    /**
     * Tokens exist as nodes in a double-linked-list amongst all tokens
     * including ignored tokens. <SOF> is always the first node and <EOF>
     * the last.
     *
     * @var Token
     */
    public ?Token $prev;

    /**
     * @var Token
     */
    public ?Token $next;

    /**
     * Token constructor.
     * @param $kind
     * @param $start
     * @param $end
     * @param $line
     * @param $column
     * @param Token $previous
     * @param null $value
     */
    public function __construct(string $kind, int $start, int $end, int $line, int $column, ?Token $previous = null, ?string $value = null)
    {
        $this->kind = $kind;
        $this->start = (int) $start;
        $this->end = (int) $end;
        $this->line = (int) $line;
        $this->column = (int) $column;
        $this->prev = $previous;
        $this->next = null;
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getDescription():string
    {
        return $this->kind . ($this->value !== null ? ' "' . $this->value  . '"' : '');
    }

    /**
     * @return array
     */
    public function toArray():array<string, mixed>
    {
        return [
            'kind' => $this->kind,
            'value' => $this->value,
            'line' => $this->line,
            'column' => $this->column
        ];
    }
}
