<?hh //strict
//decl
namespace GraphQL\Tests\Utils;

use GraphQL\Language\AST\BooleanValueNode;
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

class AstFromValueTest extends \PHPUnit_Framework_TestCase
{
    // Describe: astFromValue

    /**
     * @it converts boolean values to ASTs
     */
    public function testConvertsBooleanValueToASTs():void
    {
        $this->assertEquals(new BooleanValueNode(true), AST::astFromValue(true, GraphQlType::boolean()));
        $this->assertEquals(new BooleanValueNode(false), AST::astFromValue(false, GraphQlType::boolean()));
        $this->assertEquals(new NullValueNode(), AST::astFromValue(null, GraphQlType::boolean()));
        $this->assertEquals(new BooleanValueNode(false), AST::astFromValue(0, GraphQlType::boolean()));
        $this->assertEquals(new BooleanValueNode(true), AST::astFromValue(1, GraphQlType::boolean()));
        $this->assertEquals(new BooleanValueNode(false), AST::astFromValue(0, GraphQlType::nonNull(GraphQlType::boolean())));
        $this->assertEquals(null, AST::astFromValue(null, GraphQlType::nonNull(GraphQlType::boolean()))); // Note: null means that AST cannot
    }

    /**
     * @it converts Int values to Int ASTs
     */
    public function testConvertsIntValuesToASTs():void
    {
        $this->assertEquals(new IntValueNode('123'), AST::astFromValue(123.0, GraphQlType::int()));
        $this->assertEquals(new IntValueNode('10000'), AST::astFromValue(1e4, GraphQlType::int()));
        $this->assertEquals(new IntValueNode('0'), AST::astFromValue(0e4, GraphQlType::int()));
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
        $this->assertEquals(new IntValueNode('123'), AST::astFromValue(123, GraphQlType::float()));
        $this->assertEquals(new IntValueNode('123'), AST::astFromValue(123.0, GraphQlType::float()));
        $this->assertEquals(new FloatValueNode('123.5'), AST::astFromValue(123.5, GraphQlType::float()));
        $this->assertEquals(new IntValueNode('10000'), AST::astFromValue(1e4, GraphQlType::float()));
        $this->assertEquals(new FloatValueNode('1e+40'), AST::astFromValue(1e40, GraphQlType::float()));
        $this->assertEquals(new IntValueNode('0'), AST::astFromValue(0e40, GraphQlType::float()));
    }

    /**
     * @it converts String values to String ASTs
     */
    public function testConvertsStringValuesToASTs():void
    {
        $this->assertEquals(new StringValueNode('hello'), AST::astFromValue('hello', GraphQlType::string()));
        $this->assertEquals(new StringValueNode('VALUE'), AST::astFromValue('VALUE', GraphQlType::string()));
        $this->assertEquals(new StringValueNode('VA\\nLUE'), AST::astFromValue("VA\nLUE", GraphQlType::string()));
        $this->assertEquals(new StringValueNode('123'), AST::astFromValue(123, GraphQlType::string()));
        $this->assertEquals(new StringValueNode('false'), AST::astFromValue(false, GraphQlType::string()));
        $this->assertEquals(new NullValueNode(), AST::astFromValue(null, GraphQlType::string()));
        $this->assertEquals(null, AST::astFromValue(null, GraphQlType::nonNull(GraphQlType::string())));
    }

    /**
     * @it converts ID values to Int/String ASTs
     */
    public function testConvertIdValuesToIntOrStringASTs():void
    {
        $this->assertEquals(new StringValueNode('hello'), AST::astFromValue('hello', GraphQlType::id()));
        $this->assertEquals(new StringValueNode('VALUE'), AST::astFromValue('VALUE', GraphQlType::id()));
        $this->assertEquals(new StringValueNode('VA\\nLUE'), AST::astFromValue("VA\nLUE", GraphQlType::id()));
        $this->assertEquals(new IntValueNode('123'), AST::astFromValue(123, GraphQlType::id()));
        $this->assertEquals(new StringValueNode('false'), AST::astFromValue(false, GraphQlType::id()));
        $this->assertEquals(new NullValueNode(), AST::astFromValue(null, GraphQlType::id()));
        $this->assertEquals(null, AST::astFromValue(null, GraphQlType::nonNull(GraphQlType::id())));
    }

    /**
     * @it does not converts NonNull values to NullValue
     */
    public function testDoesNotConvertsNonNullValuestoNullValue():void
    {
        $this->assertSame(null, AST::astFromValue(null, GraphQlType::nonNull(GraphQlType::boolean())));
    }

    /**
     * @it converts string values to Enum ASTs if possible
     */
    public function testConvertsStringValuesToEnumASTsIfPossible():void
    {
        $this->assertEquals(new EnumValueNode('HELLO'), AST::astFromValue('HELLO', $this->myEnum()));
        $this->assertEquals(new EnumValueNode('COMPLEX'), AST::astFromValue($this->complexValue(), $this->myEnum()));

        // Note: case sensitive
        $this->assertEquals(null, AST::astFromValue('hello', $this->myEnum()));

        // Note: Not a valid enum value
        $this->assertEquals(null, AST::astFromValue('VALUE', $this->myEnum()));
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
        $this->assertEquals($value1, AST::astFromValue(['FOO', 'BAR'], GraphQlType::listOf(GraphQlType::string())));

        $value2 = new ListValueNode([
            new EnumValueNode('HELLO'),
            new EnumValueNode('GOODBYE'),
        ]);
        $this->assertEquals($value2, AST::astFromValue(['HELLO', 'GOODBYE'], GraphQlType::listOf($this->myEnum())));
    }

    /**
     * @it converts list singletons
     */
    public function testConvertsListSingletons():void
    {
        $this->assertEquals(new StringValueNode('FOO'), AST::astFromValue('FOO', GraphQlType::listOf(GraphQlType::string())));
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
        $this->assertEquals($expected, AST::astFromValue($data, $inputObj));
        $this->assertEquals($expected, AST::astFromValue((object) $data, $inputObj));
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

        $this->assertEquals(new ObjectValueNode([
            $this->objectField('foo', new NullValueNode())
        ]), AST::astFromValue(['foo' => null], $inputObj));
    }

    private ?stdClass $complexValue;

    private function complexValue():stdClass
    {
        $complexValue = $this->complexValue;
        if ($complexValue === null)
        {
            $complexValue =  new stdClass();
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
