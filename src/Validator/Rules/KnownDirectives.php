<?hh //partial
namespace GraphQL\Validator\Rules;


use GraphQL\Error\Error;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Validator\ValidationContext;
use GraphQL\Type\Definition\DirectiveLocation;

class KnownDirectives extends AbstractValidationRule
{
    public static function unknownDirectiveMessage(string $directiveName):string
    {
        return "Unknown directive \"$directiveName\".";
    }

    public static function misplacedDirectiveMessage(string $directiveName, $location):string
    {
        return "Directive \"$directiveName\" may not be used on \"$location\".";
    }

    /* HH_FIXME[4030]*/
    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::DIRECTIVE => function (DirectiveNode $node, $key, $parent, $path, $ancestors) use ($context)
            {
                $directiveDef = null;
                foreach ($context->getSchema()->getDirectives() as $def)
                {
                    if ($def->name === $node->name->value)
                    {
                        $directiveDef = $def;
                        break;
                    }
                }

                if (!$directiveDef)
                {
                    $context->reportError(new Error(
                        self::unknownDirectiveMessage($node->name->value),
                        [$node]
                    ));
                    return;
                }
                $appliedTo = $ancestors[\count($ancestors) - 1];
                $candidateLocation = $this->getLocationForAppliedNode($appliedTo);

                if (!$candidateLocation)
                {
                    $context->reportError(new Error(
                        self::misplacedDirectiveMessage($node->name->value, $appliedTo->type),
                        [$node]
                    ));
                }
                else if (!\in_array($candidateLocation, $directiveDef->locations))
                {
                    $context->reportError(new Error(
                        self::misplacedDirectiveMessage($node->name->value, $candidateLocation),
                        [ $node ]
                    ));
                }
            }
        ];
    }

    private function getLocationForAppliedNode(Node $appliedTo):?string
    {
        switch ($appliedTo->kind)
        {
            case NodeKind::OPERATION_DEFINITION:
            if ($appliedTo instanceof OperationDefinitionNode)
            {
                switch ($appliedTo->operation)
                {
                    case 'query': return DirectiveLocation::QUERY;
                    case 'mutation': return DirectiveLocation::MUTATION;
                    case 'subscription': return DirectiveLocation::SUBSCRIPTION;
                }
            }break;
            case NodeKind::FIELD: return DirectiveLocation::FIELD;
            case NodeKind::FRAGMENT_SPREAD: return DirectiveLocation::FRAGMENT_SPREAD;
            case NodeKind::INLINE_FRAGMENT: return DirectiveLocation::INLINE_FRAGMENT;
            case NodeKind::FRAGMENT_DEFINITION: return DirectiveLocation::FRAGMENT_DEFINITION;
        }

        return null;
    }
}
