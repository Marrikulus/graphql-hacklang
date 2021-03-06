<?hh //strict
//decl
namespace GraphQL\Tests\Validator;

use GraphQL\Validator\Rules\UniqueDirectivesPerLocation;

class UniqueDirectivesPerLocationTest extends TestCase
{
    /**
     * @it no directives
     */
    public function testNoDirectives():void
    {
        $this->expectPassesRule(new UniqueDirectivesPerLocation(), '
      fragment Test on Type {
        field
      }
        ');
    }

    /**
     * @it unique directives in different locations
     */
    public function testUniqueDirectivesInDifferentLocations():void
    {
        $this->expectPassesRule(new UniqueDirectivesPerLocation(), '
      fragment Test on Type @directiveA {
        field @directiveB
      }
        ');
    }

    /**
     * @it unique directives in same locations
     */
    public function testUniqueDirectivesInSameLocations():void
    {
        $this->expectPassesRule(new UniqueDirectivesPerLocation(), '
      fragment Test on Type @directiveA @directiveB {
        field @directiveA @directiveB
      }
        ');
    }

    /**
     * @it same directives in different locations
     */
    public function testSameDirectivesInDifferentLocations():void
    {
        $this->expectPassesRule(new UniqueDirectivesPerLocation(), '
      fragment Test on Type @directiveA {
        field @directiveA
      }
        ');
    }

    /**
     * @it same directives in similar locations
     */
    public function testSameDirectivesInSimilarLocations():void
    {
        $this->expectPassesRule(new UniqueDirectivesPerLocation(), '
      fragment Test on Type {
        field @directive
        field @directive
      }
        ');
    }

    /**
     * @it duplicate directives in one location
     */
    public function testDuplicateDirectivesInOneLocation():void
    {
        $this->expectFailsRule(new UniqueDirectivesPerLocation(), '
      fragment Test on Type {
        field @directive @directive
      }
        ', [
            $this->duplicateDirective('directive', 3, 15, 3, 26)
        ]);
    }

    /**
     * @it many duplicate directives in one location
     */
    public function testManyDuplicateDirectivesInOneLocation():void
    {
        $this->expectFailsRule(new UniqueDirectivesPerLocation(), '
      fragment Test on Type {
        field @directive @directive @directive
      }
        ', [
            $this->duplicateDirective('directive', 3, 15, 3, 26),
            $this->duplicateDirective('directive', 3, 15, 3, 37)
        ]);
    }

    /**
     * @it different duplicate directives in one location
     */
    public function testDifferentDuplicateDirectivesInOneLocation():void
    {
        $this->expectFailsRule(new UniqueDirectivesPerLocation(), '
      fragment Test on Type {
        field @directiveA @directiveB @directiveA @directiveB
      }
        ', [
            $this->duplicateDirective('directiveA', 3, 15, 3, 39),
            $this->duplicateDirective('directiveB', 3, 27, 3, 51)
        ]);
    }

    /**
     * @it duplicate directives in many locations
     */
    public function testDuplicateDirectivesInManyLocations():void
    {
        $this->expectFailsRule(new UniqueDirectivesPerLocation(), '
      fragment Test on Type @directive @directive {
        field @directive @directive
      }
        ', [
            $this->duplicateDirective('directive', 2, 29, 2, 40),
            $this->duplicateDirective('directive', 3, 15, 3, 26)
        ]);
    }

    private function duplicateDirective(string $directiveName, int $l1, int $c1, int $l2, int $c2):array<string, mixed>
    {
        return [
            'message' =>UniqueDirectivesPerLocation::duplicateDirectiveMessage($directiveName),
            'locations' => [
                ['line' => $l1, 'column' => $c1],
                ['line' => $l2, 'column' => $c2]
            ]
        ];
    }
}
