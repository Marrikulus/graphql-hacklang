<?hh //strict
//decl
namespace GraphQL\Tests\Type;

use GraphQL\Error\InvariantViolation;
use function Facebook\FBExpect\expect;
use GraphQL\Error\UserError;
use GraphQL\Type\Definition\GraphQlType;

class ScalarSerializationTest extends \Facebook\HackTest\HackTest
{
    // Type System: Scalar coercion

    /**
     * @it serializes output int
     */
    public function testSerializesOutputInt():void
    {
        $intType = GraphQlType::int();

        expect($intType->serialize(1))->toBeSame(1);
        expect($intType->serialize('123'))->toBeSame(123);
        expect($intType->serialize(0))->toBeSame(0);
        expect($intType->serialize(-1))->toBeSame(-1);
        expect($intType->serialize(1e5))->toBeSame(100000);
        expect($intType->serialize(0e5))->toBeSame(0);
        expect($intType->serialize(false))->toBeSame(0);
        expect($intType->serialize(true))->toBeSame(1);
    }

    public function testSerializesOutputIntCannotRepresentFloat1():void
    {
        // The GraphQL specification does not allow serializing non-integer values
        // as Int to avoid accidental data loss.
        $intType = GraphQlType::int();
        $this->setExpectedException(InvariantViolation::class, 'Int cannot represent non-integer value: 0.1');
        $intType->serialize(0.1);
    }

    public function testSerializesOutputIntCannotRepresentFloat2():void
    {
        $intType = GraphQlType::int();
        $this->setExpectedException(InvariantViolation::class, 'Int cannot represent non-integer value: 1.1');
        $intType->serialize(1.1);

    }

    public function testSerializesOutputIntCannotRepresentNegativeFloat():void
    {
        $intType = GraphQlType::int();
        $this->setExpectedException(InvariantViolation::class, 'Int cannot represent non-integer value: -1.1');
        $intType->serialize(-1.1);

    }

    public function testSerializesOutputIntCannotRepresentNumericString():void
    {
        $intType = GraphQlType::int();
        $this->setExpectedException(InvariantViolation::class, '');
        $intType->serialize('Int cannot represent non-integer value: "-1.1"');

    }

    public function testSerializesOutputIntCannotRepresentBiggerThan32Bits():void
    {
        // Maybe a safe PHP int, but bigger than 2^32, so not
        // representable as a GraphQL Int
        $intType = GraphQlType::int();
        $this->setExpectedException(InvariantViolation::class, 'Int cannot represent non 32-bit signed integer value: 9876504321');
        $intType->serialize(9876504321);

    }

    public function testSerializesOutputIntCannotRepresentLowerThan32Bits():void
    {
        $intType = GraphQlType::int();
        $this->setExpectedException(InvariantViolation::class, 'Int cannot represent non 32-bit signed integer value: -9876504321');
        $intType->serialize(-9876504321);
    }

    public function testSerializesOutputIntCannotRepresentBiggerThanSigned32Bits():void
    {
        $intType = GraphQlType::int();
        $this->setExpectedException(InvariantViolation::class, 'Int cannot represent non 32-bit signed integer value: 1.0E+100');
        $intType->serialize(1e100);
    }

    public function testSerializesOutputIntCannotRepresentLowerThanSigned32Bits():void
    {
        $intType = GraphQlType::int();
        $this->setExpectedException(InvariantViolation::class, 'Int cannot represent non 32-bit signed integer value: -1.0E+100');
        $intType->serialize(-1e100);
    }

    public function testSerializesOutputIntCannotRepresentString():void
    {
        $intType = GraphQlType::int();
        $this->setExpectedException(InvariantViolation::class, 'Int cannot represent non 32-bit signed integer value: "one"');
        $intType->serialize('one');

    }

    public function testSerializesOutputIntCannotRepresentEmptyString():void
    {
        $intType = GraphQlType::int();
        $this->setExpectedException(InvariantViolation::class, 'Int cannot represent non 32-bit signed integer value: (empty string)');
        $intType->serialize('');
    }

    /**
     * @it serializes output float
     */
    public function testSerializesOutputFloat():void
    {
        $floatType = GraphQlType::float();

        expect($floatType->serialize(1))->toBeSame(1.0);
        expect($floatType->serialize(0))->toBeSame(0.0);
        expect($floatType->serialize('123.5'))->toBeSame(123.5);
        expect($floatType->serialize(-1))->toBeSame(-1.0);
        expect($floatType->serialize(0.1))->toBeSame(0.1);
        expect($floatType->serialize(1.1))->toBeSame(1.1);
        expect($floatType->serialize(-1.1))->toBeSame(-1.1);
        expect($floatType->serialize('-1.1'))->toBeSame(-1.1);
        expect($floatType->serialize(false))->toBeSame(0.0);
        expect($floatType->serialize(true))->toBeSame(1.0);
    }

    public function testSerializesOutputFloatCannotRepresentString():void
    {
        $floatType = GraphQlType::float();
        $this->setExpectedException(InvariantViolation::class, 'Float cannot represent non numeric value: "one"');
        $floatType->serialize('one');
    }

    public function testSerializesOutputFloatCannotRepresentEmptyString():void
    {
        $floatType = GraphQlType::float();
        $this->setExpectedException(InvariantViolation::class, 'Float cannot represent non numeric value: (empty string)');
        $floatType->serialize('');
    }

    /**
     * @it serializes output strings
     */
    public function testSerializesOutputStrings():void
    {
        $stringType = GraphQlType::string();

        expect($stringType->serialize('string'))->toBeSame('string');
        expect($stringType->serialize(1))->toBeSame('1');
        expect($stringType->serialize(-1.1))->toBeSame('-1.1');
        expect($stringType->serialize(true))->toBeSame('true');
        expect($stringType->serialize(false))->toBeSame('false');
        expect($stringType->serialize(null))->toBeSame('null');
    }

    public function testSerializesOutputStringsCannotRepresentArray():void
    {
        $stringType = GraphQlType::string();
        $this->setExpectedException(InvariantViolation::class, 'String cannot represent non scalar value: array(0)');
        $stringType->serialize([]);
    }

    public function testSerializesOutputStringsCannotRepresentObject():void
    {
        $stringType = GraphQlType::string();
        $this->setExpectedException(InvariantViolation::class, 'String cannot represent non scalar value: instance of stdClass');
        $stringType->serialize(new \stdClass());
    }

    /**
     * @it serializes output boolean
     */
    public function testSerializesOutputBoolean():void
    {
        $boolType = GraphQlType::boolean();

        expect($boolType->serialize('string'))->toBeSame(true);
        expect($boolType->serialize(''))->toBeSame(false);
        expect($boolType->serialize('1'))->toBeSame(true);
        expect($boolType->serialize(1))->toBeSame(true);
        expect($boolType->serialize(0))->toBeSame(false);
        expect($boolType->serialize(true))->toBeSame(true);
        expect($boolType->serialize(false))->toBeSame(false);

        // TODO: how should it behave on '0'?
    }

    public function testSerializesOutputID():void
    {
        $idType = GraphQlType::id();

        expect($idType->serialize('string'))->toBeSame('string');
        expect($idType->serialize(''))->toBeSame('');
        expect($idType->serialize('1'))->toBeSame('1');
        expect($idType->serialize(1))->toBeSame('1');
        expect($idType->serialize(0))->toBeSame('0');
        expect($idType->serialize(true))->toBeSame('true');
        expect($idType->serialize(false))->toBeSame('false');
        expect($idType->serialize(new ObjectIdStub(2)))->toBeSame('2');
    }

    public function testSerializesOutputIDCannotRepresentObject():void
    {
        $idType = GraphQlType::id();
        $this->setExpectedException(InvariantViolation::class, 'ID type cannot represent non scalar value: instance of stdClass');
        $idType->serialize(new \stdClass());
    }
}
