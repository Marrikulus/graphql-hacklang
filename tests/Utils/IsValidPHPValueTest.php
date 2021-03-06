<?hh //strict
//decl
namespace GraphQL\Tests\Utils;


use GraphQL\Executor\Values;
use function Facebook\FBExpect\expect;
use GraphQL\Type\Definition\GraphQlType;

class IsValidPHPValueTest extends \Facebook\HackTest\HackTest
{
    public function testValidIntValue():void
    {
        // returns no error for positive int value
        $result = Values::isValidPHPValue(1, GraphQlType::int());
        $this->expectNoErrors($result);

        // returns no error for negative int value
        $result = Values::isValidPHPValue(-1, GraphQlType::int());
        $this->expectNoErrors($result);

        // returns no error for null value
        $result = Values::isValidPHPValue(null, GraphQlType::int());
        $this->expectNoErrors($result);

        // returns a single error for positive int string value
        $result = Values::isValidPHPValue('1', GraphQlType::int());
        $this->expectErrorResult($result, 1);

        // returns a single error for negative int string value
        $result = Values::isValidPHPValue('-1', GraphQlType::int());
        $this->expectErrorResult($result, 1);

        // returns errors for exponential int string value
        $result = Values::isValidPHPValue('1e3', GraphQlType::int());
        $this->expectErrorResult($result, 1);
        $result = Values::isValidPHPValue('0e3', GraphQlType::int());
        $this->expectErrorResult($result, 1);

        // returns a single error for empty value
        $result = Values::isValidPHPValue('', GraphQlType::int());
        $this->expectErrorResult($result, 1);

        // returns error for float value
        $result = Values::isValidPHPValue(1.5, GraphQlType::int());
        $this->expectErrorResult($result, 1);
        $result = Values::isValidPHPValue(1e3, GraphQlType::int());
        $this->expectErrorResult($result, 1);

        // returns error for float string value
        $result = Values::isValidPHPValue('1.5', GraphQlType::int());
        $this->expectErrorResult($result, 1);

        // returns a single error for char input
        $result = Values::isValidPHPValue('a', GraphQlType::int());
        $this->expectErrorResult($result, 1);

        // returns a single error for char input
        $result = Values::isValidPHPValue('meow', GraphQlType::int());
        $this->expectErrorResult($result, 1);
    }

    public function testValidFloatValue():void
    {
        // returns no error for positive float value
        $result = Values::isValidPHPValue(1.2, GraphQlType::float());
        $this->expectNoErrors($result);

        // returns no error for exponential float value
        $result = Values::isValidPHPValue(1e3, GraphQlType::float());
        $this->expectNoErrors($result);

        // returns no error for negative float value
        $result = Values::isValidPHPValue(-1.2, GraphQlType::float());
        $this->expectNoErrors($result);

        // returns no error for a positive int value
        $result = Values::isValidPHPValue(1, GraphQlType::float());
        $this->expectNoErrors($result);

        // returns no errors for a negative int value
        $result = Values::isValidPHPValue(-1, GraphQlType::float());
        $this->expectNoErrors($result);

        // returns no error for null value:
        $result = Values::isValidPHPValue(null, GraphQlType::float());
        $this->expectNoErrors($result);

        // returns error for positive float string value
        $result = Values::isValidPHPValue('1.2', GraphQlType::float());
        $this->expectErrorResult($result, 1);

        // returns error for negative float string value
        $result = Values::isValidPHPValue('-1.2', GraphQlType::float());
        $this->expectErrorResult($result, 1);

        // returns error for a positive int string value
        $result = Values::isValidPHPValue('1', GraphQlType::float());
        $this->expectErrorResult($result, 1);

        // returns errors for a negative int string value
        $result = Values::isValidPHPValue('-1', GraphQlType::float());
        $this->expectErrorResult($result, 1);

        // returns error for exponent input
        $result = Values::isValidPHPValue('1e3', GraphQlType::float());
        $this->expectErrorResult($result, 1);
        $result = Values::isValidPHPValue('0e3', GraphQlType::float());
        $this->expectErrorResult($result, 1);

        // returns a single error for empty value
        $result = Values::isValidPHPValue('', GraphQlType::float());
        $this->expectErrorResult($result, 1);

        // returns a single error for char input
        $result = Values::isValidPHPValue('a', GraphQlType::float());
        $this->expectErrorResult($result, 1);

        // returns a single error for char input
        $result = Values::isValidPHPValue('meow', GraphQlType::float());
        $this->expectErrorResult($result, 1);
    }

    private function expectNoErrors(mixed $result):void
    {
        expect($result)->toBeType('array');
        expect($result)->toBePHPEqual([]);
    }

    private function expectErrorResult(mixed $result, int $size):void
    {
        expect($result)->toBeType('array');
        expect(\count($result))->toBePHPEqual($size);
    }
}
