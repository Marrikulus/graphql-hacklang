<?hh //strict
//decl
namespace GraphQL\Tests\Utils;

use GraphQL\Language\AST\BooleanValueNode;
use function Facebook\FBExpect\expect;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\GraphQlType;

use GraphQL\Utils\AST;

use stdClass;

class AstFromValueTest extends \Facebook\HackTest\HackTest
{
    // Describe: astFromValue

    /**
     * @it converts boolean values to ASTs
     */
    public function testConvertsBooleanValueToASTs():void
    {
        expect(AST::astFromValue(true, GraphQlType::boolean()))->toBePHPEqual(new BooleanValueNode(true));
        expect(AST::astFromValue(false, GraphQlType::boolean()))->toBePHPEqual(new BooleanValueNode(false));
        expect(AST::astFromValue(null, GraphQlType::boolean()))->toBePHPEqual(new NullValueNode());
        expect(AST::astFromValue(0, GraphQlType::boolean()))->toBePHPEqual(new BooleanValueNode(false));
        expect(AST::astFromValue(1, GraphQlType::boolean()))->toBePHPEqual(new BooleanValueNode(true));
        expect(AST::astFromValue(0, GraphQlType::nonNull(GraphQlType::boolean())))->toBePHPEqual(new BooleanValueNode(false));
        expect(AST::astFromValue(null, GraphQlType::nonNull(GraphQlType::boolean())))->toBePHPEqual(null); // Note: null means that AST cannot
    }

    /**
     * @it converts Int values to Int ASTs
     */
    public function testConvertsIntValuesToASTs():void
    {
        expect(AST::astFromValue(123.0, GraphQlType::int()))->toBePHPEqual(new IntValueNode('123'));
        expect(AST::astFromValue(1e4, GraphQlType::int()))->toBePHPEqual(new IntValueNode('10000'));
        expect(AST::astFromValue(0e4, GraphQlType::int()))->toBePHPEqual(new IntValueNode('0'));
    }

    public function testConvertsIntValuesToASTsCannotRepresentNonInteger():void
    {
        // GraphQL spec does not allow coercing non-integer values to Int to avoid
        // accidental data loss.
        $this->setExpectedException(\Exception::class, 'Int cannot represent non-integer value: 123.5');
        AST::astFromValue(123.5, GraphQlType::int());
    }

    public function testConvertsIntValuesToASTsCannotRepresentNon32bitsInteger():void
    {
        $this->setExpectedException(\Exception::class, 'Int cannot represent non 32-bit signed integer value: 1.0E+40');
        AST::astFromValue(1e40, GraphQlType::int()); // Note: js version will produce 1e+40, both values are valid GraphQL floats
    }

    /**
     * @it converts Float values to Int/Float ASTs
     */
    public function testConvertsFloatValuesToIntOrFloatASTs():void
    {
        expect(AST::astFromValue(123, GraphQlType::float()))->toBePHPEqual(new IntValueNode('123'));
        expect(AST::astFromValue(123.0, GraphQlType::float()))->toBePHPEqual(new IntValueNode('123'));
        expect(AST::astFromValue(123.5, GraphQlType::float()))->toBePHPEqual(new FloatValueNode('123.5'));
        expect(AST::astFromValue(1e4, GraphQlType::float()))->toBePHPEqual(new IntValueNode('10000'));
        expect(AST::astFromValue(1e40, GraphQlType::float()))->toBePHPEqual(new FloatValueNode('1e+40'));
        expect(AST::astFromValue(0e40, GraphQlType::float()))->toBePHPEqual(new IntValueNode('0'));
    }

    /**
     * @it converts String values to String ASTs
     */
    public function testConvertsStringValuesToASTs():void
    {
        expect(AST::astFromValue('hello', GraphQlType::string()))->toBePHPEqual(new StringValueNode('hello'));
        expect(AST::astFromValue('VALUE', GraphQlType::string()))->toBePHPEqual(new StringValueNode('VALUE'));
        expect(AST::astFromValue("VA\nLUE", GraphQlType::string()))->toBePHPEqual(new StringValueNode('VA\\nLUE'));
        expect(AST::astFromValue(123, GraphQlType::string()))->toBePHPEqual(new StringValueNode('123'));
        expect(AST::astFromValue(false, GraphQlType::string()))->toBePHPEqual(new StringValueNode('false'));
        expect(AST::astFromValue(null, GraphQlType::string()))->toBePHPEqual(new NullValueNode());
        expect(AST::astFromValue(null, GraphQlType::nonNull(GraphQlType::string())))->toBePHPEqual(null);
    }

    /**
     * @it converts ID values to Int/String ASTs
     */
    public function testConvertIdValuesToIntOrStringASTs():void
    {
        expect(AST::astFromValue('hello', GraphQlType::id()))->toBePHPEqual(new StringValueNode('hello'));
        expect(AST::astFromValue('VALUE', GraphQlType::id()))->toBePHPEqual(new StringValueNode('VALUE'));
        expect(AST::astFromValue("VA\nLUE", GraphQlType::id()))->toBePHPEqual(new StringValueNode('VA\\nLUE'));
        expect(AST::astFromValue(123, GraphQlType::id()))->toBePHPEqual(new IntValueNode('123'));
        expect(AST::astFromValue(false, GraphQlType::id()))->toBePHPEqual(new StringValueNode('false'));
        expect(AST::astFromValue(null, GraphQlType::id()))->toBePHPEqual(new NullValueNode());
        expect(AST::astFromValue(null, GraphQlType::nonNull(GraphQlType::id())))->toBePHPEqual(null);
    }

    /**
     * @it does not converts NonNull values to NullValue
     */
    public function testDoesNotConvertsNonNullValuestoNullValue():void
    {
        expect(AST::astFromValue(null, GraphQlType::nonNull(GraphQlType::boolean())))->toBeSame(null);
    }

    /**
     * @it converts string values to Enum ASTs if possible
     */
    public function testConvertsStringValuesToEnumASTsIfPossible():void
    {
        expect(AST::astFromValue('HELLO', $this->myEnum()))->toBePHPEqual(new EnumValueNode('HELLO'));
        expect(AST::astFromValue($this->complexValue(), $this->myEnum()))->toBePHPEqual(new EnumValueNode('COMPLEX'));

        // Note: case sensitive
        expect(AST::astFromValue('hello', $this->myEnum()))->toBePHPEqual(null);

        // Note: Not a valid enum value
        expect(AST::astFromValue('VALUE', $this->myEnum()))->toBePHPEqual(null);
    }

    /**
     * @it converts array values to List ASTs
     */
    public function testConvertsArrayValuesToListASTs():void
    {
        $value1 = new ListValueNode([
            new StringValueNode('FOO'),
            new StringValueNode('BAR')
        ]);
        expect(AST::astFromValue(['FOO', 'BAR'], GraphQlType::listOf(GraphQlType::string())))->toBePHPEqual($value1);

        $value2 = new ListValueNode([
            new EnumValueNode('HELLO'),
            new EnumValueNode('GOODBYE'),
        ]);
        expect(AST::astFromValue(['HELLO', 'GOODBYE'], GraphQlType::listOf($this->myEnum())))->toBePHPEqual($value2);
    }

    /**
     * @it converts list singletons
     */
    public function testConvertsListSingletons():void
    {
        expect(AST::astFromValue('FOO', GraphQlType::listOf(GraphQlType::string())))->toBePHPEqual(new StringValueNode('FOO'));
    }

    /**
     * @it converts input objects
     */
    public function testConvertsInputObjects():void
    {
        $inputObj = new InputObjectType([
            'name' => 'MyInputObj',
            'fields' => [
                'foo' => GraphQlType::float(),
                'bar' => $this->myEnum()
            ]
        ]);

        $expected = new ObjectValueNode([
            $this->objectField('foo', new IntValueNode('3')),
            $this->objectField('bar', new EnumValueNode('HELLO'))
        ]);

        $data = ['foo' => 3, 'bar' => 'HELLO'];
        expect(AST::astFromValue($data, $inputObj))->toBePHPEqual($expected);
        expect(AST::astFromValue((object) $data, $inputObj))->toBePHPEqual($expected);
    }

    /**
     * @it converts input objects with explicit nulls
     */
    public function testConvertsInputObjectsWithExplicitNulls():void
    {
        $inputObj = new InputObjectType([
            'name' => 'MyInputObj',
            'fields' => [
                'foo' => GraphQlType::float(),
                'bar' => $this->myEnum()
            ]
        ]);

        expect(AST::astFromValue(['foo' => null], $inputObj))->toBePHPEqual(new ObjectValueNode([
            $this->objectField('foo', new NullValueNode())
        ]));
    }

    private ?stdClass $complexValue;

    private function complexValue():stdClass
    {
        $complexValue = $this->complexValue;
        if ($complexValue === null)
        {
            $this->complexValue = $complexValue = new stdClass();
            $complexValue->someArbitrary = 'complexValue';
        }
        return $this->complexValue;
    }

    /**
     * @return EnumType
     */
    private function myEnum():EnumType
    {
        return new EnumType([
            'name' => 'MyEnum',
            'values' => [
                'HELLO' => [],
                'GOODBYE' => [],
                'COMPLEX' => ['value' => $this->complexValue()]
            ]
        ]);
    }

    /**
     * @param $name
     * @param $value
     * @return ObjectFieldNode
     */
    private function objectField(string $name, Node $value):ObjectFieldNode
    {
        return new ObjectFieldNode(
            new NameNode($name),
            $value
        );
    }
}
