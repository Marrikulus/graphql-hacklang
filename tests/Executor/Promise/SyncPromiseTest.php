<?hh //strict
//decl
namespace GraphQL\Tests\Executor\Promise;

use GraphQL\Executor\Promise\Adapter\SyncPromise;
use function Facebook\FBExpect\expect;

class SyncPromiseTest extends \Facebook\HackTest\HackTest
{
    public function getFulfilledPromiseResolveData()
    {
        $onFulfilledReturnsNull = function() {
            return null;
        };
        $onFulfilledReturnsSameValue = function($value) {
            return $value;
        };
        $onFulfilledReturnsOtherValue = function($value) {
            return 'other-' . $value;
        };
        $onFulfilledThrows = function($value) {
            throw new \Exception("onFulfilled throws this!");
        };

        return [
            // $resolvedValue, $onFulfilled, $expectedNextValue, $expectedNextReason, $expectedNextState
            ['test-value', null, 'test-value', null, SyncPromise::FULFILLED],
            [\uniqid(), $onFulfilledReturnsNull, null, null, SyncPromise::FULFILLED],
            ['test-value', $onFulfilledReturnsSameValue, 'test-value', null, SyncPromise::FULFILLED],
            ['test-value-2', $onFulfilledReturnsOtherValue, 'other-test-value-2', null, SyncPromise::FULFILLED],
            ['test-value-3', $onFulfilledThrows, null, "onFulfilled throws this!", SyncPromise::REJECTED],
        ];
    }

    <<DataProvider('getFulfilledPromiseResolveData')>>
    public function testFulfilledPromiseCannotChangeValue(
        $resolvedValue,
        $onFulfilled,
        $expectedNextValue,
        $expectedNextReason,
        $expectedNextState
    )
    {
        $promise = new SyncPromise();
        expect($promise->state)->toBePHPEqual(SyncPromise::PENDING);

        $promise->resolve($resolvedValue);
        expect($promise->state)->toBePHPEqual(SyncPromise::FULFILLED);

        $this->setExpectedException(\Exception::class, 'Cannot change value of fulfilled promise');
        $promise->resolve($resolvedValue . '-other-value');
    }

    <<DataProvider('getFulfilledPromiseResolveData')>>
    public function testFulfilledPromiseCannotBeRejected(
        $resolvedValue,
        $onFulfilled,
        $expectedNextValue,
        $expectedNextReason,
        $expectedNextState
    )
    {
        $promise = new SyncPromise();
        expect($promise->state)->toBePHPEqual(SyncPromise::PENDING);

        $promise->resolve($resolvedValue);
        expect($promise->state)->toBePHPEqual(SyncPromise::FULFILLED);

        $this->setExpectedException(\Exception::class, 'Cannot reject fulfilled promise');
        $promise->reject(new \Exception('anything'));
    }

    <<DataProvider('getFulfilledPromiseResolveData')>>
    public function testFulfilledPromise(
        $resolvedValue,
        $onFulfilled,
        $expectedNextValue,
        $expectedNextReason,
        $expectedNextState
    )
    {
        $promise = new SyncPromise();
        expect($promise->state)->toBePHPEqual(SyncPromise::PENDING);

        $promise->resolve($resolvedValue);
        expect($promise->state)->toBePHPEqual(SyncPromise::FULFILLED);

        $nextPromise = $promise->then(null, function() {});
        expect($nextPromise)->toBeSame($promise);

        $onRejectedCalled = false;
        $nextPromise = $promise->then($onFulfilled, function () use (&$onRejectedCalled) {
            $onRejectedCalled = true;
        });

        if ($onFulfilled) {
            expect($nextPromise)->toNotBeSame($promise);
            expect($nextPromise->state)->toBePHPEqual(SyncPromise::PENDING);
        } else {
            expect($nextPromise->state)->toBePHPEqual(SyncPromise::FULFILLED);
        }
        expect($onRejectedCalled)->toBePHPEqual(false);

        $this->assertValidPromise($nextPromise, $expectedNextReason, $expectedNextValue, $expectedNextState);

        $nextPromise2 = $promise->then($onFulfilled);
        $nextPromise3 = $promise->then($onFulfilled);

        if ($onFulfilled) {
            expect($nextPromise2)->toNotBeSame($nextPromise);
        }

        SyncPromise::runQueue();

        $this->assertValidPromise($nextPromise2, $expectedNextReason, $expectedNextValue, $expectedNextState);
        $this->assertValidPromise($nextPromise3, $expectedNextReason, $expectedNextValue, $expectedNextState);
    }

    public function getRejectedPromiseData()
    {
        $onRejectedReturnsNull = function() {
            return null;
        };
        $onRejectedReturnsSomeValue = function($reason) {
            return 'some-value';
        };
        $onRejectedThrowsSameReason = function($reason) {
            throw $reason;
        };
        $onRejectedThrowsOtherReason = function($value) {
            throw new \Exception("onRejected throws other!");
        };

        return [
            // $rejectedReason, $onRejected, $expectedNextValue, $expectedNextReason, $expectedNextState
            [new \Exception('test-reason'), null, null, 'test-reason', SyncPromise::REJECTED],
            [new \Exception('test-reason-2'), $onRejectedReturnsNull, null, null, SyncPromise::FULFILLED],
            [new \Exception('test-reason-3'), $onRejectedReturnsSomeValue, 'some-value', null, SyncPromise::FULFILLED],
            [new \Exception('test-reason-4'), $onRejectedThrowsSameReason, null, 'test-reason-4', SyncPromise::REJECTED],
            [new \Exception('test-reason-5'), $onRejectedThrowsOtherReason, null, 'onRejected throws other!', SyncPromise::REJECTED],
        ];
    }

    <<DataProvider('getRejectedPromiseData')>>
    public function testRejectedPromiseCannotChangeReason(
        $rejectedReason,
        $onRejected,
        $expectedNextValue,
        $expectedNextReason,
        $expectedNextState
    )
    {
        $promise = new SyncPromise();
        expect($promise->state)->toBePHPEqual(SyncPromise::PENDING);

        $promise->reject($rejectedReason);
        expect($promise->state)->toBePHPEqual(SyncPromise::REJECTED);

        $this->setExpectedException(\Exception::class, 'Cannot change rejection reason');
        $promise->reject(new \Exception('other-reason'));

    }

    <<DataProvider('getRejectedPromiseData')>>
    public function testRejectedPromiseCannotBeResolved(
        $rejectedReason,
        $onRejected,
        $expectedNextValue,
        $expectedNextReason,
        $expectedNextState
    )
    {
        $promise = new SyncPromise();
        expect($promise->state)->toBePHPEqual(SyncPromise::PENDING);

        $promise->reject($rejectedReason);
        expect($promise->state)->toBePHPEqual(SyncPromise::REJECTED);

        $this->setExpectedException(\Exception::class, 'Cannot resolve rejected promise');
        $promise->resolve('anything');
    }

    <<DataProvider('getRejectedPromiseData')>>
    public function testRejectedPromise(
        $rejectedReason,
        $onRejected,
        $expectedNextValue,
        $expectedNextReason,
        $expectedNextState
    )
    {
        $promise = new SyncPromise();
        expect($promise->state)->toBePHPEqual(SyncPromise::PENDING);

        $promise->reject($rejectedReason);
        expect($promise->state)->toBePHPEqual(SyncPromise::REJECTED);

        try {
            $promise->reject(new \Exception('other-reason'));
            self::fail('Expected exception not thrown');
        } catch (\Exception $e) {
            expect($e->getMessage())->toBePHPEqual('Cannot change rejection reason');
        }

        try {
            $promise->resolve('anything');
            self::fail('Expected exception not thrown');
        } catch (\Exception $e) {
            expect($e->getMessage())->toBePHPEqual('Cannot resolve rejected promise');
        }

        $nextPromise = $promise->then(function() {}, null);
        expect($nextPromise)->toBeSame($promise);

        $onFulfilledCalled = false;
        $nextPromise = $promise->then(
            function () use (&$onFulfilledCalled) {
                $onFulfilledCalled = true;
            },
            $onRejected
        );

        if ($onRejected) {
            expect($nextPromise)->toNotBeSame($promise);
            expect($nextPromise->state)->toBePHPEqual(SyncPromise::PENDING);
        } else {
            expect($nextPromise->state)->toBePHPEqual(SyncPromise::REJECTED);
        }
        expect($onFulfilledCalled)->toBePHPEqual(false);
        $this->assertValidPromise($nextPromise, $expectedNextReason, $expectedNextValue, $expectedNextState);

        $nextPromise2 = $promise->then(null, $onRejected);
        $nextPromise3 = $promise->then(null, $onRejected);

        if ($onRejected) {
            expect($nextPromise2)->toNotBeSame($nextPromise);
        }

        SyncPromise::runQueue();

        $this->assertValidPromise($nextPromise2, $expectedNextReason, $expectedNextValue, $expectedNextState);
        $this->assertValidPromise($nextPromise3, $expectedNextReason, $expectedNextValue, $expectedNextState);
    }

    public function testPendingPromise():void
    {
        $promise = new SyncPromise();
        expect($promise->state)->toBePHPEqual(SyncPromise::PENDING);

        try {
            $promise->resolve($promise);
            self::fail('Expected exception not thrown');
        } catch (\Exception $e) {
            expect($e->getMessage())->toBePHPEqual('Cannot resolve promise with self');
            expect($promise->state)->toBePHPEqual(SyncPromise::PENDING);
        }

        // Try to resolve with other promise (must resolve when other promise resolves)
        $otherPromise = new SyncPromise();
        $promise->resolve($otherPromise);

        expect($promise->state)->toBePHPEqual(SyncPromise::PENDING);
        expect($otherPromise->state)->toBePHPEqual(SyncPromise::PENDING);

        $otherPromise->resolve('the value');
        expect($otherPromise->state)->toBePHPEqual(SyncPromise::FULFILLED);
        expect($promise->state)->toBePHPEqual(SyncPromise::PENDING);
        $this->assertValidPromise($promise, null, 'the value', SyncPromise::FULFILLED);

        $promise = new SyncPromise();
        $promise->resolve('resolved!');

        $this->assertValidPromise($promise, null, 'resolved!', SyncPromise::FULFILLED);

        // Test rejections
        $promise = new SyncPromise();
        expect($promise->state)->toBePHPEqual(SyncPromise::PENDING);

        try {
            $promise->reject('a');
            self::fail('Expected exception not thrown');
        } catch (\PHPUnit_Framework_AssertionFailedError $e) {
            throw $e;
        } catch (\Throwable $e) {
            expect($promise->state)->toBePHPEqual(SyncPromise::PENDING);
        } catch (\Exception $e) {
            expect($promise->state)->toBePHPEqual(SyncPromise::PENDING);
        }

        $promise->reject(new \Exception("Rejected Reason"));
        $this->assertValidPromise($promise, "Rejected Reason", null, SyncPromise::REJECTED);

        $promise = new SyncPromise();
        $promise2 = $promise->then(null, function() {
            return 'value';
        });
        $promise->reject(new \Exception("Rejected Again"));
        $this->assertValidPromise($promise2, null, 'value', SyncPromise::FULFILLED);

        $promise = new SyncPromise();
        $promise2 = $promise->then();
        $promise->reject(new \Exception("Rejected Once Again"));
        $this->assertValidPromise($promise2, "Rejected Once Again", null, SyncPromise::REJECTED);
    }

    public function testPendingPromiseThen():void
    {
        $promise = new SyncPromise();
        expect($promise->state)->toBePHPEqual(SyncPromise::PENDING);

        $nextPromise = $promise->then();
        expect($nextPromise)->toNotBeSame($promise);
        expect($promise->state)->toBePHPEqual(SyncPromise::PENDING);
        expect($nextPromise->state)->toBePHPEqual(SyncPromise::PENDING);

        // Make sure that it queues derivative promises until resolution:
        $onFulfilledCount = 0;
        $onRejectedCount = 0;
        $onFulfilled = function($value) use (&$onFulfilledCount) {
            $onFulfilledCount++;
            return $onFulfilledCount;
        };
        $onRejected = function($reason) use (&$onRejectedCount) {
            $onRejectedCount++;
            throw $reason;
        };

        $nextPromise2 = $promise->then($onFulfilled, $onRejected);
        $nextPromise3 = $promise->then($onFulfilled, $onRejected);
        $nextPromise4 = $promise->then($onFulfilled, $onRejected);

        expect(0)->toBePHPEqual(SyncPromise::getQueue()->count());
        expect(0)->toBePHPEqual($onFulfilledCount);
        expect(0)->toBePHPEqual($onRejectedCount);
        $promise->resolve(1);

        expect(4)->toBePHPEqual(SyncPromise::getQueue()->count());
        expect(0)->toBePHPEqual($onFulfilledCount);
        expect(0)->toBePHPEqual($onRejectedCount);
        expect($nextPromise->state)->toBePHPEqual(SyncPromise::PENDING);
        expect($nextPromise2->state)->toBePHPEqual(SyncPromise::PENDING);
        expect($nextPromise3->state)->toBePHPEqual(SyncPromise::PENDING);
        expect($nextPromise4->state)->toBePHPEqual(SyncPromise::PENDING);

        SyncPromise::runQueue();
        expect(0)->toBePHPEqual(SyncPromise::getQueue()->count());
        expect(3)->toBePHPEqual($onFulfilledCount);
        expect(0)->toBePHPEqual($onRejectedCount);
        $this->assertValidPromise($nextPromise, null, 1, SyncPromise::FULFILLED);
        $this->assertValidPromise($nextPromise2, null, 1, SyncPromise::FULFILLED);
        $this->assertValidPromise($nextPromise3, null, 2, SyncPromise::FULFILLED);
        $this->assertValidPromise($nextPromise4, null, 3, SyncPromise::FULFILLED);
    }

    private function assertValidPromise(SyncPromise $promise, $expectedNextReason, $expectedNextValue, $expectedNextState)
    {
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

        expect(false)->toBePHPEqual($onFulfilledCalled);
        expect(false)->toBePHPEqual($onRejectedCalled);

        SyncPromise::runQueue();

        expect($onFulfilledCalled)->toBePHPEqual(!$expectedNextReason);
        expect($onRejectedCalled)->toBePHPEqual(!!$expectedNextReason);

        expect($actualNextValue)->toBePHPEqual($expectedNextValue);
        expect($actualNextReason)->toBePHPEqual($expectedNextReason);
        expect($promise->state)->toBePHPEqual($expectedNextState);
    }
}
