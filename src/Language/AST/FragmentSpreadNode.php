<?hh
namespace GraphQL\Language\AST;

class FragmentSpreadNode extends Node implements SelectionNode
{
    public string $kind = NodeKind::FRAGMENT_SPREAD;

    public function __construct(
        public NameNode $name,
        public NodeList $directives,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
