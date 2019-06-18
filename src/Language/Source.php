<?hh //strict
//decl
namespace GraphQL\Language;

use GraphQL\Utils\Utils;

/**
 * Class Source
 * @package GraphQL\Language
 */
class Source
{
    /**
     * @var string
     */
    public string $body;

    /**
     * @var int
     */
    public int $length;

    /**
     * @var string
     */
    public string $name;

    /**
     * @var SourceLocation
     */
    public SourceLocation $locationOffset;

    /**
     * Source constructor.
     *
     * A representation of source input to GraphQL.
     * `name` and `locationOffset` are optional. They are useful for clients who
     * store GraphQL documents in source files; for example, if the GraphQL input
     * starts at line 40 in a file named Foo.graphql, it might be useful for name to
     * be "Foo.graphql" and location to be `{ line: 40, column: 0 }`.
     * line and column in locationOffset are 1-indexed
     *
     * @param $body
     * @param null $name
     * @param SourceLocation|null $location
     */
    public function __construct(string $body, ?string $name = null, ?SourceLocation $location = null):void
    {
        Utils::invariant(
            is_string($body),
            'GraphQL query body is expected to be string, but got ' . Utils::getVariableType($body)
        );

        $this->body = $body;
        $this->length = \mb_strlen($body, 'UTF-8');
        $this->name = $name !== null ? $name : 'GraphQL';
        $this->locationOffset = $location !== null ? $location : new SourceLocation(1, 1);

        Utils::invariant(
            $this->locationOffset->line > 0,
            'line in locationOffset is 1-indexed and must be positive'
        );
        Utils::invariant(
            $this->locationOffset->column > 0,
            'column in locationOffset is 1-indexed and must be positive'
        );
    }

    /**
     * @param $position
     * @return SourceLocation
     */
    public function getLocation(int $position):SourceLocation
    {
        $line = 1;
        $column = $position + 1;

        $utfChars = \json_decode('"\u2028\u2029"');
        $lineRegexp = '/\r\n|[\n\r'.$utfChars.']/su';
        $matches = [];
        \preg_match_all($lineRegexp, \mb_substr($this->body, 0, $position, 'UTF-8'), &$matches, \PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $index => $match) {
            $line += 1;
            $column = $position + 1 - ($match[1] + \mb_strlen($match[0], 'UTF-8'));
        }

        return new SourceLocation($line, $column);
    }
}
