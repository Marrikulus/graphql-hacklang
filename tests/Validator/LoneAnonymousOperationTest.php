<?hh //strict
//decl
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\LoneAnonymousOperation;

class LoneAnonymousOperationTest extends TestCase
{
    // Validate: Anonymous operation must be alone

    /**
     * @it no operations
     */
    public function testNoOperations():void
    {
        $this->expectPassesRule(new LoneAnonymousOperation(), '
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
        $this->expectPassesRule(new LoneAnonymousOperation(), '
      {
        field
      }
        ');
    }

    /**
     * @it multiple named operations
     */
    public function testMultipleNamedOperations():void
    {
        $this->expectPassesRule(new LoneAnonymousOperation(), '
      query Foo {
        field
      }

      query Bar {
        field
      }
        ');
    }

    /**
     * @it anon operation with fragment
     */
    public function testAnonOperationWithFragment():void
    {
        $this->expectPassesRule(new LoneAnonymousOperation(), '
      {
        ...Foo
      }
      fragment Foo on Type {
        field
      }
        ');
    }

    /**
     * @it multiple anon operations
     */
    public function testMultipleAnonOperations():void
    {
        $this->expectFailsRule(new LoneAnonymousOperation(), '
      {
        fieldA
      }
      {
        fieldB
      }
        ', [
            $this->anonNotAlone(2, 7),
            $this->anonNotAlone(5, 7)
        ]);
    }

    /**
     * @it anon operation with a mutation
     */
    public function testAnonOperationWithMutation():void
    {
        $this->expectFailsRule(new LoneAnonymousOperation(), '
      {
        fieldA
      }
      mutation Foo {
        fieldB
      }
        ', [
            $this->anonNotAlone(2, 7)
        ]);
    }

    /**
     * @it anon operation with a subscription
     */
    public function testAnonOperationWithSubscription():void
    {
        $this->expectFailsRule(new LoneAnonymousOperation(), '
      {
        fieldA
      }
      subscription Foo {
        fieldB
      }
        ', [
            $this->anonNotAlone(2, 7)
        ]);
    }

    private function anonNotAlone(int $line, int $column):array<string, mixed>
    {
        return FormattedError::create(
            LoneAnonymousOperation::anonOperationNotAloneMessage(),
            [new SourceLocation($line, $column)]
        );

    }
}