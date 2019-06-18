<?hh //strict
namespace GraphQL\Language\AST;

class DocumentNode extends Node
{
    public string $kind = NodeKind::DOCUMENT;

    public function __construct(
        public array<Node> $definitions,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
