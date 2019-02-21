<?hh //strict
namespace GraphQL\Language;

class SourceLocation implements \JsonSerializable
{
    public int $line;
    public int $column;

    public function __construct(int $line, int $col)
    {
        $this->line = $line;
        $this->column = $col;
    }

    /**
     * @return array
     */
    public function toArray():array<string, int>
    {
        return [
            'line' => $this->line,
            'column' => $this->column
        ];
    }

    /**
     * @return array
     */
    public function toSerializableArray():array<string, int>
    {
        return $this->toArray();
    }

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize():array<string, int>
    {
        return $this->toSerializableArray();
    }
}
