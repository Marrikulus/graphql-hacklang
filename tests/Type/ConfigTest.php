<?hh //strict
//decl
namespace GraphQL\Tests\Type;

use GraphQL\Error\InvariantViolation;
use function Facebook\FBExpect\expect;
use GraphQL\Error\Warning;
use GraphQL\Error\HackWarningException;
use GraphQL\Type\Definition\Config;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;
use GraphQL\Utils\Utils;

class ConfigTest extends \Facebook\HackTest\HackTest
{
    public async function beforeEachTestAsync(): Awaitable<void>
    {
        Warning::suppress(Warning::WARNING_CONFIG_DEPRECATION);
    }

    public static async function afterLastTestAsync(): Awaitable<void>
    {
        Config::disableValidation();
        Warning::enable(Warning::WARNING_CONFIG_DEPRECATION);
    }

    public function testToggling():void
    {
        // Disabled by default
        expect(Config::isValidationEnabled())->toBePHPEqual(false);
        Config::validate(['test' => []], ['test' => Config::STRING]); // must not throw

        Config::enableValidation();
        expect(Config::isValidationEnabled())->toBePHPEqual(true);

        try {
            Config::validate(['test' => []], ['test' => Config::STRING]);
            self::fail('Expected exception not thrown');
        } catch (\Exception $e) {
        }

        Config::disableValidation();
        expect(Config::isValidationEnabled())->toBePHPEqual(false);
        Config::validate(['test' => []], ['test' => Config::STRING]);
    }

    public function testValidateString():void
    {
        $this->expectValidationPasses(
            [
                'test' => 'string',
                'empty' => ''
            ],
            [
                'test' => Config::STRING,
                'empty' => Config::STRING
            ]
        );

        $this->expectValidationThrows(
            ['test' => 1],
            ['test' => Config::STRING],
            $this->typeError('expecting "string" at "test", but got "integer"')
        );
    }

    public function testArray():void
    {
        $this->expectValidationPasses(
            ['test' => [
                [],
                ['nested' => 'A'],
                ['nested' => null]
            ]],
            ['test' => Config::arrayOf(
                ['nested' => Config::STRING]
            )]
        );

        $this->expectValidationThrows(
            ['test' => [
                null
            ]],
            ['test' => Config::arrayOf(
                ['nested' => Config::STRING]
            )],
            $this->typeError("Each entry at 'test' must be an array, but entry at '0' is 'NULL'")
        );

        $this->expectValidationPasses(
            ['test' => null],
            ['test' => Config::arrayOf(
                ['nested' => Config::STRING]
            )]
        );

        $this->expectValidationThrows(
            ['test' => null],
            ['test' => Config::arrayOf(
                ['nested' => Config::STRING],
                Config::REQUIRED
            )],
            $this->typeError('expecting "array" at "test", but got "NULL"')
        );

        // Check validation nesting:
        $this->expectValidationPasses(
            ['nest' => [
                ['nest' => [
                    ['test' => 'value']
                ]]
            ]],
            ['nest' => Config::arrayOf([
                'nest' => Config::arrayOf([
                    'test' => Config::STRING
                ])
            ])]
        );

        $this->expectValidationThrows(
            ['nest' => [
                ['nest' => [
                    ['test' => 'notInt']
                ]]
            ]],
            ['nest' => Config::arrayOf([
                'nest' => Config::arrayOf([
                    'test' => Config::INT
                ])
            ])],
            $this->typeError('expecting "int" at "nest:0:nest:0:test", but got "string"')
        );

        // Check arrays of types:
        $this->expectValidationPasses(
            ['nest' => [
                GraphQlType::string(),
                GraphQlType::int()
            ]],
            ['nest' => Config::arrayOf(
                Config::OUTPUT_TYPE, Config::REQUIRED
            )]
        );

        // Check arrays of types:
        $this->expectValidationThrows(
            ['nest' => [
                GraphQlType::string(),
                new InputObjectType(['name' => 'test', 'fields' => []])
            ]],
            ['nest' => Config::arrayOf(
                Config::OUTPUT_TYPE, Config::REQUIRED
            )],
            $this->typeError('expecting "OutputType definition" at "nest:1", but got "test"')
        );
    }

    public function testRequired():void
    {
        $this->expectValidationPasses(
            ['required' => ''],
            ['required' => Config::STRING | Config::REQUIRED]
        );

        $this->expectValidationThrows(
            [],
            ['required' => Config::STRING | Config::REQUIRED],
            $this->typeError('Required keys missing: "required" ')
        );

        $this->expectValidationThrows(
            ['required' => null],
            ['required' => Config::STRING | Config::REQUIRED],
            $this->typeError('Value at "required" can not be null')
        );

        $this->expectValidationPasses(
            ['test' => [
                ['nested' => '']
            ]],
            ['test' => Config::arrayOf([
                'nested' => Config::STRING | Config::REQUIRED
            ])]
        );

        $this->expectValidationThrows(
            ['test' => [
                []
            ]],
            ['test' => Config::arrayOf([
                'nested' => Config::STRING | Config::REQUIRED
            ])],
            $this->typeError('Required keys missing: "nested"  at test:0')
        );

        $this->expectValidationThrows(
            ['test' => [
                ['nested' => null]
            ]],
            ['test' => Config::arrayOf([
                'nested' => Config::STRING | Config::REQUIRED
            ])],
            $this->typeError('Value at "test:0:nested" can not be null')
        );

        $this->expectValidationPasses(
            ['test' => [
                ['nested' => null]
            ]],
            ['test' => Config::arrayOf(
                ['nested' => Config::STRING],
                Config::REQUIRED
            )]
        );

        $this->expectValidationThrows(
            ['test' => [

            ]],
            ['test' => Config::arrayOf(
                ['nested' => Config::STRING],
                Config::REQUIRED
            )],
            $this->typeError("Value at 'test' cannot be empty array")
        );
    }

    public function testKeyAsName():void
    {
        $this->expectValidationPasses(
            ['test' => [
                'name1' => ['key1' => null],
                '' => ['name' => 'name1'],
            ]],
            ['test' => Config::arrayOf(
                ['name' => Config::STRING | Config::REQUIRED, 'key1' => Config::STRING],
                Config::KEY_AS_NAME
            )]
        );

        $this->expectValidationThrows(
            ['test' => [
                'name1' => ['key1' => null]
            ]],
            ['test' => Config::arrayOf(
                ['name' => Config::STRING | Config::REQUIRED, 'key1' => Config::STRING]
            )],
            $this->typeError('Required keys missing: "name"  at test:name1')
        );

        $this->expectValidationThrows(
            ['test' => [
                ['key1' => null]
            ]],
            ['test' => Config::arrayOf(
                ['name' => Config::STRING | Config::REQUIRED, 'key1' => Config::STRING],
                Config::KEY_AS_NAME
            )],
            $this->typeError('Required keys missing: "name"  at test:0')
        );
    }

    public function testMaybeThunk():void
    {
        $this->expectValidationPasses(
            [
                'test' => [
                    ['nested' => ''],
                    ['nested' => '1'],
                ],
                'testThunk' => function() {
                    // Currently config won't validate thunk return value
                }
            ],
            [
                'test' => Config::arrayOf(
                    ['nested' => Config::STRING | Config::REQUIRED],
                    Config::MAYBE_THUNK
                ),
                'testThunk' => Config::arrayOf(
                    ['nested' => Config::STRING | Config::REQUIRED],
                    Config::MAYBE_THUNK
                )
            ]
        );

        $this->expectValidationThrows(
            [
                'testThunk' => $closure = function() {}
            ],
            [
                'testThunk' => Config::arrayOf(
                    ['nested' => Config::STRING | Config::REQUIRED]
                )
            ],
            $this->typeError('expecting "array" at "testThunk", but got "' . Utils::getVariableType($closure) . '"')
        );

        $this->expectValidationThrows(
            [
                'testThunk' => 1
            ],
            [
                'testThunk' => Config::arrayOf(
                    ['nested' => Config::STRING | Config::REQUIRED],
                    Config::MAYBE_THUNK
                )
            ],
            $this->typeError('expecting "array or callable" at "testThunk", but got "integer"')
        );
    }

    public function testMaybeType():void
    {
        $type = new ObjectType([
            'name' => 'Test',
            'fields' => []
        ]);

        $this->expectValidationPasses(
            ['test' => [
                $type,
                ['type' => $type],
            ]],
            ['test' => Config::arrayOf(
                ['type' => Config::OBJECT_TYPE | Config::REQUIRED],
                Config::MAYBE_TYPE
            )]
        );

        $this->expectValidationThrows(
            ['test' => [
                ['type' => 'str']
            ]],
            ['test' => Config::arrayOf(
                ['type' => Config::OBJECT_TYPE | Config::REQUIRED],
                Config::MAYBE_TYPE
            )],
            $this->typeError('expecting "ObjectType definition" at "test:0:type", but got "string"')
        );

        $this->expectValidationThrows(
            ['test' => [
                $type
            ]],
            ['test' => Config::arrayOf(
                ['name' => Config::OBJECT_TYPE | Config::REQUIRED]
            )],
            $this->typeError("Each entry at 'test' must be an array, but entry at '0' is 'Test'")
        );
    }

    public function testMaybeName():void
    {
        $this->expectValidationPasses(
            ['test' => [
                'some-name',
                ['name' => 'other-name'],
            ]],
            ['test' => Config::arrayOf(
                ['name' => Config::STRING | Config::REQUIRED],
                Config::MAYBE_NAME
            )]
        );

        $this->expectValidationThrows(
            ['test' => [
                'some-name'
            ]],
            ['test' => Config::arrayOf(
                ['name' => Config::OBJECT_TYPE | Config::REQUIRED]
            )],
            $this->typeError("Each entry at 'test' must be an array, but entry at '0' is 'string'")
        );

        $this->expectValidationPasses(
            ['test' => [
                'some-key' => 'some-name',
                '' => 'some-name'
            ]],
            ['test' => Config::arrayOf(
                ['name' => Config::STRING | Config::REQUIRED],
                Config::MAYBE_NAME | Config::KEY_AS_NAME
            )]
        );
    }

    public function getValidValues():array<(int, mixed)>
    {
        return [
            // $type, $validValue
            tuple(Config::ANY, null),
            tuple(Config::ANY, 0),
            tuple(Config::ANY, ''),
            tuple(Config::ANY, '0'),
            tuple(Config::ANY, 1),
            tuple(Config::ANY, function() {}),
            tuple(Config::ANY, []),
            tuple(Config::ANY, new \stdClass()),
            tuple(Config::STRING, null),
            tuple(Config::STRING, ''),
            tuple(Config::STRING, '0'),
            tuple(Config::STRING, 'anything'),
            tuple(Config::BOOLEAN, null),
            tuple(Config::BOOLEAN, false),
            tuple(Config::BOOLEAN, true),
            tuple(Config::INT, null),
            tuple(Config::INT, 0),
            tuple(Config::INT, 1),
            tuple(Config::INT, -1),
            tuple(Config::INT, 5000000),
            tuple(Config::INT, -5000000),
            tuple(Config::FLOAT, null),
            tuple(Config::FLOAT, 0),
            tuple(Config::FLOAT, 0.0),
            tuple(Config::FLOAT, 0.1),
            tuple(Config::FLOAT, -12.5),
            tuple(Config::NUMERIC, null),
            tuple(Config::NUMERIC, '0'),
            tuple(Config::NUMERIC, 0),
            tuple(Config::NUMERIC, 1),
            tuple(Config::NUMERIC, 0.0),
            tuple(Config::NUMERIC, 1.0),
            tuple(Config::NUMERIC, -1.0),
            tuple(Config::NUMERIC, -1),
            tuple(Config::NUMERIC, 1),
            tuple(Config::CALLBACK, null),
            tuple(Config::CALLBACK, function() {}),
            tuple(Config::CALLBACK, [$this, 'getValidValues']),
            tuple(Config::SCALAR, null),
            tuple(Config::SCALAR, 0),
            tuple(Config::SCALAR, 1),
            tuple(Config::SCALAR, 0.0),
            tuple(Config::SCALAR, 1.0),
            tuple(Config::SCALAR, true),
            tuple(Config::SCALAR, false),
            tuple(Config::SCALAR, ''),
            tuple(Config::SCALAR, '0'),
            tuple(Config::SCALAR, 'anything'),
            tuple(Config::NAME, null),
            tuple(Config::NAME, 'CamelCaseIsOk'),
            tuple(Config::NAME, 'underscore_is_ok'),
            tuple(Config::NAME, 'numbersAreOk0123456789'),
            tuple(Config::INPUT_TYPE, null),
            tuple(Config::INPUT_TYPE, new InputObjectType(['name' => 'test', 'fields' => []])),
            tuple(Config::INPUT_TYPE, new EnumType(['name' => 'test2', 'values' => ['A', 'B', 'C']])),
            tuple(Config::INPUT_TYPE, GraphQlType::string()),
            tuple(Config::INPUT_TYPE, GraphQlType::int()),
            tuple(Config::INPUT_TYPE, GraphQlType::float()),
            tuple(Config::INPUT_TYPE, GraphQlType::boolean()),
            tuple(Config::INPUT_TYPE, GraphQlType::id()),
            tuple(Config::INPUT_TYPE, GraphQlType::listOf(GraphQlType::string())),
            tuple(Config::INPUT_TYPE, GraphQlType::nonNull(GraphQlType::string())),
            tuple(Config::OUTPUT_TYPE, null),
            tuple(Config::OUTPUT_TYPE, new ObjectType(['name' => 'test3', 'fields' => []])),
            tuple(Config::OUTPUT_TYPE, new EnumType(['name' => 'test4', 'values' => ['A', 'B', 'C']])),
            tuple(Config::OUTPUT_TYPE, GraphQlType::string()),
            tuple(Config::OUTPUT_TYPE, GraphQlType::int()),
            tuple(Config::OUTPUT_TYPE, GraphQlType::float()),
            tuple(Config::OUTPUT_TYPE, GraphQlType::boolean()),
            tuple(Config::OUTPUT_TYPE, GraphQlType::id()),
            tuple(Config::OBJECT_TYPE, null),
            tuple(Config::OBJECT_TYPE, new ObjectType(['name' => 'test6', 'fields' => []])),
            tuple(Config::INTERFACE_TYPE, null),
            tuple(Config::INTERFACE_TYPE, new InterfaceType(['name' => 'test7', 'fields' => []])),
        ];
    }

    <<DataProvider('getValidValues')>>
    public function testValidValues(int $type, mixed $validValue):void
    {
        $this->expectValidationPasses(
            ['test' => $validValue],
            ['test' => $type]
        );
    }

    /* HH_FIXME[1002]*/
    public function getInvalidValues():array<(int, string, mixed, ?string, ?string)>
    {
        return [
            // $type, $typeLabel, $invalidValue, $actualTypeLabel
            tuple(Config::STRING,        'string',                       1,                          'integer',  null),
            tuple(Config::STRING,        'string',                       0,                          'integer',  null),
            tuple(Config::STRING,        'string',                       false,                      'boolean',  null),
            tuple(Config::STRING,        'string',                       $tmp = function() {},       Utils::getVariableType($tmp), null), // Note: can't use "Closure" as HHVM returns different string
            tuple(Config::STRING,        'string',                       [],                         'array',    null),
            tuple(Config::STRING,        'string',                       new \stdClass(),            'stdClass', null),
            tuple(Config::BOOLEAN,       'boolean',                      '',                         'string',   null),
            tuple(Config::BOOLEAN,       'boolean',                      1,                          'integer',  null),
            tuple(Config::BOOLEAN,       'boolean',                      $tmp = function() {},       Utils::getVariableType($tmp), null),
            tuple(Config::BOOLEAN,       'boolean',                      [],                         'array',    null),
            tuple(Config::BOOLEAN,       'boolean',                      new \stdClass(),            'stdClass', null),
            tuple(Config::INT,           'int',                          false,                      'boolean',  null),
            tuple(Config::INT,           'int',                          '',                         'string',   null),
            tuple(Config::INT,           'int',                          '0',                        'string',   null),
            tuple(Config::INT,           'int',                          '1',                        'string',   null),
            tuple(Config::INT,           'int',                          $tmp = function() {},       Utils::getVariableType($tmp), null),
            tuple(Config::INT,           'int',                          [],                         'array',    null),
            tuple(Config::INT,           'int',                          new \stdClass(),            'stdClass', null),
            tuple(Config::FLOAT,         'float',                        '',                         'string',   null),
            tuple(Config::FLOAT,         'float',                        '0',                        'string',   null),
            tuple(Config::FLOAT,         'float',                        $tmp = function() {},       Utils::getVariableType($tmp), null),
            tuple(Config::FLOAT,         'float',                        [],                         'array',    null),
            tuple(Config::FLOAT,         'float',                        new \stdClass(),            'stdClass', null),
            tuple(Config::NUMERIC,       'numeric',                      '',                         'string',   null),
            tuple(Config::NUMERIC,       'numeric',                      'tmp',                      'string',   null),
            tuple(Config::NUMERIC,       'numeric',                      [],                         'array',    null),
            tuple(Config::NUMERIC,       'numeric',                      new \stdClass(),            'stdClass', null),
            tuple(Config::NUMERIC,       'numeric',                      $tmp = function() {},       Utils::getVariableType($tmp), null),
            tuple(Config::CALLBACK,      'callable',                     1,                          'integer',  null),
            tuple(Config::CALLBACK,      'callable',                     '',                         'string',   null),
            tuple(Config::CALLBACK,      'callable',                     [],                         'array',    null),
            tuple(Config::CALLBACK,      'callable',                     new \stdClass(),            'stdClass', null),
            tuple(Config::SCALAR,        'scalar',                       [],                         'array',    null),
            tuple(Config::SCALAR,        'scalar',                       new \stdClass(),            'stdClass', null),
            tuple(Config::SCALAR,        'scalar',                       $tmp = function() {},       Utils::getVariableType($tmp), null),
            tuple(Config::NAME,          'name',                         5,                          'integer',  null),
            tuple(Config::NAME,          'name',                         $tmp = function() {},       Utils::getVariableType($tmp), null),
            tuple(Config::NAME,          'name',                         [],                         'array',    null),
            tuple(Config::NAME,          'name',                         new \stdClass(),            'stdClass', null),
            tuple(Config::NAME,          'name',                         '',                         null, 'Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but "" does not.'),
            tuple(Config::NAME,          'name',                         '0',                        null, 'Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but "0" does not.'),
            tuple(Config::NAME,          'name',                         '4abc',                     null, 'Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but "4abc" does not.'),
            tuple(Config::NAME,          'name',                         'specialCharsAreBad!',      null, 'Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but "specialCharsAreBad!" does not.'),
            tuple(Config::INPUT_TYPE,    'InputType definition',         new ObjectType(['name' => 'test3', 'fields' => []]), 'test3',           null),
            tuple(Config::INPUT_TYPE,    'InputType definition',         '',                                                 'string',           null),
            tuple(Config::INPUT_TYPE,    'InputType definition',         'test',                                             'string',           null),
            tuple(Config::INPUT_TYPE,    'InputType definition',         1,                                                  'integer',          null),
            tuple(Config::INPUT_TYPE,    'InputType definition',         0.5,                                                'double',           null),
            tuple(Config::INPUT_TYPE,    'InputType definition',         false,                                              'boolean',          null),
            tuple(Config::INPUT_TYPE,    'InputType definition',         [],                                                 'array',            null),
            tuple(Config::INPUT_TYPE,    'InputType definition',         new \stdClass(),                                    'stdClass',         null),
            tuple(Config::OUTPUT_TYPE,   'OutputType definition',        new InputObjectType(['name' => 'InputTypeTest']),   'InputTypeTest',    null),
            tuple(Config::OBJECT_TYPE,   'ObjectType definition',        '',                                                 'string',           null),
            tuple(Config::OBJECT_TYPE,   'ObjectType definition',        new InputObjectType(['name' => 'InputTypeTest2']),  'InputTypeTest2',   null),
            tuple(Config::INTERFACE_TYPE, 'InterfaceType definition',    new ObjectType(['name' => 'ObjectTypeTest']),       'ObjectTypeTest',   null),
            tuple(Config::INTERFACE_TYPE, 'InterfaceType definition',    'InputTypeTest2',                                   'string',           null),
        ];
    }

    <<DataProvider('getInvalidValues')>>
    public function testInvalidValues(int $type, string $typeLabel, mixed $invalidValue, ?string $actualTypeLabel = null, ?string $expectedFullError = null):void
    {
        $expectedFullError ??= $this->typeError(
            $invalidValue === null ?
                'Value at "test" can not be null' :
                'expecting "' . $typeLabel . '" at "test", but got "' . $actualTypeLabel . '"'
        );

        $this->expectValidationThrows(
            ['test' => $invalidValue],
            ['test' => $type],
            $expectedFullError
        );
    }

    public function testErrorMessageContainsTypeName():void
    {
        $this->expectValidationThrows(
            [
                'name' => 'TypeName',
                'test' => 'notInt'
            ],
            [
                'name' => Config::STRING | Config::REQUIRED,
                'test' => Config::INT
            ],
            $this->typeError('expecting "int" at "test", but got "string"', 'TypeName')
        );
    }

    public function testValidateField():void
    {
        Config::enableValidation();

        // Should just validate:
        Config::validateField(
            'TypeName',
            ['test' => 'value'],
            ['test' => Config::STRING]
        );

        // Should include type name in error
        try {
            Config::validateField(
                'TypeName',
                ['test' => 'notInt'],
                ['test' => Config::INT]
            );
            self::fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            expect($e->getMessage())
                ->toBePHPEqual($this->typeError('expecting "int" at "(Unknown Field):test", but got "string"', 'TypeName'));
        }

        // Should include field type in error when field name is unknown:
        try {
            Config::validateField(
                'TypeName',
                ['type' => GraphQlType::string()],
                ['name' => Config::STRING | Config::REQUIRED, 'type' => Config::OUTPUT_TYPE]
            );
            self::fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            expect($e->getMessage())
                ->toBePHPEqual($this->typeError('Required keys missing: "name"  at (Unknown Field of type: String)', 'TypeName'));
        }

        // Should include field name in error when field name is set:
        try {
            Config::validateField(
                'TypeName',
                ['name' => 'fieldName', 'test' => 'notInt'],
                ['name' => Config::STRING, 'test' => Config::INT]
            );
            self::fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            expect($e->getMessage())
                ->toBePHPEqual($this->typeError('expecting "int" at "test", but got "string"', 'TypeName'));
        }
    }

    public function testAllowCustomOptions():void
    {
        // Disabled by default when validation is enabled
        Config::enableValidation(true);

        Config::validate(
            ['test' => 'value', 'test2' => 'value'],
            ['test' => Config::STRING]
        );

        Config::enableValidation(false);

        \set_error_handler(
            function($errno, $errstr, $errfile, $errline){
                throw new HackWarningException($errstr, $errno, 0, $errfile, $errline);
            },
            E_WARNING | E_USER_WARNING
        );

        try
        {
            Config::validate(
                ['test' => 'value', 'test2' => 'value'],
                ['test' => Config::STRING]
            );
            self::fail('Expected exception not thrown');
        }
        catch (HackWarningException $e)
        {
            expect($e->getMessage())->toBePHPEqual($this->typeError('Non-standard keys "test2" '));
        }
        \restore_error_handler();
    }

    private function expectValidationPasses(array<string, mixed> $config, array<string, mixed> $definition):void
    {
        Config::enableValidation(false);
        Config::validate($config, $definition);
    }

    private function expectValidationThrows(array<string, mixed> $config, array<string, mixed> $definition, string $expectedError):void
    {
        Config::enableValidation(false);
        try
        {
            Config::validate($config, $definition);
            self::fail('Expected exception not thrown: ' . $expectedError);
        }
        catch (InvariantViolation $e)
        {
            expect($e->getMessage())->toBePHPEqual($expectedError);
        }
    }

    private function typeError(string $err, ?string $typeName = null):string
    {
        return 'Error in "'. ($typeName ?? '(Unnamed Type)') . '" type definition: ' . $err;
    }
}
