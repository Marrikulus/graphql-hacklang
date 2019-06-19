<?hh //strict
namespace GraphQL\Language\AST;

class DocumentNode extends Node
{
    public function __construct(
        public array<Node> $definitions,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::DOCUMENT);
    }
}
