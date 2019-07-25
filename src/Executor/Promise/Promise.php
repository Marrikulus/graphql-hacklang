<?hh //strict
//partial
namespace GraphQL\Executor\Promise;

use GraphQL\Utils\Utils;
use GraphQL\Error\Error;

/**
 * Convenience wrapper for promises represented by Promise Adapter
 */
class Promise
{
    private PromiseAdapter $adapter;

    public nonnull $adoptedPromise;

    /**
     * Promise constructor.
     *
     * @param mixed $adoptedPromise
     * @param PromiseAdapter $adapter
     */
    public function __construct(nonnull $adoptedPromise, PromiseAdapter $adapter)
    {
        Utils::invariant(!$adoptedPromise instanceof self, 'Expecting promise from adapted system, got ' . __CLASS__);

        $this->adapter = $adapter;
        $this->adoptedPromise = $adoptedPromise;
    }

    /**
     * @param callable|null $onFulfilled
     * @param callable|null $onRejected
     *
     * @return Promise
     */
    public function then(?(function(mixed):mixed) $onFulfilled = null, ?(function(Error):mixed) $onRejected = null):mixed
    {
        return $this->adapter->then($this, $onFulfilled, $onRejected);
    }
}
