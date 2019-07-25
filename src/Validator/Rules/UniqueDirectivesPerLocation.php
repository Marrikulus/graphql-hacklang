<?hh //partial
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\Node;
use GraphQL\Validator\ValidationContext;

class UniqueDirectivesPerLocation extends AbstractValidationRule
{
    public static function duplicateDirectiveMessage(string $directiveName):string
    {
        return 'The directive "'.$directiveName.'" can only be used once at this location.';
    }

    public function getVisitor(ValidationContext $context)
    {
        return [
            'enter' => function(Node $node) use ($context)
            {
                if ($node instanceof HasDirectives)
                {
                    $knownDirectives = [];
                    foreach ($node->getDirectives() as $directive) {
                        /** @var DirectiveNode $directive */
                        $directiveName = $directive->name->value;
                        if (isset($knownDirectives[$directiveName]))
                        {
                            $context->reportError(new Error(
                                self::duplicateDirectiveMessage($directiveName),
                                [$knownDirectives[$directiveName], $directive]
                            ));
                        }
                        else
                        {
                            $knownDirectives[$directiveName] = $directive;
                        }
                    }
                }
            }
        ];
    }
}
