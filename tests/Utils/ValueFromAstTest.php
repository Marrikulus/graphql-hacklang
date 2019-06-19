<?hh //strict
//decl
namespace GraphQL\Tests\Utils;

use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\GraphQlType;
use GraphQL\Utils\Utils;
use GraphQL\Utils\AST;

class ValueFromAstTest extends \PHPUnit_Framework_TestCase
{
    private function runTestCase(GraphQlType $type, string $valueText, mixed $expected):void
    {
        $this->assertEquals($expected, AST::valueFromAST(Parser::parseValueString($valueText), $type));
    }

    private function runTestCaseWithVars(array<string, mixed> $variables, GraphQlType $type, string $valueText, mixed $expected):void
    {
        $this->assertEquals($expected, AST::valueFromAST(Parser::parseValueString($valueText), $type, $variables));
    }

    /**
     * @it rejects empty input
     */
    public function testRejectsEmptyInput():void
    {
        $this->assertEquals(Utils::undefined(), AST::valueFromAST(null, GraphQlType::boolean()));
    }

    /**
     * @it converts according to input coercion rules
     */
    public function testConvertsAccordingToInputCoercionRules():void
    {
        $this->runTestCase(GraphQlType::boolean(), 'true', true);
        $this->runTestCase(GraphQlType::boolean(), 'false', false);
        $this->runTestCase(GraphQlType::int(), '123', 123);
        $this->runTestCase(GraphQlType::float(), '123', 123);
        $this->runTestCase(GraphQlType::float(), '123.456', 123.456);
        $this->runTestCase(GraphQlType::string(), '"abc123"', 'abc123');
        $this->runTestCase(GraphQlType::id(), '123456', '123456');
        $this->runTestCase(GraphQlType::id(), '"123456"', '123456');
    }

    /**
     * @it does not convert when input coercion rules reject a value
     */
    public function testDoesNotConvertWhenInputCoercionRulesRejectAValue():void
    {
        $undefined = Utils::undefined();

        $this->runTestCase(GraphQlType::boolean(), '123', $undefined);
        $this->runTestCase(GraphQlType::int(), '123.456', $undefined);
        $this->runTestCase(GraphQlType::int(), 'true', $undefined);
        $this->runTestCase(GraphQlType::int(), '"123"', $undefined);
        $this->runTestCase(GraphQlType::float(), '"123"', $undefined);
        $this->runTestCase(GraphQlType::string(), '123', $undefined);
        $this->runTestCase(GraphQlType::string(), 'true', $undefined);
        $this->runTestCase(GraphQlType::id(), '123.456', $undefined);
    }

    /**
     * @it converts enum values according to input coercion rules
     */
    public function testConvertsEnumValuesAccordingToInputCoercionRules():void
    {
        $testEnum = new EnumType([
            'name' => 'TestColor',
            'values' => [
                'RED' => ['value' => 1],
                'GREEN' => ['value' => 2],
                'BLUE' => ['value' => 3],
                'NULL' => ['value' => null],
            ]
        ]);

        $this->runTestCase($testEnum, 'RED', 1);
        $this->runTestCase($testEnum, 'BLUE', 3);
        $this->runTestCase($testEnum, '3', Utils::undefined());
        $this->runTestCase($testEnum, '"BLUE"', Utils::undefined());
        $this->runTestCase($testEnum, 'null', null);
        $this->runTestCase($testEnum, 'NULL', null);
    }

    /**
     * @it coerces to null unless non-null
     */
    public function testCoercesToNullUnlessNonNull():void
    {
        $this->runTestCase(GraphQlType::boolean(), 'null', null);
        $this->runTestCase(GraphQlType::nonNull(GraphQlType::boolean()), 'null', Utils::undefined());
    }

    /**
     * @it coerces lists of values
     */
    public function testCoercesListsOfValues():void
    {
        $listOfBool = GraphQlType::listOf(GraphQlType::boolean());
        $undefined = Utils::undefined();

        $this->runTestCase($listOfBool, 'true', [ true ]);
        $this->runTestCase($listOfBool, '123', $undefined);
        $this->runTestCase($listOfBool, 'null', null);
        $this->runTestCase($listOfBool, '[true, false]', [ true, false ]);
        $this->runTestCase($listOfBool, '[true, 123]', $undefined);
        $this->runTestCase($listOfBool, '[true, null]', [ true, null ]);
        $this->runTestCase($listOfBool, '{ true: true }', $undefined);
    }

    /**
     * @it coerces non-null lists of values
     */
    public function testCoercesNonNullListsOfValues():void
    {
        $nonNullListOfBool = GraphQlType::nonNull(GraphQlType::listOf(GraphQlType::boolean()));
        $undefined = Utils::undefined();

        $this->runTestCase($nonNullListOfBool, 'true', [ true ]);
        $this->runTestCase($nonNullListOfBool, '123', $undefined);
        $this->runTestCase($nonNullListOfBool, 'null', $undefined);
        $this->runTestCase($nonNullListOfBool, '[true, false]', [ true, false ]);
        $this->runTestCase($nonNullListOfBool, '[true, 123]', $undefined);
        $this->runTestCase($nonNullListOfBool, '[true, null]', [ true, null ]);
    }

    /**
     * @it coerces lists of non-null values
     */
    public function testCoercesListsOfNonNullValues():void
    {
        $listOfNonNullBool = GraphQlType::listOf(GraphQlType::nonNull(GraphQlType::boolean()));
        $undefined = Utils::undefined();

        $this->runTestCase($listOfNonNullBool, 'true', [ true ]);
        $this->runTestCase($listOfNonNullBool, '123', $undefined);
        $this->runTestCase($listOfNonNullBool, 'null', null);
        $this->runTestCase($listOfNonNullBool, '[true, false]', [ true, false ]);
        $this->runTestCase($listOfNonNullBool, '[true, 123]', $undefined);
        $this->runTestCase($listOfNonNullBool, '[true, null]', $undefined);
    }

    /**
     * @it coerces non-null lists of non-null values
     */
    public function testCoercesNonNullListsOfNonNullValues():void
    {
        $nonNullListOfNonNullBool = GraphQlType::nonNull(GraphQlType::listOf(GraphQlType::nonNull(GraphQlType::boolean())));
        $undefined = Utils::undefined();

        $this->runTestCase($nonNullListOfNonNullBool, 'true', [ true ]);
        $this->runTestCase($nonNullListOfNonNullBool, '123', $undefined);
        $this->runTestCase($nonNullListOfNonNullBool, 'null', $undefined);
        $this->runTestCase($nonNullListOfNonNullBool, '[true, false]', [ true, false ]);
        $this->runTestCase($nonNullListOfNonNullBool, '[true, 123]', $undefined);
        $this->runTestCase($nonNullListOfNonNullBool, '[true, null]', $undefined);
    }

    private ?InputObjectType $inputObj;

    private function inputObj(): InputObjectType
    {
        return $this->inputObj ?? $this->inputObj = new InputObjectType([
            'name' => 'TestInput',
            'fields' => [
                'int' => [ 'type' => GraphQlType::int(), 'defaultValue' => 42 ],
                'bool' => [ 'type' => GraphQlType::boolean() ],
                'requiredBool' => [ 'type' => GraphQlType::nonNull(GraphQlType::boolean()) ],
            ]
        ]);
    }

    /**
     * @it coerces input objects according to input coercion rules
     */
    public function testCoercesInputObjectsAccordingToInputCoercionRules():void
    {
        $testInputObj = $this->inputObj();
        $undefined = Utils::undefined();

        $this->runTestCase($testInputObj, 'null', null);
        $this->runTestCase($testInputObj, '123', $undefined);
        $this->runTestCase($testInputObj, '[]', $undefined);
        $this->runTestCase($testInputObj, '{ int: 123, requiredBool: false }', ['int' => 123, 'requiredBool' => false]);
        $this->runTestCase($testInputObj, '{ bool: true, requiredBool: false }', [ 'int' => 42, 'bool' => true, 'requiredBool' => false ]);
        $this->runTestCase($testInputObj, '{ int: true, requiredBool: true }', $undefined);
        $this->runTestCase($testInputObj, '{ requiredBool: null }', $undefined);
        $this->runTestCase($testInputObj, '{ bool: true }', $undefined);
    }

    /**
     * @it accepts variable values assuming already coerced
     */
    public function testAcceptsVariableValuesAssumingAlreadyCoerced():void
    {
        $this->runTestCaseWithVars([], GraphQlType::boolean(), '$var', Utils::undefined());
        $this->runTestCaseWithVars([ 'var' => true ], GraphQlType::boolean(), '$var', true);
        $this->runTestCaseWithVars([ 'var' => null ], GraphQlType::boolean(), '$var', null);
    }

    /**
     * @it asserts variables are provided as items in lists
     */
    public function testAssertsVariablesAreProvidedAsItemsInLists():void
    {
        $listOfBool = GraphQlType::listOf(GraphQlType::boolean());
        $listOfNonNullBool = GraphQlType::listOf(GraphQlType::nonNull(GraphQlType::boolean()));

        $this->runTestCaseWithVars([], $listOfBool, '[ $foo ]', [ null ]);
        $this->runTestCaseWithVars([], $listOfNonNullBool, '[ $foo ]', Utils::undefined());
        $this->runTestCaseWithVars([ 'foo' => true ], $listOfNonNullBool, '[ $foo ]', [ true ]);
        // Note: variables are expected to have already been coerced, so we
        // do not expect the singleton wrapping behavior for variables.
        $this->runTestCaseWithVars([ 'foo' => true ], $listOfNonNullBool, '$foo', true);
        $this->runTestCaseWithVars([ 'foo' => [ true ] ], $listOfNonNullBool, '$foo', [ true ]);
    }

    /**
     * @it omits input object fields for unprovided variables
     */
    public function testOmitsInputObjectFieldsForUnprovidedVariables():void
    {
        $testInputObj = $this->inputObj();

        $this->runTestCaseWithVars(
            [],
            $testInputObj,
            '{ int: $foo, bool: $foo, requiredBool: true }',
            [ 'int' => 42, 'requiredBool' => true ]
        );
        $this->runTestCaseWithVars(
            [],
            $testInputObj,
            '{ requiredBool: $foo }',
            Utils::undefined()
        );
        $this->runTestCaseWithVars(
            [ 'foo' => true ],
            $testInputObj,
            '{ requiredBool: $foo }',
            [ 'int' => 42, 'requiredBool' => true ]
        );
    }
}
