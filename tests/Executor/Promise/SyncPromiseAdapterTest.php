<?hh //strict
//decl
namespace GraphQL\Tests\Executor\Promise;

use GraphQL\Deferred;
use function Facebook\FBExpect\expect;
use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\Promise\Adapter\SyncPromise;
use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Executor\Promise\Promise;

class SyncPromiseAdapterTest extends \Facebook\HackTest\HackTest
{
    /**
     * @var SyncPromiseAdapter
     */
    private $promises;

    public async function beforeEachTestAsync(): Awaitable<void>
    {
        $this->promises = new SyncPromiseAdapter();
    }

    public function testIsThenable():void
    {
        expect($this->promises->isThenable(new Deferred(function() {})))->toBePHPEqual(true);
        expect($this->promises->isThenable(false))->toBePHPEqual(false);
        expect($this->promises->isThenable(true))->toBePHPEqual(false);
        expect($this->promises->isThenable(1))->toBePHPEqual(false);
        expect($this->promises->isThenable(0))->toBePHPEqual(false);
        expect($this->promises->isThenable('test'))->toBePHPEqual(false);
        expect($this->promises->isThenable(''))->toBePHPEqual(false);
        expect($this->promises->isThenable([]))->toBePHPEqual(false);
        expect($this->promises->isThenable(new \stdClass()))->toBePHPEqual(false);
    }

    public function testConvert():void
    {
        $dfd = new Deferred(function() {});
        $result = $this->promises->convertThenable($dfd);

        expect($result)->toBeInstanceOf('GraphQL\Executor\Promise\Promise');
        expect($result->adoptedPromise)->toBeInstanceOf('GraphQL\Executor\Promise\Adapter\SyncPromise');

        $this->setExpectedException(InvariantViolation::class, 'Expected instance of GraphQL\Deferred, got (empty string)');
        $this->promises->convertThenable('');
    }

    public function testThen():void
    {
        $dfd = new Deferred(function() {});
        $promise = $this->promises->convertThenable($dfd);

        $result = $this->promises->then($promise);

        expect($result)->toBeInstanceOf('GraphQL\Executor\Promise\Promise');
        expect($result->adoptedPromise)->toBeInstanceOf('GraphQL\Executor\Promise\Adapter\SyncPromise');
    }

    public function testCreatePromise():void
    {
        $promise = $this->promises->create(function($resolve, $reject) {});

        expect($promise)->toBeInstanceOf('GraphQL\Executor\Promise\Promise');
        expect($promise->adoptedPromise)->toBeInstanceOf('GraphQL\Executor\Promise\Adapter\SyncPromise');

        $promise = $this->promises->create(function($resolve, $reject) {
            $resolve('A');
        });

        $this->assertValidPromise($promise, null, 'A', SyncPromise::FULFILLED);
    }

    public function testCreateFulfilledPromise():void
    {
        $promise = $this->promises->createFulfilled('test');
        $this->assertValidPromise($promise, null, 'test', SyncPromise::FULFILLED);
    }

    public function testCreateRejectedPromise():void
    {
        $promise = $this->promises->createRejected(new \Exception('test reason'));
        $this->assertValidPromise($promise, 'test reason', null, SyncPromise::REJECTED);
    }

    public function testCreatePromiseAll():void
    {
        $promise = $this->promises->all([]);
        $this->assertValidPromise($promise, null, [], SyncPromise::FULFILLED);

        $promise = $this->promises->all(['1']);
        $this->assertValidPromise($promise, null, ['1'], SyncPromise::FULFILLED);

        $promise1 = new SyncPromise();
        $promise2 = new SyncPromise();
        $promise3 = $promise2->then(
            function($value) {
                return $value .'-value3';
            }
        );

        $data = [
            '1',
            new Promise($promise1, $this->promises),
            new Promise($promise2, $this->promises),
            3,
            new Promise($promise3, $this->promises),
            []
        ];

        $promise = $this->promises->all($data);
        $this->assertValidPromise($promise, null, null, SyncPromise::PENDING);

        $promise1->resolve('value1');
        $this->assertValidPromise($promise, null, null, SyncPromise::PENDING);
        $promise2->resolve('value2');
        $this->assertValidPromise($promise, null, ['1', 'value1', 'value2', 3, 'value2-value3', []], SyncPromise::FULFILLED);
    }

    public function testWait():void
    {
        $called = [];

        $deferred1 = new Deferred(function() use (&$called) {
            $called[] = 1;
            return 1;
        });
        $deferred2 = new Deferred(function() use (&$called) {
            $called[] = 2;
            return 2;
        });

        $p1 = $this->promises->convertThenable($deferred1);
        $p2 = $this->promises->convertThenable($deferred2);

        $p3 = $p2->then(function() use (&$called) {
            $dfd = new Deferred(function() use (&$called) {
                $called[] = 3;
                return 3;
            });
            return $this->promises->convertThenable($dfd);
        });

        $p4 = $p3->then(function() use (&$called) {
            return new Deferred(function() use (&$called) {
                $called[] = 4;
                return 4;
            });
        });

        $all = $this->promises->all([0, $p1, $p2, $p3, $p4]);

        $result = $this->promises->wait($p2);
        expect($result)->toBePHPEqual(2);
        expect($p3->adoptedPromise->state)->toBePHPEqual(SyncPromise::PENDING);
        expect($all->adoptedPromise->state)->toBePHPEqual(SyncPromise::PENDING);
        expect($called)->toBePHPEqual([1, 2]);

        $expectedResult = [0,1,2,3,4];
        $result = $this->promises->wait($all);
        expect($result)->toBePHPEqual($expectedResult);
        expect($called)->toBePHPEqual([1, 2, 3, 4]);
        $this->assertValidPromise($all, null, [0,1,2,3,4], SyncPromise::FULFILLED);
    }

    private function assertValidPromise($promise, $expectedNextReason, $expectedNextValue, $expectedNextState)
    {
        expect($promise)->toBeInstanceOf('GraphQL\Executor\Promise\Promise');
        expect($promise->adoptedPromise)->toBeInstanceOf('GraphQL\Executor\Promise\Adapter\SyncPromise');

        $actualNextValue = null;
        $actualNextReason = null;
        $onFulfilledCalled = false;
        $onRejectedCalled = false;

        $promise->then(
            function($nextValue) use (&$actualNextValue, &$onFulfilledCalled) {
                $onFulfilledCalled = true;
                $actualNextValue = $nextValue;
            },
            function(\Exception $reason) use (&$actualNextReason, &$onRejectedCalled) {
                $onRejectedCalled = true;
                $actualNextReason = $reason->getMessage();
            }
        );

        expect(false)->toBeSame($onFulfilledCalled);
        expect(false)->toBeSame($onRejectedCalled);

        SyncPromise::runQueue();

        if ($expectedNextState !== SyncPromise::PENDING) {
            expect($onFulfilledCalled)->toBeSame(!$expectedNextReason);
            expect($onRejectedCalled)->toBeSame(!!$expectedNextReason);
        }

        expect($actualNextValue)->toBeSame($expectedNextValue);
        expect($actualNextReason)->toBeSame($expectedNextReason);
        expect($promise->adoptedPromise->state)->toBeSame($expectedNextState);
    }
}
