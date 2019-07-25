<?hh //strict
//decl


namespace GraphQL\Tests\Executor\Promise;


use GraphQL\Executor\Promise\Adapter\ReactPromiseAdapter;
use function Facebook\FBExpect\expect;
use GraphQL\Executor\Promise\Promise;
use React\Promise\Deferred;
use React\Promise\FulfilledPromise;
use React\Promise\LazyPromise;
use React\Promise\Promise as ReactPromise;
use React\Promise\RejectedPromise;

/**
 * @group ReactPromise
 */
class ReactPromiseAdapterTest extends \Facebook\HackTest\HackTest
{
    // public async function beforeEachTestAsync(): Awaitable<void>
    // {
    //     if(! \class_exists('React\Promise\Promise')) {
    //         static::markTestSkipped('react/promise package must be installed to run GraphQL\Tests\Executor\Promise\ReactPromiseAdapterTest');
    //     }
    // }

    // public function testIsThenableReturnsTrueWhenAReactPromiseIsGiven():void
    // {
    //     $reactAdapter = new ReactPromiseAdapter();

    //     expect($reactAdapter->isThenable(new ReactPromise(function() {})))->toBeSame(true);
    //     expect($reactAdapter->isThenable(new FulfilledPromise()))->toBeSame(true);
    //     expect($reactAdapter->isThenable(new RejectedPromise()))->toBeSame(true);
    //     expect($reactAdapter->isThenable(new LazyPromise(function() {})))->toBeSame(true);
    //     expect($reactAdapter->isThenable(false))->toBeSame(false);
    //     expect($reactAdapter->isThenable(true))->toBeSame(false);
    //     expect($reactAdapter->isThenable(1))->toBeSame(false);
    //     expect($reactAdapter->isThenable(0))->toBeSame(false);
    //     expect($reactAdapter->isThenable('test'))->toBeSame(false);
    //     expect($reactAdapter->isThenable(''))->toBeSame(false);
    //     expect($reactAdapter->isThenable([]))->toBeSame(false);
    //     expect($reactAdapter->isThenable(new \stdClass()))->toBeSame(false);
    // }

    // public function testConvertsReactPromisesToGraphQlOnes():void
    // {
    //     $reactAdapter = new ReactPromiseAdapter();
    //     $reactPromise = new FulfilledPromise(1);

    //     $promise = $reactAdapter->convertThenable($reactPromise);

    //     expect($promise)->toBeInstanceOf('GraphQL\Executor\Promise\Promise');
    //     expect($promise->adoptedPromise)->toBeInstanceOf('React\Promise\FulfilledPromise');
    // }

    // public function testThen():void
    // {
    //     $reactAdapter = new ReactPromiseAdapter();
    //     $reactPromise = new FulfilledPromise(1);
    //     $promise = $reactAdapter->convertThenable($reactPromise);

    //     $result = null;

    //     $resultPromise = $reactAdapter->then($promise, function ($value) use (&$result) {
    //         $result = $value;
    //     });

    //     expect($result)->toBeSame(1);
    //     expect($resultPromise)->toBeInstanceOf('GraphQL\Executor\Promise\Promise');
    //     expect($resultPromise->adoptedPromise)->toBeInstanceOf('React\Promise\FulfilledPromise');
    // }

    // public function testCreate():void
    // {
    //     $reactAdapter = new ReactPromiseAdapter();
    //     $resolvedPromise = $reactAdapter->create(function ($resolve) {
    //          $resolve(1);
    //     });

    //     expect($resolvedPromise)->toBeInstanceOf('GraphQL\Executor\Promise\Promise');
    //     expect($resolvedPromise->adoptedPromise)->toBeInstanceOf('React\Promise\Promise');

    //     $result = null;

    //     $resolvedPromise->then(function ($value) use (&$result) {
    //        $result = $value;
    //     });

    //     expect($result)->toBeSame(1);
    // }

    // public function testCreateFulfilled():void
    // {
    //     $reactAdapter = new ReactPromiseAdapter();
    //     $fulfilledPromise = $reactAdapter->createFulfilled(1);

    //     expect($fulfilledPromise)->toBeInstanceOf('GraphQL\Executor\Promise\Promise');
    //     expect($fulfilledPromise->adoptedPromise)->toBeInstanceOf('React\Promise\FulfilledPromise');

    //     $result = null;

    //     $fulfilledPromise->then(function ($value) use (&$result) {
    //         $result = $value;
    //     });

    //     expect($result)->toBeSame(1);
    // }

    // public function testCreateRejected():void
    // {
    //     $reactAdapter = new ReactPromiseAdapter();
    //     $rejectedPromise = $reactAdapter->createRejected(new \Exception('I am a bad promise'));

    //     expect($rejectedPromise)->toBeInstanceOf('GraphQL\Executor\Promise\Promise');
    //     expect($rejectedPromise->adoptedPromise)->toBeInstanceOf('React\Promise\RejectedPromise');

    //     $exception = null;

    //     $rejectedPromise->then(null, function ($error) use (&$exception) {
    //         $exception = $error;
    //     });

    //     expect($exception)->toBeInstanceOf('\Exception');
    //     expect($exception->getMessage())->toBePHPEqual('I am a bad promise');
    // }

    // public function testAll():void
    // {
    //     $reactAdapter = new ReactPromiseAdapter();
    //     $promises = [new FulfilledPromise(1), new FulfilledPromise(2), new FulfilledPromise(3)];

    //     $allPromise = $reactAdapter->all($promises);

    //     expect($allPromise)->toBeInstanceOf('GraphQL\Executor\Promise\Promise');
    //     expect($allPromise->adoptedPromise)->toBeInstanceOf('React\Promise\FulfilledPromise');

    //     $result = null;

    //     $allPromise->then(function ($values) use (&$result) {
    //        $result = $values;
    //     });

    //     expect($result)->toBeSame([1, 2, 3]);
    // }

    // public function testAllShouldPreserveTheOrderOfTheArrayWhenResolvingAsyncPromises():void
    // {
    //     $reactAdapter = new ReactPromiseAdapter();
    //     $deferred = new Deferred();
    //     $promises = [new FulfilledPromise(1), $deferred->promise(), new FulfilledPromise(3)];
    //     $result = null;

    //     $reactAdapter->all($promises)->then(function ($values) use (&$result) {
    //         $result = $values;
    //     });

    //     // Resolve the async promise
    //     $deferred->resolve(2);
    //     expect($result)->toBeSame([1, 2, 3]);
    // }
}
