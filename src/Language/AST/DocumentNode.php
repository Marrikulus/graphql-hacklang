<?hh //strict
namespace GraphQL\Language\AST;

class DocumentNode extends Node
{
    public string $kind = NodeKind::DOCUMENT;

    public function __construct(
        public NodeList $definitions,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
