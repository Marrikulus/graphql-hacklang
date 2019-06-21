<?hh //strict
//decl
namespace GraphQL\Tests\Executor;

use GraphQL\Executor\ExecutionResult;
use function Facebook\FBExpect\expect;

class ExecutionResultTest extends \Facebook\HackTest\HackTest
{
    public function testToArrayWithoutExtensions():void
    {
        $executionResult = new ExecutionResult();

        expect($executionResult->toArray())->toBePHPEqual([]);
    }

    public function testToArrayExtensions():void
    {
        $executionResult = new ExecutionResult(null, [], ['foo' => 'bar']);

        expect($executionResult->toArray())->toBePHPEqual(['extensions' => ['foo' => 'bar']]);

        $executionResult->extensions = ['bar' => 'foo'];

        expect($executionResult->toArray())->toBePHPEqual(['extensions' => ['bar' => 'foo']]);
    }
}
