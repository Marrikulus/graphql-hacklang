<?hh //strict
namespace GraphQL\Language\AST;

class DirectiveNode extends Node
{
    public function __construct(
        public NameNode $name,
        public array<Node> $arguments,
        ?Location $loc = null)
    {
        parent::__construct($loc, NodeKind::DIRECTIVE);
    }
}
