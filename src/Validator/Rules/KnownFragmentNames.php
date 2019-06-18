<?hh //strict
//decl
namespace GraphQL\Validator\Rules;


use GraphQL\Error\Error;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Validator\ValidationContext;

class KnownFragmentNames extends AbstractValidationRule
{
    public static function unknownFragmentMessage($fragName)
   : @string {
        return "Unknown fragment \"$fragName\".";
    }

    public function getVisitor(ValidationContext $context): @array
    {
        return [
            NodeKind::FRAGMENT_SPREAD => function(FragmentSpreadNode $node) use ($context) {
                $fragmentName = $node->name->value;
                $fragment = $context->getFragment($fragmentName);
                if (!$fragment) {
                    $context->reportError(new Error(
                        self::unknownFragmentMessage($fragmentName),
                        [$node->name]
                    ));
                }
            }
        ];
    }
}
