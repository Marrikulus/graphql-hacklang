<?hh //strict
namespace GraphQL\Language\AST;

class FieldNode extends Node implements SelectionNode
{
    public string $kind = NodeKind::FIELD;

    public function __construct(
        public NameNode $name,
        public ?NameNode $alias,
        public NodeList $arguments,
        public NodeList $directives,
        public ?SelectionSetNode $selectionSet,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
