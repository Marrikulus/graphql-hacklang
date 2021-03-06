<?hh //strict
//decl
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\UniqueVariableNames;

class UniqueVariableNamesTest extends TestCase
{
    // Validate: Unique variable names

    /**
     * @it unique variable names
     */
    public function testUniqueVariableNames():void
    {
        $this->expectPassesRule(new UniqueVariableNames(), '
      query A($x: Int, $y: String) { __typename }
      query B($x: String, $y: Int) { __typename }
        ');
    }

    /**
     * @it duplicate variable names
     */
    public function testDuplicateVariableNames():void
    {
        $this->expectFailsRule(new UniqueVariableNames(), '
      query A($x: Int, $x: Int, $x: String) { __typename }
      query B($x: String, $x: Int) { __typename }
      query C($x: Int, $x: Int) { __typename }
        ', [
            $this->duplicateVariable('x', 2, 16, 2, 25),
            $this->duplicateVariable('x', 2, 16, 2, 34),
            $this->duplicateVariable('x', 3, 16, 3, 28),
            $this->duplicateVariable('x', 4, 16, 4, 25)
        ]);
    }

    private function duplicateVariable(string $name, int $l1, int $c1, int $l2, int $c2):array<string, mixed>
    {
        return FormattedError::create(
            UniqueVariableNames::duplicateVariableMessage($name),
            [new SourceLocation($l1, $c1), new SourceLocation($l2, $c2)]
        );
    }
}
