<?hh //strict
namespace GraphQL\Language\AST;

class NamedTypeNode extends Node implements TypeNode
{
    public string $kind = NodeKind::NAMED_TYPE;

    public function __construct(
        public NameNode $name,
        ?Location $loc = null)
    {
        parent::__construct($loc);
    }
}
