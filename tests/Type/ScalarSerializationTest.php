<?hh //strict
//decl
namespace GraphQL\Tests\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\Error\UserError;
use GraphQL\Type\Definition\GraphQlType;

class ScalarSerializationTest extends \PHPUnit_Framework_TestCase
{
    // Type System: Scalar coercion

    /**
     * @it serializes output int
     */
    public function testSerializesOutputInt():void
    {
        $intType = GraphQlType::int();

        $this->assertSame(1, $intType->serialize(1));
        $this->assertSame(123, $intType->serialize('123'));
        $this->assertSame(0, $intType->serialize(0));
        $this->assertSame(-1, $intType->serialize(-1));
        $this->assertSame(100000, $intType->serialize(1e5));
        $this->assertSame(0, $intType->serialize(0e5));
        $this->assertSame(0, $intType->serialize(false));
        $this->assertSame(1, $intType->serialize(true));
    }

    public function testSerializesOutputIntCannotRepresentFloat1()
    {
        // The GraphQL specification does not allow serializing non-integer values
        // as Int to avoid accidental data loss.
        $intType = GraphQlType::int();
        $this->setExpectedException(InvariantViolation::class, 'Int cannot represent non-integer value: 0.1');
        $intType->serialize(0.1);
    }

    public function testSerializesOutputIntCannotRepresentFloat2()
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

    public function testSerializesOutputIntCannotRepresentBiggerThan32Bits()
    {
        // Maybe a safe PHP int, but bigger than 2^32, so not
        // representable as a GraphQL Int
        $intType = GraphQlType::int();
        $this->setExpectedException(InvariantViolation::class, 'Int cannot represent non 32-bit signed integer value: 9876504321');
        $intType->serialize(9876504321);

    }

    public function testSerializesOutputIntCannotRepresentLowerThan32Bits()
    {
        $intType = GraphQlType::int();
        $this->setExpectedException(InvariantViolation::class, 'Int cannot represent non 32-bit signed integer value: -9876504321');
        $intType->serialize(-9876504321);
    }

    public function testSerializesOutputIntCannotRepresentBiggerThanSigned32Bits()
    {
        $intType = GraphQlType::int();
        $this->setExpectedException(InvariantViolation::class, 'Int cannot represent non 32-bit signed integer value: 1.0E+100');
        $intType->serialize(1e100);
    }

    public function testSerializesOutputIntCannotRepresentLowerThanSigned32Bits()
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

        $this->assertSame(1.0, $floatType->serialize(1));
        $this->assertSame(0.0, $floatType->serialize(0));
        $this->assertSame(123.5, $floatType->serialize('123.5'));
        $this->assertSame(-1.0, $floatType->serialize(-1));
        $this->assertSame(0.1, $floatType->serialize(0.1));
        $this->assertSame(1.1, $floatType->serialize(1.1));
        $this->assertSame(-1.1, $floatType->serialize(-1.1));
        $this->assertSame(-1.1, $floatType->serialize('-1.1'));
        $this->assertSame(0.0, $floatType->serialize(false));
        $this->assertSame(1.0, $floatType->serialize(true));
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

        $this->assertSame('string', $stringType->serialize('string'));
        $this->assertSame('1', $stringType->serialize(1));
        $this->assertSame('-1.1', $stringType->serialize(-1.1));
        $this->assertSame('true', $stringType->serialize(true));
        $this->assertSame('false', $stringType->serialize(false));
        $this->assertSame('null', $stringType->serialize(null));
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

        $this->assertSame(true, $boolType->serialize('string'));
        $this->assertSame(false, $boolType->serialize(''));
        $this->assertSame(true, $boolType->serialize('1'));
        $this->assertSame(true, $boolType->serialize(1));
        $this->assertSame(false, $boolType->serialize(0));
        $this->assertSame(true, $boolType->serialize(true));
        $this->assertSame(false, $boolType->serialize(false));

        // TODO: how should it behave on '0'?
    }

    public function testSerializesOutputID():void
    {
        $idType = GraphQlType::id();

        $this->assertSame('string', $idType->serialize('string'));
        $this->assertSame('', $idType->serialize(''));
        $this->assertSame('1', $idType->serialize('1'));
        $this->assertSame('1', $idType->serialize(1));
        $this->assertSame('0', $idType->serialize(0));
        $this->assertSame('true', $idType->serialize(true));
        $this->assertSame('false', $idType->serialize(false));
        $this->assertSame('2', $idType->serialize(new ObjectIdStub(2)));
    }

    public function testSerializesOutputIDCannotRepresentObject():void
    {
        $idType = GraphQlType::id();
        $this->setExpectedException(InvariantViolation::class, 'ID type cannot represent non scalar value: instance of stdClass');
        $idType->serialize(new \stdClass());
    }
}
