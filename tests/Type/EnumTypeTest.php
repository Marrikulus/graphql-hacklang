<?hh //strict
//decl
namespace GraphQL\Tests\Type;

use GraphQL\Error\Error;
use function Facebook\FBExpect\expect;
use GraphQL\GraphQL;
use GraphQL\Language\SourceLocation;
use GraphQL\Schema;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;
use GraphQL\Type\Introspection;

class EnumTypeTest extends \Facebook\HackTest\HackTest
{
    /**
     * @var Schema
     */
    private $schema;

    /**
     * @var EnumType
     */
    private $ComplexEnum;

    private $Complex1;

    private $Complex2;

    public async function beforeEachTestAsync(): Awaitable<void>
    {
        $ColorType = new EnumType([
            'name' => 'Color',
            'values' => [
                'RED' => ['value' => 0],
                'GREEN' => ['value' => 1],
                'BLUE' => ['value' => 2],
            ]
        ]);

        $simpleEnum = new EnumType([
            'name' => 'SimpleEnum',
            'values' => [
                'ONE', 'TWO', 'THREE'
            ]
        ]);

        $Complex1 = ['someRandomFunction' => function() {}];
        $Complex2 = new \ArrayObject(['someRandomValue' => 123]);

        $ComplexEnum = new EnumType([
            'name' => 'Complex',
            'values' => [
                'ONE' => ['value' => $Complex1],
                'TWO' => ['value' => $Complex2]
            ]
        ]);

        $QueryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'colorEnum' => [
                    'type' => $ColorType,
                    'args' => [
                        'fromEnum' => ['type' => $ColorType],
                        'fromInt' => ['type' => GraphQlType::int()],
                        'fromString' => ['type' => GraphQlType::string()],
                    ],
                    'resolve' => function ($value, $args) {
                        if (isset($args['fromInt'])) {
                            return $args['fromInt'];
                        }
                        if (isset($args['fromString'])) {
                            return $args['fromString'];
                        }
                        if (isset($args['fromEnum'])) {
                            return $args['fromEnum'];
                        }
                    }
                ],
                'simpleEnum' => [
                    'type' => $simpleEnum,
                    'args' => [
                        'fromName' => ['type' => GraphQlType::string()],
                        'fromValue' => ['type' => GraphQlType::string()]
                    ],
                    'resolve' => function($value, $args) {
                        if (isset($args['fromName'])) {
                            return $args['fromName'];
                        }
                        if (isset($args['fromValue'])) {
                            return $args['fromValue'];
                        }
                    }
                ],
                'colorInt' => [
                    'type' => GraphQlType::int(),
                    'args' => [
                        'fromEnum' => ['type' => $ColorType],
                        'fromInt' => ['type' => GraphQlType::int()],
                    ],
                    'resolve' => function ($value, $args) {
                        if (isset($args['fromInt'])) {
                            return $args['fromInt'];
                        }
                        if (isset($args['fromEnum'])) {
                            return $args['fromEnum'];
                        }
                    }
                ],
                'complexEnum' => [
                    'type' => $ComplexEnum,
                    'args' => [
                        'fromEnum' => [
                            'type' => $ComplexEnum,
                            // Note: defaultValue is provided an *internal* representation for
                            // Enums, rather than the string name.
                            'defaultValue' => $Complex1
                        ],
                        'provideGoodValue' => [
                            'type' => GraphQlType::boolean(),
                        ],
                        'provideBadValue' => [
                            'type' => GraphQlType::boolean()
                        ]
                    ],
                    'resolve' => function($value, $args) use ($Complex1, $Complex2) {
                        if (!empty($args['provideGoodValue'])) {
                            // Note: this is one of the references of the internal values which
                            // ComplexEnum allows.
                            return $Complex2;
                        }
                        if (!empty($args['provideBadValue'])) {
                            // Note: similar shape, but not the same *reference*
                            // as Complex2 above. Enum internal values require === equality.
                            return new \ArrayObject(['someRandomValue' => 123]);
                        }
                        return $args['fromEnum'];
                    }
                ]
            ]
        ]);

        $MutationType = new ObjectType([
            'name' => 'Mutation',
            'fields' => [
                'favoriteEnum' => [
                    'type' => $ColorType,
                    'args' => ['color' => ['type' => $ColorType]],
                    'resolve' => function ($value, $args) {
                        return isset($args['color']) ? $args['color'] : null;
                    }
                ]
            ]
        ]);

        $SubscriptionType = new ObjectType([
            'name' => 'Subscription',
            'fields' => [
                'subscribeToEnum' => [
                    'type' => $ColorType,
                    'args' => ['color' => ['type' => $ColorType]],
                    'resolve' => function ($value, $args) {
                        return isset($args['color']) ? $args['color'] : null;
                    }
                ]
            ]
        ]);

        $this->Complex1 = $Complex1;
        $this->Complex2 = $Complex2;
        $this->ComplexEnum = $ComplexEnum;

        $this->schema = new Schema([
            'query' => $QueryType,
            'mutation' => $MutationType,
            'subscription' => $SubscriptionType
        ]);
    }

    // Describe: Type System: Enum Values

    /**
     * @it accepts enum literals as input
     */
    public function testAcceptsEnumLiteralsAsInput():void
    {
        expect(GraphQL::execute($this->schema, '{ colorInt(fromEnum: GREEN) }'))
            ->toBePHPEqual(['data' => ['colorInt' => 1]]);
    }

    /**
     * @it enum may be output type
     */
    public function testEnumMayBeOutputType():void
    {
        expect(GraphQL::execute($this->schema, '{ colorEnum(fromInt: 1) }'))
            ->toBePHPEqual(['data' => ['colorEnum' => 'GREEN']]);
    }

    /**
     * @it enum may be both input and output type
     */
    public function testEnumMayBeBothInputAndOutputType():void
    {
        expect(GraphQL::execute($this->schema, '{ colorEnum(fromEnum: GREEN) }'))
            ->toBePHPEqual(['data' => ['colorEnum' => 'GREEN']]);
    }

    /**
     * @it does not accept string literals
     */
    public function testDoesNotAcceptStringLiterals():void
    {
        $this->expectFailure(
            '{ colorEnum(fromEnum: "GREEN") }',
            null,
            [
                'message' => "Argument \"fromEnum\" got invalid value \"GREEN\".\nExpected type \"Color\", found \"GREEN\".",
                'locations' => [new SourceLocation(1, 23)]
            ]
        );
    }

    /**
     * @it does not accept incorrect internal value
     */
    public function testDoesNotAcceptIncorrectInternalValue():void
    {
        $this->expectFailure(
            '{ colorEnum(fromString: "GREEN") }',
            null,
            [
                'message' => 'Expected a value of type "Color" but received: "GREEN"',
                'locations' => [new SourceLocation(1, 3)]
            ]
        );
    }

    /**
     * @it does not accept internal value in place of enum literal
     */
    public function testDoesNotAcceptInternalValueInPlaceOfEnumLiteral():void
    {
        $this->expectFailure(
            '{ colorEnum(fromEnum: 1) }',
            null,
            "Argument \"fromEnum\" got invalid value 1.\nExpected type \"Color\", found 1."
        );
    }

    /**
     * @it does not accept enum literal in place of int
     */
    public function testDoesNotAcceptEnumLiteralInPlaceOfInt():void
    {
        $this->expectFailure(
            '{ colorEnum(fromInt: GREEN) }',
            null,
            "Argument \"fromInt\" got invalid value GREEN.\nExpected type \"Int\", found GREEN."
        );
    }

    /**
     * @it accepts JSON string as enum variable
     */
    public function testAcceptsJSONStringAsEnumVariable():void
    {
        expect(GraphQL::execute(
                $this->schema,
                'query test($color: Color!) { colorEnum(fromEnum: $color) }',
                null,
                null,
                ['color' => 'BLUE']
            ))->toBePHPEqual(['data' => ['colorEnum' => 'BLUE']]);
    }

    /**
     * @it accepts enum literals as input arguments to mutations
     */
    public function testAcceptsEnumLiteralsAsInputArgumentsToMutations():void
    {
        expect(GraphQL::execute(
                $this->schema,
                'mutation x($color: Color!) { favoriteEnum(color: $color) }',
                null,
                null,
                ['color' => 'GREEN']
            ))->toBePHPEqual(['data' => ['favoriteEnum' => 'GREEN']]);
    }

    /**
     * @it accepts enum literals as input arguments to subscriptions
     * @todo
     */
    public function testAcceptsEnumLiteralsAsInputArgumentsToSubscriptions():void
    {
        expect(GraphQL::execute(
                $this->schema,
                'subscription x($color: Color!) { subscribeToEnum(color: $color) }',
                null,
                null,
                ['color' => 'GREEN']
            ))->toBePHPEqual(['data' => ['subscribeToEnum' => 'GREEN']]);
    }

    /**
     * @it does not accept internal value as enum variable
     */
    public function testDoesNotAcceptInternalValueAsEnumVariable():void
    {
        $this->expectFailure(
            'query test($color: Color!) { colorEnum(fromEnum: $color) }',
            ['color' => 2],
            "Variable \"\$color\" got invalid value 2.\nExpected type \"Color\", found 2."
        );
    }

    /**
     * @it does not accept string variables as enum input
     */
    public function testDoesNotAcceptStringVariablesAsEnumInput():void
    {
        $this->expectFailure(
            'query test($color: String!) { colorEnum(fromEnum: $color) }',
            ['color' => 'BLUE'],
            'Variable "$color" of type "String!" used in position expecting type "Color".'
        );
    }

    /**
     * @it does not accept internal value variable as enum input
     */
    public function testDoesNotAcceptInternalValueVariableSsEnumInput():void
    {
        $this->expectFailure(
            'query test($color: Int!) { colorEnum(fromEnum: $color) }',
            ['color' => 2],
            'Variable "$color" of type "Int!" used in position ' . 'expecting type "Color".'
        );
    }

    /**
     * @it enum value may have an internal value of 0
     */
    public function testEnumValueMayHaveAnInternalValueOf0()
    {
        expect(GraphQL::execute($this->schema, "{
                colorEnum(fromEnum: RED)
                colorInt(fromEnum: RED)
            }"))
        ->toBePHPEqual(['data' => ['colorEnum' => 'RED', 'colorInt' => 0]]);
    }

    /**
     * @it enum inputs may be nullable
     */
    public function testEnumInputsMayBeNullable():void
    {
        expect(GraphQL::execute($this->schema, "{
                colorEnum
                colorInt
            }"))
            ->toBePHPEqual(['data' => ['colorEnum' => null, 'colorInt' => null]]        );
    }

    /**
     * @it presents a getValues() API for complex enums
     */
    public function testPresentsGetValuesAPIForComplexEnums():void
    {
        $ComplexEnum = $this->ComplexEnum;
        $values = $ComplexEnum->getValues();

        expect(\count($values))->toBePHPEqual(2);
        expect($values[0]->name)->toBePHPEqual('ONE');
        expect($values[0]->value)->toBePHPEqual($this->Complex1);
        expect($values[1]->name)->toBePHPEqual('TWO');
        expect($values[1]->value)->toBePHPEqual($this->Complex2);
    }

    /**
     * @it presents a getValue() API for complex enums
     */
    public function testPresentsGetValueAPIForComplexEnums():void
    {
        $oneValue = $this->ComplexEnum->getValue('ONE');
        expect($oneValue->name)->toBePHPEqual('ONE');
        expect($oneValue->value)->toBePHPEqual($this->Complex1);

        $badUsage = $this->ComplexEnum->getValue($this->Complex1);
        expect($badUsage)->toBePHPEqual(null);
    }

    /**
     * @it may be internally represented with complex values
     */
    public function testMayBeInternallyRepresentedWithComplexValues():void
    {
        $result = GraphQL::executeAndReturnResult($this->schema, '{
        first: complexEnum
        second: complexEnum(fromEnum: TWO)
        good: complexEnum(provideGoodValue: true)
        bad: complexEnum(provideBadValue: true)
        }')->toArray(1);

        $expected = [
            'data' => [
                'first' => 'ONE',
                'second' => 'TWO',
                'good' => 'TWO',
                'bad' => null
            ],
            'errors' => [[
                'debugMessage' =>
                    'Expected a value of type "Complex" but received: instance of ArrayObject',
                'locations' => [['line' => 5, 'column' => 9]]
            ]]
        ];

        expect($result)->toInclude($expected);
    }

    /**
     * @it can be introspected without error
     */
    public function testCanBeIntrospectedWithoutError():void
    {
        $result = GraphQL::execute($this->schema, Introspection::getIntrospectionQuery());
        expect($result)->toNotContainKey('errors');
    }

    public function testAllowsSimpleArrayAsValues():void
    {
        $q = '{
            first: simpleEnum(fromName: "ONE")
            second: simpleEnum(fromValue: "TWO")
            third: simpleEnum(fromValue: "WRONG")
        }';

        $result = GraphQL::executeAndReturnResult($this->schema, $q)->toArray(1);
        expect($result)->toInclude([
            'data' => ['first' => 'ONE', 'second' => 'TWO', 'third' => null],
            'errors' => [[
                'debugMessage' => 'Expected a value of type "SimpleEnum" but received: "WRONG"',
                'locations' => [['line' => 4, 'column' => 13]]
            ]]
        ]);
    }

    private function expectFailure(string $query, ?array<string, mixed> $vars, mixed $err):void
    {
        $result = GraphQL::executeAndReturnResult($this->schema, $query, null, null, $vars);
        expect(\count($result->errors))->toBePHPEqual(1);

        if (is_array($err)) {
            expect($result->errors[0]->getMessage())->toBePHPEqual($err['message']);
            expect($result->errors[0]->getLocations())->toBePHPEqual($err['locations']);
        } else {
            expect($result->errors[0]->getMessage())->toBePHPEqual($err);
        }
    }
}
