<?hh //strict
namespace GraphQL\Language\AST;

class NamedTypeNode extends Node implements TypeNode
{
    public function __construct(
        public NameNode $name,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::NAMED_TYPE);
    }
}
