<?hh //strict
//decl
namespace GraphQL\Tests\Type;

class ObjectIdStub
{
    /**
     * @var int
     */
    private int $id;

    /**
     * @param int $id
     */
    public function __construct(int $id)
    {
        $this->id = $id;
    }

    public function __toString():string
    {
        return (string) $this->id;
    }
}
