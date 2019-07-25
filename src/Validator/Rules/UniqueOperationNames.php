<?hh //partial
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Validator\ValidationContext;


class UniqueOperationNames extends AbstractValidationRule
{
    public static function duplicateOperationNameMessage(string $operationName): @string
    {
      return "There can be only one operation named \"$operationName\".";
    }

    public array<string, NameNode> $knownOperationNames = [];

    public function getVisitor(ValidationContext $context):array
    {
        $this->knownOperationNames = [];

        return [
            NodeKind::OPERATION_DEFINITION => function(OperationDefinitionNode $node) use ($context) {
                $operationName = $node->name;

                if ($operationName)
                {
                    if (\array_key_exists($operationName->value, $this->knownOperationNames))
                    {
                        $context->reportError(new Error(
                            self::duplicateOperationNameMessage($operationName->value),
                            [ $this->knownOperationNames[$operationName->value], $operationName ]
                        ));
                    } else {
                        $this->knownOperationNames[$operationName->value] = $operationName;
                    }
                }
                return Visitor::skipNode();
            },
            NodeKind::FRAGMENT_DEFINITION => function(Node $ble)
            {
                return Visitor::skipNode();
            }
        ];
    }
}
