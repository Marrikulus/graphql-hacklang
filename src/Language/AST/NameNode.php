<?hh //strict
namespace GraphQL\Language\AST;

class NameNode extends Node implements TypeNode
{
    public function __construct(
        public string $value,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::NAME);
    }
}
