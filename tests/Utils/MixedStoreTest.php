<?hh //strict
//decl
namespace GraphQL\Tests\Utils;


use GraphQL\Utils\Utils;
use function Facebook\FBExpect\expect;
use GraphQL\Utils\MixedStore;

class MixedStoreTest extends \Facebook\HackTest\HackTest
{
    /**
     * @var MixedStore
     */
    private $mixedStore;

    public async function beforeEachTestAsync():Awaitable<void>
    {
        $this->mixedStore = new MixedStore();
    }

    public function getPossibleValues():array<mixed>
    {
        return [
            null,
            false,
            true,
            '',
            '0',
            '1',
            'a',
            [],
            new \stdClass(),
            function() {},
            new MixedStore()
        ];
    }

    public function testAcceptsNullKeys():void
    {
        foreach ($this->getPossibleValues() as $value) {
            $this->assertAcceptsKeyValue(null, $value);
        }
    }

    public function testAcceptsBoolKeys():void
    {
        foreach ($this->getPossibleValues() as $value) {
            $this->assertAcceptsKeyValue(false, $value);
        }
        foreach ($this->getPossibleValues() as $value) {
            $this->assertAcceptsKeyValue(true, $value);
        }
    }

    public function testAcceptsIntKeys():void
    {
        foreach ($this->getPossibleValues() as $value) {
            $this->assertAcceptsKeyValue(-100000, $value);
            $this->assertAcceptsKeyValue(-1, $value);
            $this->assertAcceptsKeyValue(0, $value);
            $this->assertAcceptsKeyValue(1, $value);
            $this->assertAcceptsKeyValue(1000000, $value);
        }
    }

    public function testAcceptsFloatKeys():void
    {
        foreach ($this->getPossibleValues() as $value) {
            $this->assertAcceptsKeyValue(-100000.5, $value);
            $this->assertAcceptsKeyValue(-1.6, $value);
            $this->assertAcceptsKeyValue(-0.0001, $value);
            $this->assertAcceptsKeyValue(0.0000, $value);
            $this->assertAcceptsKeyValue(0.0001, $value);
            $this->assertAcceptsKeyValue(1.6, $value);
            $this->assertAcceptsKeyValue(1000000.5, $value);
        }
    }

    public function testAcceptsArrayKeys():void
    {
        foreach ($this->getPossibleValues() as $value) {
            $this->assertAcceptsKeyValue([], $value);
            $this->assertAcceptsKeyValue([null], $value);
            $this->assertAcceptsKeyValue([[]], $value);
            $this->assertAcceptsKeyValue([new \stdClass()], $value);
            $this->assertAcceptsKeyValue(['a', 'b'], $value);
            $this->assertAcceptsKeyValue(['a' => 'b'], $value);
        }
    }

    public function testAcceptsObjectKeys():void
    {
        foreach ($this->getPossibleValues() as $value) {
            $this->assertAcceptsKeyValue(new \stdClass(), $value);
            $this->assertAcceptsKeyValue(new MixedStore(), $value);
            $this->assertAcceptsKeyValue(function() {}, $value);
        }
    }

    private function assertAcceptsKeyValue(mixed $key, mixed $value):void
    {
        $err = 'Failed assertion that MixedStore accepts key ' .
            Utils::printSafe($key) . ' with value ' .  Utils::printSafe($value);

        expect($this->mixedStore->offsetExists($key))->toBeFalse($err);
        $this->mixedStore->offsetSet($key, $value);
        expect($this->mixedStore->offsetExists($key))->toBeTrue($err);
        expect($this->mixedStore->offsetGet($key))->toBeSame($value, $err);
        $this->mixedStore->offsetUnset($key);
        expect($this->mixedStore->offsetExists($key))->toBeFalse($err);
        $this->assertProvidesArrayAccess($key, $value);
    }

    private function assertProvidesArrayAccess(mixed $key, mixed $value):void
    {
        $err = 'Failed assertion that MixedStore provides array access for key ' .
            Utils::printSafe($key) . ' with value ' .  Utils::printSafe($value);

        expect(isset($this->mixedStore[$key]))->toBeFalse($err);
        $this->mixedStore[$key] = $value;
        expect(isset($this->mixedStore[$key]))->toBeTrue($err);
        expect(!empty($this->mixedStore[$key]))->toBePHPEqual(!empty($value), $err);
        expect($this->mixedStore[$key])->toBeSame($value, $err);
        unset($this->mixedStore[$key]);
        expect(isset($this->mixedStore[$key]))->toBeFalse($err);
    }
}
