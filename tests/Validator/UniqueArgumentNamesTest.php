<?hh //strict
//decl
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\UniqueArgumentNames;

class UniqueArgumentNamesTest extends TestCase
{
    // Validate: Unique argument names

    /**
     * @it no arguments on field
     */
    public function testNoArgumentsOnField():void
    {
        $this->expectPassesRule(new UniqueArgumentNames(), '
      {
        field
      }
        ');
    }

    /**
     * @it no arguments on directive
     */
    public function testNoArgumentsOnDirective():void
    {
        $this->expectPassesRule(new UniqueArgumentNames(), '
      {
        field @directive
      }
        ');
    }

    /**
     * @it argument on field
     */
    public function testArgumentOnField():void
    {
        $this->expectPassesRule(new UniqueArgumentNames(), '
      {
        field(arg: "value")
      }
        ');
    }

    /**
     * @it argument on directive
     */
    public function testArgumentOnDirective():void
    {
        $this->expectPassesRule(new UniqueArgumentNames(), '
      {
        field @directive(arg: "value")
      }
        ');
    }

    /**
     * @it same argument on two fields
     */
    public function testSameArgumentOnTwoFields():void
    {
        $this->expectPassesRule(new UniqueArgumentNames(), '
      {
        one: field(arg: "value")
        two: field(arg: "value")
      }
        ');
    }

    /**
     * @it same argument on field and directive
     */
    public function testSameArgumentOnFieldAndDirective():void
    {
        $this->expectPassesRule(new UniqueArgumentNames(), '
      {
        field(arg: "value") @directive(arg: "value")
      }
        ');
    }

    /**
     * @it same argument on two directives
     */
    public function testSameArgumentOnTwoDirectives():void
    {
        $this->expectPassesRule(new UniqueArgumentNames(), '
      {
        field @directive1(arg: "value") @directive2(arg: "value")
      }
        ');
    }

    /**
     * @it multiple field arguments
     */
    public function testMultipleFieldArguments():void
    {
        $this->expectPassesRule(new UniqueArgumentNames(), '
      {
        field(arg1: "value", arg2: "value", arg3: "value")
      }
        ');
    }

    /**
     * @it multiple directive arguments
     */
    public function testMultipleDirectiveArguments():void
    {
        $this->expectPassesRule(new UniqueArgumentNames(), '
      {
        field @directive(arg1: "value", arg2: "value", arg3: "value")
      }
        ');
    }

    /**
     * @it duplicate field arguments
     */
    public function testDuplicateFieldArguments():void
    {
        $this->expectFailsRule(new UniqueArgumentNames(), '
      {
        field(arg1: "value", arg1: "value")
      }
        ', [
            $this->duplicateArg('arg1', 3, 15, 3, 30)
        ]);
    }

    /**
     * @it many duplicate field arguments
     */
    public function testManyDuplicateFieldArguments():void
    {
        $this->expectFailsRule(new UniqueArgumentNames(), '
      {
        field(arg1: "value", arg1: "value", arg1: "value")
      }
        ', [
            $this->duplicateArg('arg1', 3, 15, 3, 30),
            $this->duplicateArg('arg1', 3, 15, 3, 45)
        ]);
    }

    /**
     * @it duplicate directive arguments
     */
    public function testDuplicateDirectiveArguments():void
    {
        $this->expectFailsRule(new UniqueArgumentNames(), '
      {
        field @directive(arg1: "value", arg1: "value")
      }
        ', [
            $this->duplicateArg('arg1', 3, 26, 3, 41)
        ]);
    }

    /**
     * @it many duplicate directive arguments
     */
    public function testManyDuplicateDirectiveArguments():void
    {
        $this->expectFailsRule(new UniqueArgumentNames(), '
      {
        field @directive(arg1: "value", arg1: "value", arg1: "value")
      }
        ', [
            $this->duplicateArg('arg1', 3, 26, 3, 41),
            $this->duplicateArg('arg1', 3, 26, 3, 56)
        ]);
    }

    private function duplicateArg($argName, $l1, $c1, $l2, $c2)
    {
        return FormattedError::create(
            UniqueArgumentNames::duplicateArgMessage($argName),
            [new SourceLocation($l1, $c1), new SourceLocation($l2, $c2)]
        );
    }
}
