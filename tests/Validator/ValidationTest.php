<?hh //strict
//decl
namespace GraphQL\Tests\Validator;

use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\QueryComplexity;

class ValidationTest extends TestCase
{
    // Validate: Supports full validation

    /**
     * @it validates queries
     */
    public function testValidatesQueries():void
    {
        $this->expectPassesCompleteValidation('
          query {
            catOrDog {
              ... on Cat {
                furColor
              }
              ... on Dog {
                isHousetrained
              }
            }
          }
        ');
    }
/*
    public function testAllowsSettingRulesGlobally():void
    {
        $rule = new QueryComplexity(0);

        DocumentValidator::addRule($rule);
        $instance = DocumentValidator::getRule(QueryComplexity::class);
        $this->assertSame($rule, $instance);
    }
*/
    public function testPassesValidationWithEmptyRules():void
    {
        $query = '{invalid}';

        $expectedError = [
            'message' => 'Cannot query field "invalid" on type "QueryRoot".',
            'locations' => [ ['line' => 1, 'column' => 2] ]
        ];
        $this->expectFailsCompleteValidation($query, [$expectedError]);
        $this->expectValid(TestCase::getDefaultSchema(), [], $query);
    }
}
