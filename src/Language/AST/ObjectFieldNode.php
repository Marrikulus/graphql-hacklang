<?hh //strict
namespace GraphQL\Language\AST;

class ObjectFieldNode extends Node
{
    public string $kind = NodeKind::OBJECT_FIELD;

    public function __construct(
        public NameNode $name,
        public Node $value,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
