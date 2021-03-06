<?hh //strict
//decl
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use function Facebook\FBExpect\expect;
use GraphQL\Error\Error;
use GraphQL\Language\SourceLocation;
use GraphQL\Language\Parser;
use GraphQL\Type\Introspection;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\AbstractQuerySecurity;

abstract class AbstractQuerySecurityTest extends \Facebook\HackTest\HackTest
{
    /**
     * @param $max
     *
     * @return AbstractQuerySecurity
     */
    abstract protected function getRule(int $max):AbstractQuerySecurityTest;

    /**
     * @param $max
     * @param $count
     *
     * @return string
     */
    abstract protected function getErrorMessage(int $max, int $count):string;

    public function testMaxQueryDepthMustBeGreaterOrEqualTo0():void
    {
        expect(() ==> {
            $this->getRule(-1);
        })->toThrow(\InvalidArgumentException::class);
    }

    protected function createFormattedError(int $max, int $count, array<SourceLocation> $locations = []):array<string, mixed>
    {
        return FormattedError::create($this->getErrorMessage($max, $count), $locations);
    }

    protected function assertDocumentValidator(string $queryString, int $max, array<array<string,mixed>> $expectedErrors = []):array<Error>
    {
        $errors = DocumentValidator::validate(
            QuerySecuritySchema::buildSchema(),
            Parser::parse($queryString),
            [$this->getRule($max)]
        );
        $gotErrors = \array_map(class_meth(Error::class, 'formatError'), $errors);
        expect($gotErrors)->toBePHPEqual($expectedErrors, $queryString);

        return $errors;
    }

    protected function assertIntrospectionQuery(int $maxExpected):void
    {
        $query = Introspection::getIntrospectionQuery(true);

        $this->assertMaxValue($query, $maxExpected);
    }

    protected function assertIntrospectionTypeMetaFieldQuery(int $maxExpected):void
    {
        $query = '
          {
            __type(name: "Human") {
              name
            }
          }
        ';

        $this->assertMaxValue($query, $maxExpected);
    }

    protected function assertTypeNameMetaFieldQuery(int $maxExpected):void
    {
        $query = '
          {
            human {
              __typename
              firstName
            }
          }
        ';
        $this->assertMaxValue($query, $maxExpected);
    }

    protected function assertMaxValue(string $query, int $maxExpected):void
    {
        $this->assertDocumentValidator($query, $maxExpected);
        $newMax = $maxExpected - 1;
        if ($newMax !== AbstractQuerySecurity::DISABLED) {
            $this->assertDocumentValidator($query, $newMax, [$this->createFormattedError($newMax, $maxExpected)]);
        }
    }
}
