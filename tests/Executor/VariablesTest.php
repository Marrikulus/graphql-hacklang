<?hh //strict
//decl
namespace GraphQL\Tests\Executor;

require_once __DIR__ . '/TestClasses.php';

use GraphQL\Error\InvariantViolation;
use function Facebook\FBExpect\expect;
use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;

class VariablesTest extends \Facebook\HackTest\HackTest
{
    // Execute: Handles inputs
    // Handles objects and nullability

    /**
     * @describe using inline structs
     */
    public function testUsingInlineStructs():void
    {
        // executes with complex input:
        $doc = '
        {
          fieldWithObjectInput(input: {a: "foo", b: ["bar"], c: "baz"})
        }
        ';
        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
            'fieldWithObjectInput' => '{"a":"foo","b":["bar"],"c":"baz"}'
          ]
        ];
        expect(Executor::execute($this->schema(), $ast)->toArray())->toBePHPEqual($expected);

        // properly parses single value to list:
        $doc = '
        {
          fieldWithObjectInput(input: {a: "foo", b: "bar", c: "baz"})
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithObjectInput' => '{"a":"foo","b":["bar"],"c":"baz"}']];

        expect(Executor::execute($this->schema(), $ast)->toArray())->toBePHPEqual($expected);

        // properly parses null value to null
        $doc = '
        {
          fieldWithObjectInput(input: {a: null, b: null, c: "C", d: null})
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithObjectInput' => '{"a":null,"b":null,"c":"C","d":null}']];

        expect(Executor::execute($this->schema(), $ast)->toArray())->toBePHPEqual($expected);

        // properly parses null value in list
        $doc = '
        {
          fieldWithObjectInput(input: {b: ["A",null,"C"], c: "C"})
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithObjectInput' => '{"b":["A",null,"C"],"c":"C"}']];

        expect(Executor::execute($this->schema(), $ast)->toArray())->toBePHPEqual($expected);

        // does not use incorrect value
        $doc = '
        {
          fieldWithObjectInput(input: ["foo", "bar", "baz"])
        }
        ';
        $ast = Parser::parse($doc);
        $result = Executor::execute($this->schema(), $ast)->toArray();

        $expected = [
            'data' => ['fieldWithObjectInput' => null],
            'errors' => [[
                'message' => 'Argument "input" got invalid value ["foo", "bar", "baz"].' . "\n" .
                    'Expected "TestInputObject", found not an object.',
                'path' => ['fieldWithObjectInput']
            ]]
        ];
        expect($result)->toInclude($expected);

        // properly runs parseLiteral on complex scalar types
        $doc = '
        {
          fieldWithObjectInput(input: {c: "foo", d: "SerializedValue"})
        }
        ';
        $ast = Parser::parse($doc);
        expect(Executor::execute($this->schema(), $ast)->toArray())
            ->toBePHPEqual(['data' => ['fieldWithObjectInput' => '{"c":"foo","d":"DeserializedValue"}']]);
    }

    /**
     * @describe using variables
     */
    public function testUsingVariables():void
    {
        // executes with complex input:
        $doc = '
        query q($input:TestInputObject) {
          fieldWithObjectInput(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $params = ['input' => ['a' => 'foo', 'b' => ['bar'], 'c' => 'baz']];
        $schema = $this->schema();

        expect(Executor::execute($schema, $ast, null, null, $params)->toArray())
            ->toBePHPEqual(['data' => ['fieldWithObjectInput' => '{"a":"foo","b":["bar"],"c":"baz"}']]);

        // uses default value when not provided:
        $withDefaultsNode = Parser::parse('
          query q($input: TestInputObject = {a: "foo", b: ["bar"], c: "baz"}) {
            fieldWithObjectInput(input: $input)
          }
        ');

        $result = Executor::execute($this->schema(), $withDefaultsNode)->toArray();
        $expected = [
            'data' => ['fieldWithObjectInput' => '{"a":"foo","b":["bar"],"c":"baz"}']
        ];
        expect($result)->toBePHPEqual($expected);

        // properly parses single value to array:
        $params = ['input' => ['a' => 'foo', 'b' => 'bar', 'c' => 'baz']];
        expect(Executor::execute($schema, $ast, null, null, $params)->toArray())
            ->toBePHPEqual(['data' => ['fieldWithObjectInput' => '{"a":"foo","b":["bar"],"c":"baz"}']]);

        // executes with complex scalar input:
        $params = [ 'input' => [ 'c' => 'foo', 'd' => 'SerializedValue' ] ];
        $result = Executor::execute($schema, $ast, null, null, $params)->toArray();
        $expected = [
          'data' => [
            'fieldWithObjectInput' => '{"c":"foo","d":"DeserializedValue"}'
          ]
        ];
        expect($result)->toBePHPEqual($expected);

        // errors on null for nested non-null:
        $params = ['input' => ['a' => 'foo', 'b' => 'bar', 'c' => null]];
        $result = Executor::execute($schema, $ast, null, null, $params);
        $expected = [
            'errors' => [
                [
                    'message' =>
                        'Variable "$input" got invalid value {"a":"foo","b":"bar","c":null}.'. "\n".
                        'In field "c": Expected "String!", found null.',
                    'locations' => [['line' => 2, 'column' => 17]],
                    'category' => 'graphql'
                ]
            ]
        ];
        expect($result->toArray())->toBePHPEqual($expected);

        // errors on incorrect type:
        $params = [ 'input' => 'foo bar' ];
        $result = Executor::execute($schema, $ast, null, null, $params);
        $expected = [
            'errors' => [
                [
                    'message' =>
                        'Variable "$input" got invalid value "foo bar".' . "\n" .
                        'Expected "TestInputObject", found not an object.',
                    'locations' => [ [ 'line' => 2, 'column' => 17 ] ],
                    'category' => 'graphql',
                ]
            ]
        ];
        expect($result->toArray())->toBePHPEqual($expected);

        // errors on omission of nested non-null:
        $params = ['input' => ['a' => 'foo', 'b' => 'bar']];

        $result = Executor::execute($schema, $ast, null, null, $params);
        $expected = [
            'errors' => [
                [
                    'message' =>
                        'Variable "$input" got invalid value {"a":"foo","b":"bar"}.'. "\n".
                        'In field "c": Expected "String!", found null.',
                    'locations' => [['line' => 2, 'column' => 17]],
                    'category' => 'graphql',
                ]
            ]
        ];
        expect($result->toArray())->toBePHPEqual($expected);

        // errors on deep nested errors and with many errors
        $nestedDoc = '
          query q($input: TestNestedInputObject) {
            fieldWithNestedObjectInput(input: $input)
          }
        ';
        $nestedAst = Parser::parse($nestedDoc);
        $params = [ 'input' => [ 'na' => [ 'a' => 'foo' ] ] ];

        $result = Executor::execute($schema, $nestedAst, null, null, $params);
        $expected = [
            'errors' => [
                [
                    'message' =>
                        'Variable "$input" got invalid value {"na":{"a":"foo"}}.' . "\n" .
                        'In field "na": In field "c": Expected "String!", found null.' . "\n" .
                        'In field "nb": Expected "String!", found null.',
                    'locations' => [['line' => 2, 'column' => 19]],
                    'category' => 'graphql',
                ]
            ]
        ];
        expect($result->toArray())->toBePHPEqual($expected);


        // errors on addition of unknown input field
        $params = ['input' => [ 'a' => 'foo', 'b' => 'bar', 'c' => 'baz', 'd' => 'dog' ]];
        $result = Executor::execute($schema, $ast, null, null, $params);
        $expected = [
            'errors' => [
                [
                    'message' =>
                        'Variable "$input" got invalid value {"a":"foo","b":"bar","c":"baz","d":"dog"}.'."\n".
                        'In field "d": Expected type "ComplexScalar", found "dog".',
                    'locations' => [['line' => 2, 'column' => 17]],
                    'category' => 'graphql',
                ]
            ]
        ];
        expect($result->toArray())->toBePHPEqual($expected);
    }

    // Describe: Handles nullable scalars

    /**
     * @it allows nullable inputs to be omitted
     */
    public function testAllowsNullableInputsToBeOmitted():void
    {
        $doc = '
      {
        fieldWithNullableStringInput
      }
        ';
        $ast = Parser::parse($doc);
        $expected = [
            'data' => ['fieldWithNullableStringInput' => null]
        ];

        expect(Executor::execute($this->schema(), $ast)->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it allows nullable inputs to be omitted in a variable
     */
    public function testAllowsNullableInputsToBeOmittedInAVariable():void
    {
        $doc = '
      query SetsNullable($value: String) {
        fieldWithNullableStringInput(input: $value)
      }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithNullableStringInput' => null]];

        expect(Executor::execute($this->schema(), $ast)->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it allows nullable inputs to be omitted in an unlisted variable
     */
    public function testAllowsNullableInputsToBeOmittedInAnUnlistedVariable():void
    {
        $doc = '
      query SetsNullable {
        fieldWithNullableStringInput(input: $value)
      }
      ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithNullableStringInput' => null]];
        expect(Executor::execute($this->schema(), $ast)->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it allows nullable inputs to be set to null in a variable
     */
    public function testAllowsNullableInputsToBeSetToNullInAVariable():void
    {
        $doc = '
      query SetsNullable($value: String) {
        fieldWithNullableStringInput(input: $value)
      }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithNullableStringInput' => null]];

        expect(Executor::execute($this->schema(), $ast, null, ['value' => null])->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it allows nullable inputs to be set to a value in a variable
     */
    public function testAllowsNullableInputsToBeSetToAValueInAVariable():void
    {
        $doc = '
      query SetsNullable($value: String) {
        fieldWithNullableStringInput(input: $value)
      }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithNullableStringInput' => '"a"']];
        expect(Executor::execute($this->schema(), $ast, null, null, ['value' => 'a'])->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it allows nullable inputs to be set to a value directly
     */
    public function testAllowsNullableInputsToBeSetToAValueDirectly():void
    {
        $doc = '
      {
        fieldWithNullableStringInput(input: "a")
      }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithNullableStringInput' => '"a"']];
        expect(Executor::execute($this->schema(), $ast)->toArray())->toBePHPEqual($expected);
    }


    // Describe: Handles non-nullable scalars

    /**
     * @it allows non-nullable inputs to be omitted given a default
     */
    public function testAllowsNonNullableInputsToBeOmittedGivenADefault():void
    {
        $doc = '
        query SetsNonNullable($value: String = "default") {
          fieldWithNonNullableStringInput(input: $value)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = [
            'data' => ['fieldWithNonNullableStringInput' => '"default"']
        ];
        expect(Executor::execute($this->schema(), $ast)->toArray())->toBePHPEqual($expected);

    }

    /**
     * @it does not allow non-nullable inputs to be omitted in a variable
     */
    public function testDoesntAllowNonNullableInputsToBeOmittedInAVariable():void
    {
        $doc = '
        query SetsNonNullable($value: String!) {
          fieldWithNonNullableStringInput(input: $value)
        }
        ';
        $ast = Parser::parse($doc);
        $result = Executor::execute($this->schema(), $ast);

        $expected = [
            'errors' => [
                [
                    'message' => 'Variable "$value" of required type "String!" was not provided.',
                    'locations' => [['line' => 2, 'column' => 31]],
                    'category' => 'graphql'
                ]
            ]
        ];
        expect($result->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it does not allow non-nullable inputs to be set to null in a variable
     */
    public function testDoesNotAllowNonNullableInputsToBeSetToNullInAVariable():void
    {
        $doc = '
        query SetsNonNullable($value: String!) {
          fieldWithNonNullableStringInput(input: $value)
        }
        ';
        $ast = Parser::parse($doc);
        $result = Executor::execute($this->schema(), $ast, null, null, ['value' => null]);
        $expected = [
            'errors' => [
                [
                    'message' =>
                        'Variable "$value" got invalid value null.' . "\n".
                        'Expected "String!", found null.',
                    'locations' => [['line' => 2, 'column' => 31]],
                    'category' => 'graphql',
                ]
            ]
        ];
        expect($result->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it allows non-nullable inputs to be set to a value in a variable
     */
    public function testAllowsNonNullableInputsToBeSetToAValueInAVariable():void
    {
        $doc = '
        query SetsNonNullable($value: String!) {
          fieldWithNonNullableStringInput(input: $value)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithNonNullableStringInput' => '"a"']];
        expect(Executor::execute($this->schema(), $ast, null, null, ['value' => 'a'])->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it allows non-nullable inputs to be set to a value directly
     */
    public function testAllowsNonNullableInputsToBeSetToAValueDirectly():void
    {
        $doc = '
      {
        fieldWithNonNullableStringInput(input: "a")
      }
      ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithNonNullableStringInput' => '"a"']];

        expect(Executor::execute($this->schema(), $ast)->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it reports error for missing non-nullable inputs
     */
    public function testReportsErrorForMissingNonNullableInputs():void
    {
        $doc = '
      {
        fieldWithNonNullableStringInput
      }
        ';
        $ast = Parser::parse($doc);
        $expected = [
            'data' => ['fieldWithNonNullableStringInput' => null],
            'errors' => [[
                'message' => 'Argument "input" of required type "String!" was not provided.',
                'locations' => [ [ 'line' => 3, 'column' => 9 ] ],
                'path' => [ 'fieldWithNonNullableStringInput' ],
                'category' => 'graphql',
            ]]
        ];
        expect(Executor::execute($this->schema(), $ast)->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it reports error for array passed into string input
     */
    public function testReportsErrorForArrayPassedIntoStringInput():void
    {

        $doc = '
        query SetsNonNullable($value: String!) {
          fieldWithNonNullableStringInput(input: $value)
        }
        ';
        $ast = Parser::parse($doc);
        $variables = ['value' => [1, 2, 3]];

        $expected = [
            'errors' => [[
                'message' =>
                    'Variable "$value" got invalid value [1,2,3].' . "\n" .
                    'Expected type "String", found array(3).',
                'category' => 'graphql',
                'locations' => [
                    ['line' => 2, 'column' => 31]
                ]
            ]]
        ];

        expect(Executor::execute($this->schema(), $ast, null, null, $variables)->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it serializing an array via GraphQLString throws TypeError
     */
    public function testSerializingAnArrayViaGraphQLStringThrowsTypeError():void
    {
        $this->setExpectedException(
            InvariantViolation::class,
            'String cannot represent non scalar value: array(3)'
        );
        GraphQlType::string()->serialize([1, 2, 3]);
    }

    /**
     * @it reports error for non-provided variables for non-nullable inputs
     */
    public function testReportsErrorForNonProvidedVariablesForNonNullableInputs():void
    {
        // Note: this test would typically fail validation before encountering
        // this execution error, however for queries which previously validated
        // and are being run against a new schema which have introduced a breaking
        // change to make a formerly non-required argument required, this asserts
        // failure before allowing the underlying code to receive a non-null value.
        $doc = '
      {
        fieldWithNonNullableStringInput(input: $foo)
      }
        ';
        $ast = Parser::parse($doc);

        $expected = [
            'data' => ['fieldWithNonNullableStringInput' => null],
            'errors' => [[
                'message' =>
                    'Argument "input" of required type "String!" was provided the ' .
                    'variable "$foo" which was not provided a runtime value.',
                'locations' => [['line' => 3, 'column' => 48]],
                'path' => ['fieldWithNonNullableStringInput'],
                'category' => 'graphql',
            ]]
        ];
        expect(Executor::execute($this->schema(), $ast)->toArray())->toBePHPEqual($expected);
    }

    // Describe: Handles lists and nullability

    /**
     * @it allows lists to be null
     */
    public function testAllowsListsToBeNull():void
    {
        $doc = '
        query q($input:[String]) {
          list(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['list' => null]];

        expect(Executor::execute($this->schema(), $ast, null, ['input' => null])->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it allows lists to contain values
     */
    public function testAllowsListsToContainValues():void
    {
        $doc = '
        query q($input:[String]) {
          list(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['list' => '["A"]']];
        expect(Executor::execute($this->schema(), $ast, null, null, ['input' => ['A']])->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it allows lists to contain null
     */
    public function testAllowsListsToContainNull():void
    {
        $doc = '
        query q($input:[String]) {
          list(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['list' => '["A",null,"B"]']];
        expect(Executor::execute($this->schema(), $ast, null, null, ['input' => ['A',null,'B']])->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it does not allow non-null lists to be null
     */
    public function testDoesNotAllowNonNullListsToBeNull():void
    {
        $doc = '
        query q($input:[String]!) {
          nnList(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $result = Executor::execute($this->schema(), $ast, null, null, ['input' => null]);
        $expected = [
            'errors' => [
                [
                    'message' =>
                        'Variable "$input" got invalid value null.' . "\n" .
                        'Expected "[String]!", found null.',
                    'locations' => [['line' => 2, 'column' => 17]],
                    'category' => 'graphql',
                ]
            ]
        ];
        expect($result->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it allows non-null lists to contain values
     */
    public function testAllowsNonNullListsToContainValues():void
    {
        $doc = '
        query q($input:[String]!) {
          nnList(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['nnList' => '["A"]']];
        expect(Executor::execute($this->schema(), $ast, null, null, ['input' => 'A'])->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it allows non-null lists to contain null
     */
    public function testAllowsNonNullListsToContainNull():void
    {
        $doc = '
        query q($input:[String]!) {
          nnList(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['nnList' => '["A",null,"B"]']];

        expect(Executor::execute($this->schema(), $ast, null, null, ['input' => ['A',null,'B']])->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it allows lists of non-nulls to be null
     */
    public function testAllowsListsOfNonNullsToBeNull():void
    {
        $doc = '
        query q($input:[String!]) {
          listNN(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['listNN' => null]];
        expect(Executor::execute($this->schema(), $ast, null, ['input' => null])->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it allows lists of non-nulls to contain values
     */
    public function testAllowsListsOfNonNullsToContainValues():void
    {
        $doc = '
        query q($input:[String!]) {
          listNN(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['listNN' => '["A"]']];

        expect(Executor::execute($this->schema(), $ast, null, null, ['input' => 'A'])->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it does not allow lists of non-nulls to contain null
     */
    public function testDoesNotAllowListsOfNonNullsToContainNull():void
    {
        $doc = '
        query q($input:[String!]) {
          listNN(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $result = Executor::execute($this->schema(), $ast, null, null, ['input' => ['A', null, 'B']]);
        $expected = [
            'errors' => [
                [
                    'message' =>
                        'Variable "$input" got invalid value ["A",null,"B"].' . "\n" .
                        'In element #1: Expected "String!", found null.',
                    'locations' => [ ['line' => 2, 'column' => 17] ],
                    'category' => 'graphql',
                ]
            ]
        ];
        expect($result->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it does not allow non-null lists of non-nulls to be null
     */
    public function testDoesNotAllowNonNullListsOfNonNullsToBeNull():void
    {
        $doc = '
        query q($input:[String!]!) {
          nnListNN(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $result = Executor::execute($this->schema(), $ast, null, null, ['input' => null]);
        $expected = [
            'errors' => [
                [
                    'message' =>
                        'Variable "$input" got invalid value null.' . "\n" .
                        'Expected "[String!]!", found null.',
                    'locations' => [ ['line' => 2, 'column' => 17] ],
                    'category' => 'graphql',
                ]
            ]
        ];
        expect($result->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it allows non-null lists of non-nulls to contain values
     */
    public function testAllowsNonNullListsOfNonNullsToContainValues():void
    {
        $doc = '
        query q($input:[String!]!) {
          nnListNN(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['nnListNN' => '["A"]']];
        expect(Executor::execute($this->schema(), $ast, null, null, ['input' => ['A']])->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it does not allow non-null lists of non-nulls to contain null
     */
    public function testDoesNotAllowNonNullListsOfNonNullsToContainNull():void
    {
        $doc = '
        query q($input:[String!]!) {
          nnListNN(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $result = Executor::execute($this->schema(), $ast, null, null, ['input' => ['A', null, 'B']]);
        $expected = [
            'errors' => [
                [
                    'message' =>
                        'Variable "$input" got invalid value ["A",null,"B"].'."\n".
                        'In element #1: Expected "String!", found null.',
                    'locations' => [ ['line' => 2, 'column' => 17] ],
                    'category' => 'graphql',
                ]
            ]
        ];
        expect($result->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it does not allow invalid types to be used as values
     */
    public function testDoesNotAllowInvalidTypesToBeUsedAsValues():void
    {
        $doc = '
        query q($input: TestType!) {
          fieldWithObjectInput(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $vars = [ 'input' => [ 'list' => [ 'A', 'B' ] ] ];
        $result = Executor::execute($this->schema(), $ast, null, null, $vars);
        $expected = [
            'errors' => [
                [
                    'message' =>
                        'Variable "$input" expected value of type "TestType!" which cannot ' .
                        'be used as an input type.',
                    'locations' => [['line' => 2, 'column' => 25]],
                    'category' => 'graphql',
                ]
            ]
        ];
        expect($result->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it does not allow unknown types to be used as values
     */
    public function testDoesNotAllowUnknownTypesToBeUsedAsValues():void
    {
        $doc = '
        query q($input: UnknownType!) {
          fieldWithObjectInput(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $vars = ['input' => 'whoknows'];

        $result = Executor::execute($this->schema(), $ast, null, null, $vars);
        $expected = [
            'errors' => [
                [
                    'message' =>
                        'Variable "$input" expected value of type "UnknownType!" which ' .
                        'cannot be used as an input type.',
                    'locations' => [['line' => 2, 'column' => 25]],
                    'category' => 'graphql',
                ]
            ]
        ];
        expect($result->toArray())->toBePHPEqual($expected);
    }

    // Describe: Execute: Uses argument default values
    /**
     * @it when no argument provided
     */
    public function testWhenNoArgumentProvided():void
    {
        $ast = Parser::parse('{
        fieldWithDefaultArgumentValue
        }');

        expect(Executor::execute($this->schema(), $ast)->toArray())
            ->toBePHPEqual(['data' => ['fieldWithDefaultArgumentValue' => '"Hello World"']]);
    }

    /**
     * @it when omitted variable provided
     */
    public function testWhenOmittedVariableProvided():void
    {
        $ast = Parser::parse('query optionalVariable($optional: String) {
            fieldWithDefaultArgumentValue(input: $optional)
        }');

        expect(Executor::execute($this->schema(), $ast)->toArray())
            ->toBePHPEqual(['data' => ['fieldWithDefaultArgumentValue' => '"Hello World"']]);
    }

    /**
     * @it not when argument cannot be coerced
     */
    public function testNotWhenArgumentCannotBeCoerced():void
    {
        $ast = Parser::parse('{
            fieldWithDefaultArgumentValue(input: WRONG_TYPE)
        }');

        $expected = [
            'data' => ['fieldWithDefaultArgumentValue' => null],
            'errors' => [[
                'message' =>
                    'Argument "input" got invalid value WRONG_TYPE.' . "\n" .
                    'Expected type "String", found WRONG_TYPE.',
                'locations' => [ [ 'line' => 2, 'column' => 50 ] ],
                'path' => [ 'fieldWithDefaultArgumentValue' ],
                'category' => 'graphql',
            ]]
        ];

        expect(Executor::execute($this->schema(), $ast)->toArray())
            ->toBePHPEqual($expected);
    }


    public function schema()
    {
        $ComplexScalarType = ComplexScalar::create();

        $TestInputObject = new InputObjectType([
            'name' => 'TestInputObject',
            'fields' => [
                'a' => ['type' => GraphQlType::string()],
                'b' => ['type' => GraphQlType::listOf(GraphQlType::string())],
                'c' => ['type' => GraphQlType::nonNull(GraphQlType::string())],
                'd' => ['type' => $ComplexScalarType],
            ]
        ]);

        $TestNestedInputObject = new InputObjectType([
            'name' => 'TestNestedInputObject',
            'fields' => [
                'na' => [ 'type' => GraphQlType::nonNull($TestInputObject) ],
                'nb' => [ 'type' => GraphQlType::nonNull(GraphQlType::string()) ],
            ],
        ]);

        $TestType = new ObjectType([
            'name' => 'TestType',
            'fields' => [
                'fieldWithObjectInput' => [
                    'type' => GraphQlType::string(),
                    'args' => ['input' => ['type' => $TestInputObject]],
                    'resolve' => function ($_, $args) {
                        return isset($args['input']) ? \json_encode($args['input']) : null;
                    }
                ],
                'fieldWithNullableStringInput' => [
                    'type' => GraphQlType::string(),
                    'args' => ['input' => ['type' => GraphQlType::string()]],
                    'resolve' => function ($_, $args) {
                        return isset($args['input']) ?  \json_encode($args['input']) : null;
                    }
                ],
                'fieldWithNonNullableStringInput' => [
                    'type' => GraphQlType::string(),
                    'args' => ['input' => ['type' => GraphQlType::nonNull(GraphQlType::string())]],
                    'resolve' => function ($_, $args) {
                        return isset($args['input']) ? \json_encode($args['input']) : null;
                    }
                ],
                'fieldWithDefaultArgumentValue' => [
                    'type' => GraphQlType::string(),
                    'args' => [ 'input' => [ 'type' => GraphQlType::string(), 'defaultValue' => 'Hello World' ]],
                    'resolve' => function($_, $args) {
                        return isset($args['input']) ? \json_encode($args['input']) : null;
                    }
                ],
                'fieldWithNestedInputObject' => [
                    'type' => GraphQlType::string(),
                    'args' => [
                        'input' => [
                            'type' => $TestNestedInputObject,
                            'defaultValue' => 'Hello World'
                        ]
                    ],
                    'resolve' => function($_, $args) {
                        return isset($args['input']) ? \json_encode($args['input']) : null;
                    }
                ],
                'list' => [
                    'type' => GraphQlType::string(),
                    'args' => ['input' => ['type' => GraphQlType::listOf(GraphQlType::string())]],
                    'resolve' => function ($_, $args) {
                        return isset($args['input']) ? \json_encode($args['input']) : null;
                    }
                ],
                'nnList' => [
                    'type' => GraphQlType::string(),
                    'args' => ['input' => ['type' => GraphQlType::nonNull(GraphQlType::listOf(GraphQlType::string()))]],
                    'resolve' => function ($_, $args) {
                        return isset($args['input']) ? \json_encode($args['input']) : null;
                    }
                ],
                'listNN' => [
                    'type' => GraphQlType::string(),
                    'args' => ['input' => ['type' => GraphQlType::listOf(GraphQlType::nonNull(GraphQlType::string()))]],
                    'resolve' => function ($_, $args) {
                        return isset($args['input']) ? \json_encode($args['input']) : null;
                    }
                ],
                'nnListNN' => [
                    'type' => GraphQlType::string(),
                    'args' => ['input' => ['type' => GraphQlType::nonNull(GraphQlType::listOf(GraphQlType::nonNull(GraphQlType::string())))]],
                    'resolve' => function ($_, $args) {
                        return isset($args['input']) ? \json_encode($args['input']) : null;
                    }
                ],
            ]
        ]);

        $schema = new Schema(['query' => $TestType]);
        return $schema;
    }
}
