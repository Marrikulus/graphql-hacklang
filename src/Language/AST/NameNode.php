<?hh
namespace GraphQL\Language\AST;

class NameNode extends Node implements TypeNode
{
    public string $kind = NodeKind::NAME;

    public function __construct(
        public ?string $value,
        ?Location $loc)
    {
        parent::__construct($loc);
    }
}
