<?hh //strict
//decl
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\UniqueOperationNames;

class UniqueOperationNamesTest extends TestCase
{
    // Validate: Unique operation names

    /**
     * @it no operations
     */
    public function testNoOperations():void
    {
        $this->expectPassesRule(new UniqueOperationNames(), '
      fragment fragA on Type {
        field
      }
        ');
    }

    /**
     * @it one anon operation
     */
    public function testOneAnonOperation():void
    {
        $this->expectPassesRule(new UniqueOperationNames(), '
      {
        field
      }
        ');
    }

    /**
     * @it one named operation
     */
    public function testOneNamedOperation():void
    {
        $this->expectPassesRule(new UniqueOperationNames(), '
      query Foo {
        field
      }
        ');
    }

    /**
     * @it multiple operations
     */
    public function testMultipleOperations():void
    {
        $this->expectPassesRule(new UniqueOperationNames(), '
      query Foo {
        field
      }

      query Bar {
        field
      }
        ');
    }

    /**
     * @it multiple operations of different types
     */
    public function testMultipleOperationsOfDifferentTypes():void
    {
        $this->expectPassesRule(new UniqueOperationNames(), '
      query Foo {
        field
      }

      mutation Bar {
        field
      }

      subscription Baz {
        field
      }
        ');
    }

    /**
     * @it fragment and operation named the same
     */
    public function testFragmentAndOperationNamedTheSame():void
    {
        $this->expectPassesRule(new UniqueOperationNames(), '
      query Foo {
        ...Foo
      }
      fragment Foo on Type {
        field
      }
        ');
    }

    /**
     * @it multiple operations of same name
     */
    public function testMultipleOperationsOfSameName():void
    {
        $this->expectFailsRule(new UniqueOperationNames(), '
      query Foo {
        fieldA
      }
      query Foo {
        fieldB
      }
        ', [
            $this->duplicateOp('Foo', 2, 13, 5, 13)
        ]);
    }

    /**
     * @it multiple ops of same name of different types (mutation)
     */
    public function testMultipleOpsOfSameNameOfDifferentTypes_Mutation():void
    {
        $this->expectFailsRule(new UniqueOperationNames(), '
      query Foo {
        fieldA
      }
      mutation Foo {
        fieldB
      }
        ', [
            $this->duplicateOp('Foo', 2, 13, 5, 16)
        ]);
    }

    /**
     * @it multiple ops of same name of different types (subscription)
     */
    public function testMultipleOpsOfSameNameOfDifferentTypes_Subscription():void
    {
        $this->expectFailsRule(new UniqueOperationNames(), '
      query Foo {
        fieldA
      }
      subscription Foo {
        fieldB
      }
        ', [
            $this->duplicateOp('Foo', 2, 13, 5, 20)
        ]);
    }

    private function duplicateOp(string $opName, int $l1, int $c1, int $l2, int $c2):array<string, mixed>
    {
        return FormattedError::create(
            UniqueOperationNames::duplicateOperationNameMessage($opName),
            [new SourceLocation($l1, $c1), new SourceLocation($l2, $c2)]
        );
    }
}
