<?hh //strict
//decl
namespace GraphQL\Validator\Rules;


use GraphQL\Error\Error;
use GraphQL\Validator\ValidationContext;

type VisitorFn = (function(ValidationContext):array);

class CustomValidationRule extends AbstractValidationRule
{
    private VisitorFn $visitorFn;

    public function __construct(string $name, VisitorFn $visitorFn)
    {
        $this->name = $name;
        $this->visitorFn = $visitorFn;
    }

    /**
     * @param ValidationContext $context
     * @return Error[]
     */
    public function getVisitor(ValidationContext $context)
    {
        $fn = $this->visitorFn;
        return $fn($context);
    }
}
