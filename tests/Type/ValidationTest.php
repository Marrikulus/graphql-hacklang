<?hh //strict
//decl
namespace GraphQL\Tests\Type;

use GraphQL\Error\InvariantViolation;
use function Facebook\FBExpect\expect;
use GraphQL\Error\Warning;
use GraphQL\Error\HackWarningException;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Utils\Utils;



class ValidationTest extends \Facebook\HackTest\HackTest
{
    public ?CustomScalarType $SomeScalarType;

    public ?ObjectType $SomeObjectType;

    public ?ObjectType $ObjectWithIsTypeOf;

    public ?UnionType $SomeUnionType;

    public ?InterfaceType $SomeInterfaceType;

    public ?EnumType $SomeEnumType;

    public ?InputObjectType $SomeInputObjectType;

    public ?array<GraphQlType> $outputTypes;

    public ?array<mixed> $notOutputTypes;

    public ?array<GraphQlType> $inputTypes;

    public ?array<mixed> $notInputTypes;

    public ?array<mixed> $notInterfaceTypes;

    public ?array<mixed> $notObjectTypes;

    public ?array<mixed> $types;

    public ?string $String;

    public async function beforeEachTestAsync():Awaitable<void>
    {
        $String = 'TestString';

        $this->SomeInterfaceType = $SomeInterfaceType = new InterfaceType([
            'name' => 'SomeInterface',
            'resolveType' => function() {
                return null;
            },
            'fields' => [ 'f' => ['type' => GraphQlType::string() ]]
        ]);

        $SomeScalarType = new CustomScalarType([
            'name' => 'SomeScalar',
            'serialize' => function() {},
            'parseValue' => function() {},
            'parseLiteral' => function() {}
        ]);

        $this->SomeObjectType = $SomeObjectType = new ObjectType([
            'name' => 'SomeObject',
            'fields' => [ 'f' => [ 'type' => GraphQlType::string() ] ],
            'interfaces' => function() {return [$this->SomeInterfaceType];}
        ]);

        $ObjectWithIsTypeOf = new ObjectType([
            'name' => 'ObjectWithIsTypeOf',
            'isTypeOf' => function() {
                return true;
            },
            'fields' => [ 'f' => [ 'type' => GraphQlType::string() ]]
        ]);
        $SomeUnionType = new UnionType([
            'name' => 'SomeUnion',
            'resolveType' => function() {
                return null;
            },
            'types' => [ $this->SomeObjectType ]
        ]);

        $SomeEnumType = new EnumType([
            'name' => 'SomeEnum',
            'values' => [
                'ONLY' => []
            ]
        ]);

        $SomeInputObjectType = new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => [
                'val' => [ 'type' => GraphQlType::string(), 'defaultValue' => 'hello' ]
            ]
        ]);

        $outputTypes = $this->withModifiers([
            GraphQlType::string(),
            $SomeScalarType,
            $SomeEnumType,
            $SomeObjectType,
            $SomeUnionType,
            $SomeInterfaceType
        ]);

        $notOutputTypes = $this->withModifiers([
          $SomeInputObjectType,
        ]);
        $notOutputTypes[] = $String;

        $inputTypes = $this->withModifiers([
            GraphQlType::string(),
            $SomeScalarType,
            $SomeEnumType,
            $SomeInputObjectType,
        ]);

        $notInputTypes = $this->withModifiers([
            $SomeObjectType,
            $SomeUnionType,
            $SomeInterfaceType,
        ]);

        $notInputTypes[] = $String;


        $notInterfaceTypes = $this->withModifiers([
            $SomeScalarType,
            $SomeEnumType,
            $SomeObjectType,
            $SomeUnionType,
            $SomeInputObjectType,
        ]);

        $notObjectTypes = $this->withModifiers([
            $SomeScalarType,
            $SomeEnumType,
            $SomeInterfaceType,
            $SomeUnionType,
            $SomeInputObjectType,
        ]);

        $types = $this->withModifiers([
            GraphQlType::string(),
            $SomeScalarType,
            $SomeObjectType,
            $SomeUnionType,
            $SomeInterfaceType,
            $SomeEnumType,
            $SomeInputObjectType,
        ]);

        $this->String               = $String;
        $this->SomeScalarType       = $SomeScalarType;
        $this->ObjectWithIsTypeOf   = $ObjectWithIsTypeOf;
        $this->SomeUnionType        = $SomeUnionType;
        $this->SomeInterfaceType    = $SomeInterfaceType;
        $this->SomeEnumType         = $SomeEnumType;
        $this->SomeInputObjectType  = $SomeInputObjectType;
        $this->outputTypes          = $outputTypes;
        $this->notOutputTypes       = $notOutputTypes;
        $this->inputTypes           = $inputTypes;
        $this->notInputTypes        = $notInputTypes;
        $this->notInterfaceTypes    = $notInterfaceTypes;
        $this->notObjectTypes       = $notObjectTypes;
        $this->types                = $types;

        Warning::suppress(Warning::WARNING_NOT_A_TYPE);
    }

    public async function afterEachTestAsync():Awaitable<void>
    {
        Warning::enable(Warning::WARNING_NOT_A_TYPE);
    }

    public function testRejectsTypesWithoutNames():void
    {
        $this->assertEachCallableThrows([
            function() {
                return new ObjectType([]);
            },
            function() {
                return new EnumType([]);
            },
            function() {
                return new InputObjectType([]);
            },
            function() {
                return new UnionType([]);
            },
            function() {
                return new InterfaceType([]);
            }
        ], 'Must be named. Unexpected name: null');
    }

    public function testRejectsAnObjectTypeWithReservedName():void
    {
        $this->assertWarnsOnce([
            function() {
                return new ObjectType([
                    'name' => '__ReservedName',
                ]);
            },
            function() {
                return new EnumType([
                    'name' => '__ReservedName',
                ]);
            },
            function() {
                return new InputObjectType([
                    'name' => '__ReservedName',
                ]);
            },
            function() {
                return new UnionType([
                    'name' => '__ReservedName',
                    'types' => [new ObjectType(['name' => 'Test'])]
                ]);
            },
            function() {
                return new InterfaceType([
                    'name' => '__ReservedName',
                ]);
            }
        ], 'Name "__ReservedName" must not begin with "__", which is reserved by GraphQL introspection. In a future release of graphql this will become an exception');
    }

    public function testRejectsAnObjectTypeWithInvalidName():void
    {
        $this->assertEachCallableThrows([
            function() {
                return new ObjectType([
                    'name' => 'a-b-c',
                ]);
            },
            function() {
                return new EnumType([
                    'name' => 'a-b-c',
                ]);
            },
            function() {
                return new InputObjectType([
                    'name' => 'a-b-c',
                ]);
            },
            function() {
                return new UnionType([
                    'name' => 'a-b-c',
                ]);
            },
            function() {
                return new InterfaceType([
                    'name' => 'a-b-c',
                ]);
            }
        ], 'Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but "a-b-c" does not.');
    }

    // DESCRIBE: Type System: A Schema must have Object root types

    /**
     * @it accepts a Schema whose query type is an object type
     */
    public function testAcceptsASchemaWhoseQueryTypeIsAnObjectType():void
    {
        // Must not throw:
        $schema = new Schema([
            'query' => $this->SomeObjectType
        ]);
        $schema->assertValid();
    }

    /**
     * @it accepts a Schema whose query and mutation types are object types
     */
    public function testAcceptsASchemaWhoseQueryAndMutationTypesAreObjectTypes():void
    {
        $mutationType = new ObjectType([
            'name' => 'Mutation',
            'fields' => [
                'edit' => ['type' => GraphQlType::string()]
            ]
        ]);
        $schema = new Schema([
            'query' => $this->SomeObjectType,
            'mutation' => $mutationType
        ]);
        $schema->assertValid();
    }

    /**
     * @it accepts a Schema whose query and subscription types are object types
     */
    public function testAcceptsASchemaWhoseQueryAndSubscriptionTypesAreObjectTypes():void
    {
        $subscriptionType = new ObjectType([
            'name' => 'Subscription',
            'fields' => [
                'subscribe' => ['type' => GraphQlType::string()]
            ]
        ]);
        $schema = new Schema([
            'query' => $this->SomeObjectType,
            'subscription' => $subscriptionType
        ]);
        $schema->assertValid();
    }

    /**
     * @it rejects a Schema without a query type
     */
    public function testRejectsASchemaWithoutAQueryType():void
    {
        $this->setExpectedException(InvariantException::class, 'Schema query must be Object Type but got: NULL');
        new Schema([]);
    }

    /**
     * @it rejects a Schema whose query type is an input type
     */
    public function testRejectsASchemaWhoseQueryTypeIsAnInputType():void
    {
        $this->setExpectedException(
            InvariantViolation::class,
            'Schema query must be Object Type if provided but got: SomeInputObject'
        );
        new Schema([
            'query' => $this->SomeInputObjectType
        ]);
    }

    /**
     * @it rejects a Schema whose mutation type is an input type
     */
    public function testRejectsASchemaWhoseMutationTypeIsAnInputType():void
    {
        $this->setExpectedException(
            InvariantViolation::class,
            'Schema mutation must be Object Type if provided but got: SomeInputObject'
        );
        new Schema([
            'query' => $this->SomeObjectType,
            'mutation' => $this->SomeInputObjectType
        ]);
    }

    /**
     * @it rejects a Schema whose subscription type is an input type
     */
    public function testRejectsASchemaWhoseSubscriptionTypeIsAnInputType():void
    {
        $this->setExpectedException(
            InvariantViolation::class,
            'Schema subscription must be Object Type if provided but got: SomeInputObject'
        );
        new Schema([
            'query' => $this->SomeObjectType,
            'subscription' => $this->SomeInputObjectType
        ]);
    }

    /**
     * @it rejects a Schema whose directives are incorrectly typed
     */
    public function testRejectsASchemaWhoseDirectivesAreIncorrectlyTyped():void
    {
        $schema = new Schema([
            'query' => $this->SomeObjectType,
            'directives' => ['somedirective']
        ]);

        $this->setExpectedException(
            InvariantViolation::class,
            'Each entry of "directives" option of Schema config must be an instance of GraphQL\Type\Definition\Directive but entry at position 0 is "somedirective".'
        );

        $schema->assertValid();
    }

    // DESCRIBE: Type System: A Schema must contain uniquely named types
    /**
     * @it rejects a Schema which redefines a built-in type
     */
    public function testRejectsASchemaWhichRedefinesABuiltInType():void
    {
        $FakeString = new CustomScalarType([
            'name' => 'String',
            'serialize' => function() {
                return null;
            },
        ]);

        $QueryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'normal' => [ 'type' => GraphQlType::string() ],
                'fake' => [ 'type' => $FakeString ],
            ]
        ]);

        $this->setExpectedException(
            InvariantViolation::class,
            'Schema must contain unique named types but contains multiple types named "String" '.
            '(see http://webonyx.github.io/graphql-php/type-system/#type-registry).'
        );
        new Schema(['query' => $QueryType]);
    }

    /**
     * @it rejects a Schema which defines an object type twice
     */
    public function testRejectsASchemaWhichDfinesAnObjectTypeTwice():void
    {
        $A = new ObjectType([
            'name' => 'SameName',
            'fields' => [ 'f' => [ 'type' => GraphQlType::string() ]],
        ]);

        $B = new ObjectType([
            'name' => 'SameName',
            'fields' => [ 'f' => [ 'type' => GraphQlType::string() ] ],
        ]);

        $QueryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'a' => [ 'type' => $A ],
                'b' => [ 'type' => $B ]
            ]
        ]);

        $this->setExpectedException(
            InvariantViolation::class,
            'Schema must contain unique named types but contains multiple types named "SameName" '.
            '(see http://webonyx.github.io/graphql-php/type-system/#type-registry).'
        );

        new Schema([ 'query' => $QueryType ]);
    }

    /**
     * @it rejects a Schema which have same named objects implementing an interface
     */
    public function testRejectsASchemaWhichHaveSameNamedObjectsImplementingAnInterface():void
    {
        $AnotherInterface = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => function() {},
            'fields' => [ 'f' => [ 'type' => GraphQlType::string() ]],
        ]);

        $FirstBadObject = new ObjectType([
            'name' => 'BadObject',
            'interfaces' => [ $AnotherInterface ],
            'fields' => [ 'f' => [ 'type' => GraphQlType::string() ]],
        ]);

        $SecondBadObject = new ObjectType([
            'name' => 'BadObject',
            'interfaces' => [ $AnotherInterface ],
            'fields' => [ 'f' => [ 'type' => GraphQlType::string() ]],
        ]);

        $QueryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'iface' => [ 'type' => $AnotherInterface ],
            ]
        ]);

        $this->setExpectedException(
            InvariantViolation::class,
            'Schema must contain unique named types but contains multiple types named "BadObject" '.
            '(see http://webonyx.github.io/graphql-php/type-system/#type-registry).'
        );

        new Schema([
            'query' => $QueryType,
            'types' => [ $FirstBadObject, $SecondBadObject ]
        ]);
    }


    // DESCRIBE: Type System: Objects must have fields

    /**
     * @it accepts an Object type with fields object
     */
    public function testAcceptsAnObjectTypeWithFieldsObject():void
    {
        $schema = $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'fields' => [
                'f' => [ 'type' => GraphQlType::string() ]
            ]
        ]));

        // Should not throw:
        $schema->assertValid();
    }

    /**
     * @it accepts an Object type with a field function
     */
    public function testAcceptsAnObjectTypeWithAfieldFunction():void
    {
        $schema = $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'fields' => function() {
                return [
                    'f' => ['type' => GraphQlType::string()]
                ];
            }
        ]));
        $schema->assertValid();
    }

    /**
     * @it rejects an Object type with missing fields
     */
    public function testRejectsAnObjectTypeWithMissingFields():void
    {
        $schema = $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject'
        ]));

        $this->setExpectedException(
            InvariantViolation::class,
            'SomeObject fields must not be empty'
        );
        $schema->assertValid();
    }

    /**
     * @it rejects an Object type field with undefined config
     */
    public function testRejectsAnObjectTypeFieldWithUndefinedConfig():void
    {
        $this->setExpectedException(
            InvariantViolation::class,
            'SomeObject.f field config must be an array, but got'
        );
        $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'fields' => [
                'f' => null
            ]
        ]));
    }

    /**
     * @it rejects an Object type with incorrectly named fields
     */
    public function testRejectsAnObjectTypeWithIncorrectlyNamedFields():void
    {
        $schema = $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'fields' => [
                'bad-name-with-dashes' => ['type' => GraphQlType::string()]
            ]
        ]));

        $this->setExpectedException(
            InvariantViolation::class
        );

        $schema->assertValid();
    }

    /**
     * @it warns about an Object type with reserved named fields
     */
    public function testWarnsAboutAnObjectTypeWithReservedNamedFields():void
    {
        $lastMessage = null;
        /* HH_FIXME[2087]*/
        Warning::setWarningHandler(function($message, $warningId) use (&$lastMessage) {
            $lastMessage = $message;
        });

        $schema = $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'fields' => [
                '__notPartOfIntrospection' => ['type' => GraphQlType::string()]
            ]
        ]));

        $schema->assertValid();

        expect($lastMessage)->toBePHPEqual(
            'Name "__notPartOfIntrospection" must not begin with "__", which is reserved by GraphQL introspection. '.
            'In a future release of graphql this will become an exception'
        );
        Warning::setWarningHandler(null);
    }

    public function testAcceptsShorthandNotationForFields():void
    {
        $schema = $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'fields' => [
                'field' => GraphQlType::string()
            ]
        ]));
        $schema->assertValid();
    }

    /**
     * @it rejects an Object type with incorrectly typed fields
     */
    public function testRejectsAnObjectTypeWithIncorrectlyTypedFields():void
    {
        $schema = $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'fields' => [
                'field' => new \stdClass(['type' => GraphQlType::string()])
            ]
        ]));

        $this->setExpectedException(
            InvariantViolation::class,
            'SomeObject.field field type must be Output Type but got: instance of stdClass'
        );
        $schema->assertValid();
    }

    /**
     * @it rejects an Object type with empty fields
     */
    public function testRejectsAnObjectTypeWithEmptyFields():void
    {
        $schema = $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'fields' => []
        ]));

        $this->setExpectedException(
            InvariantViolation::class,
            'SomeObject fields must not be empty'
        );
        $schema->assertValid();
    }

    /**
     * @it rejects an Object type with a field function that returns nothing
     */
    public function testRejectsAnObjectTypeWithAFieldFunctionThatReturnsNothing():void
    {
        $this->setExpectedException(
            InvariantViolation::class,
            'SomeObject fields must be an array or a callable which returns such an array.'
        );
        $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'fields' => function() {}
        ]));
    }

    /**
     * @it rejects an Object type with a field function that returns empty
     */
    public function testRejectsAnObjectTypeWithAFieldFunctionThatReturnsEmpty():void
    {
        $schema = $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'fields' => function() {
                return [];
            }
        ]));

        $this->setExpectedException(
            InvariantViolation::class,
            'SomeObject fields must not be empty'
        );
        $schema->assertValid();
    }

    // DESCRIBE: Type System: Fields args must be properly named

    /**
     * @it accepts field args with valid names
     */
    public function testAcceptsFieldArgsWithValidNames():void
    {
        $schema = $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'fields' => [
                'goodField' => [
                    'type' => GraphQlType::string(),
                    'args' => [
                        'goodArg' => ['type' => GraphQlType::string()]
                    ]
                ]
            ]
        ]));
        $schema->assertValid();
    }

    /**
     * @it rejects field arg with invalid names
     */
    public function testRejectsFieldArgWithInvalidNames():void
    {
        $QueryType = new ObjectType([
            'name' => 'SomeObject',
            'fields' => [
                'badField' => [
                    'type' => GraphQlType::string(),
                    'args' => [
                        'bad-name-with-dashes' => ['type' => GraphQlType::string()]
                    ]
                ]
            ]
        ]);
        $schema = new Schema(['query' => $QueryType]);

        $this->setExpectedException(
            InvariantViolation::class,
            'SomeObject.badField(bad-name-with-dashes:) Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but "bad-name-with-dashes" does not.'
        );

        $schema->assertValid();
    }

    // DESCRIBE: Type System: Fields args must be objects

    /**
     * @it accepts an Object type with field args
     */
    public function testAcceptsAnObjectTypeWithFieldArgs():void
    {
        $schema = $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'fields' => [
                'goodField' => [
                    'type' => GraphQlType::string(),
                    'args' => [
                        'goodArg' => ['type' => GraphQlType::string()]
                    ]
                ]
            ]
        ]));
        $schema->assertValid();
    }

    /**
     * @it rejects an Object type with incorrectly typed field args
     */
    public function testRejectsAnObjectTypeWithIncorrectlyTypedFieldArgs():void
    {
        $schema = $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'fields' => [
                'badField' => [
                    'type' => GraphQlType::string(),
                    'args' => [
                        ['badArg' => GraphQlType::string()]
                    ]
                ]
            ]
        ]));

        $this->setExpectedException(
            InvariantViolation::class,
            'SomeObject.badField(0:) Must be named. Unexpected name: 0'
        );

        $schema->assertValid();
    }

    // DESCRIBE: Type System: Object interfaces must be array

    /**
     * @it accepts an Object type with array interfaces
     */
    public function testAcceptsAnObjectTypeWithArrayInterfaces():void
    {
        $AnotherInterfaceType = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => function () {
            },
            'fields' => ['f' => ['type' => GraphQlType::string()]]
        ]);

        $schema = $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'interfaces' => [$AnotherInterfaceType],
            'fields' => ['f' => ['type' => GraphQlType::string()]]
        ]));
        $schema->assertValid();
    }

    /**
     * @it accepts an Object type with interfaces as a function returning an array
     */
    public function testAcceptsAnObjectTypeWithInterfacesAsAFunctionReturningAnArray():void
    {
        $AnotherInterfaceType = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => function () {
            },
            'fields' => ['f' => ['type' => GraphQlType::string()]]
        ]);

        $schema = $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'interfaces' => function () use ($AnotherInterfaceType) {
                return [$AnotherInterfaceType];
            },
            'fields' => ['f' => ['type' => GraphQlType::string()]]
        ]));
        $schema->assertValid();
    }

    /**
     * @it rejects an Object type with incorrectly typed interfaces
     */
    public function testRejectsAnObjectTypeWithIncorrectlyTypedInterfaces():void
    {
        $this->setExpectedException(
            InvariantViolation::class,
            'SomeObject interfaces must be an Array or a callable which returns an Array.'
        );
        $schema = $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'interfaces' => new \stdClass(),
            'fields' => ['f' => ['type' => GraphQlType::string()]]
        ]));
        $schema->assertValid();
    }

    /**
     * @it rejects an Object that declare it implements same interface more than once
     */
    public function testRejectsAnObjectThatDeclareItImplementsSameInterfaceMoreThanOnce():void
    {
        $NonUniqInterface = new InterfaceType([
            'name' => 'NonUniqInterface',
            'resolveType' => function () {
            },
            'fields' => ['f' => ['type' => GraphQlType::string()]],
        ]);

        $AnotherInterface = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => function(){},
            'fields' => ['f' => ['type' => GraphQlType::string()]],
        ]);

        $schema = $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'interfaces' => function () use ($NonUniqInterface, $AnotherInterface) {
                return [$NonUniqInterface, $AnotherInterface, $NonUniqInterface];
            },
            'fields' => ['f' => ['type' => GraphQlType::string()]]
        ]));

        $this->setExpectedException(
            InvariantViolation::class,
            'SomeObject may declare it implements NonUniqInterface only once.'
        );

        $schema->assertValid();
    }

    // TODO: rejects an Object type with interfaces as a function returning an incorrect type

    /**
     * @it rejects an Object type with interfaces as a function returning an incorrect type
     */
    public function testRejectsAnObjectTypeWithInterfacesAsAFunctionReturningAnIncorrectType():void
    {
        $this->setExpectedException(
            InvariantViolation::class,
            'SomeObject interfaces must be an Array or a callable which returns an Array.'
        );
        $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'interfaces' => function () {
                return new \stdClass();
            },
            'fields' => ['f' => ['type' => GraphQlType::string()]]
        ]));
    }

    // DESCRIBE: Type System: Union types must be array

    /**
     * @it accepts a Union type with array types
     */
    public function testAcceptsAUnionTypeWithArrayTypes():void
    {
        $schema = $this->schemaWithFieldType(new UnionType([
            'name' => 'SomeUnion',
            'resolveType' => function () {
                return null;
            },
            'types' => [$this->SomeObjectType],
        ]));
        $schema->assertValid();
    }

    /**
     * @it accepts a Union type with function returning an array of types
     */
    public function testAcceptsAUnionTypeWithFunctionReturningAnArrayOfTypes():void
    {
        $schema = $this->schemaWithFieldType(new UnionType([
            'name' => 'SomeUnion',
            'resolveType' => function () {
                return null;
            },
            'types' => function () {
                return [$this->SomeObjectType];
            },
        ]));
        $schema->assertValid();
    }

    /**
     * @it rejects a Union type without types
     */
    public function testRejectsAUnionTypeWithoutTypes():void
    {
        $this->setExpectedException(
            InvariantViolation::class,
            'SomeUnion types must be an Array or a callable which returns an Array.'
        );
        $this->schemaWithFieldType(new UnionType([
            'name' => 'SomeUnion',
            'resolveType' => function() {return null;}
        ]));
    }

    /**
     * @it rejects a Union type with empty types
     */
    public function testRejectsAUnionTypeWithemptyTypes():void
    {
        $schema = $this->schemaWithFieldType(new UnionType([
            'name' => 'SomeUnion',
            'resolveType' => function () {
            },
            'types' => []
        ]));

        $this->setExpectedException(
            InvariantViolation::class,
            'SomeUnion types must not be empty'
        );
        $schema->assertValid();
    }

    /**
     * @it rejects a Union type with incorrectly typed types
     */
    public function testRejectsAUnionTypeWithIncorrectlyTypedTypes():void
    {
        $this->setExpectedException(
            InvariantViolation::class,
            'SomeUnion types must be an Array or a callable which returns an Array.'
        );
        $this->schemaWithFieldType(new UnionType([
            'name' => 'SomeUnion',
            'resolveType' => function () {
            },
            'types' => $this->SomeObjectType
        ]));
    }

    /**
     * @it rejects a Union type with duplicated member type
     */
    public function testRejectsAUnionTypeWithDuplicatedMemberType():void
    {
        $schema = $this->schemaWithFieldType(new UnionType([
            'name' => 'SomeUnion',
            'resolveType' => function(){},
            'types' => [
                $this->SomeObjectType,
                $this->SomeObjectType,
            ],
        ]));

        $this->setExpectedException(
            InvariantViolation::class,
            'SomeUnion can include SomeObject type only once.'
        );
        $schema->assertValid();
    }

    // DESCRIBE: Type System: Input Objects must have fields

    /**
     * @it accepts an Input Object type with fields
     */
    public function testAcceptsAnInputObjectTypeWithFields():void
    {
        $schema = $this->schemaWithInputObject(new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => [
                'f' => ['type' => GraphQlType::string()]
            ]
        ]));

        $schema->assertValid();
    }

    /**
     * @it accepts an Input Object type with a field function
     */
    public function testAcceptsAnInputObjectTypeWithAFieldFunction():void
    {
        $schema = $this->schemaWithInputObject(new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => function () {
                return [
                    'f' => ['type' => GraphQlType::string()]
                ];
            }
        ]));

        $schema->assertValid();
    }

    /**
     * @it rejects an Input Object type with missing fields
     */
    public function testRejectsAnInputObjectTypeWithMissingFields():void
    {
        $schema = $this->schemaWithInputObject(new InputObjectType([
            'name' => 'SomeInputObject',
        ]));

        $this->setExpectedException(
            InvariantViolation::class,
            'SomeInputObject fields must not be empty'
        );
        $schema->assertValid();
    }

    /**
     * @it rejects an Input Object type with incorrectly typed fields
     */
    public function testRejectsAnInputObjectTypeWithIncorrectlyTypedFields():void
    {
        $schema = $this->schemaWithInputObject(new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => [
                ['field' => GraphQlType::string()]
            ]
        ]));

        $this->setExpectedException(
            InvariantViolation::class,
            'SomeInputObject.0: Must be named. Unexpected name: 0'
        );
        $schema->assertValid();
    }

    /**
     * @it rejects an Input Object type with empty fields
     */
    public function testRejectsAnInputObjectTypeWithEmptyFields():void
    {
        $this->setExpectedException(
            InvariantViolation::class,
            'SomeInputObject fields must be an array or a callable which returns such an array.'
        );
        $this->schemaWithInputObject(new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => new \stdClass()
        ]));
    }

    /**
     * @it rejects an Input Object type with a field function that returns nothing
     */
    public function testRejectsAnInputObjectTypeWithAFieldFunctionThatReturnsNothing():void
    {
        $this->setExpectedException(
            InvariantViolation::class,
            'SomeInputObject fields must be an array or a callable which returns such an array.'
        );
        $this->schemaWithInputObject(new ObjectType([
            'name' => 'SomeInputObject',
            'fields' => function () {
            }
        ]));
    }

    /**
     * @it rejects an Input Object type with a field function that returns empty
     */
    public function testRejectsAnInputObjectTypeWithAFieldFunctionThatReturnsEmpty():void
    {
        $this->setExpectedException(
            InvariantViolation::class,
            'SomeInputObject fields must be an array or a callable which returns such an array.'
        );
        $this->schemaWithInputObject(new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => function () {
                return new \stdClass();
            }
        ]));
    }

    // DESCRIBE: Type System: Input Object fields must not have resolvers

    /**
     * @it accepts an Input Object type with no resolver
     */
    public function testAcceptsAnInputObjectTypeWithNoResolver():void
    {
        $schema = $this->schemaWithInputObject(new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => [
                'f' => [
                    'type' => GraphQlType::string(),
                ]
            ]
        ]));

        $schema->assertValid();
    }

    /**
     * @it accepts an Input Object type with null resolver
     */
    public function testAcceptsAnInputObjectTypeWithNullResolver():void
    {
        $schema = $this->schemaWithInputObject(new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => [
                'f' => [
                    'type' => GraphQlType::string(),
                    'resolve' => null,
                ]
            ]
        ]));
        $schema->assertValid();
    }

    /**
     * @it rejects an Input Object type with resolver function
     */
    public function testRejectsAnInputObjectTypeWithResolverFunction():void
    {
        $schema = $this->schemaWithInputObject(new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => [
                'f' => [
                    'type' => GraphQlType::string(),
                    'resolve' => function () {
                        return 0;
                    },
                ]
            ]
        ]));

        $this->setExpectedException(
            InvariantViolation::class,
            'SomeInputObject.f field type has a resolve property, but Input Types cannot define resolvers.'
        );
        $schema->assertValid();
    }

    /**
     * @it rejects an Input Object type with resolver constant
     */
    public function testRejectsAnInputObjectTypeWithResolverConstant():void
    {
        $schema = $this->schemaWithInputObject(new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => [
                'f' => [
                    'type' => GraphQlType::string(),
                    'resolve' => new \stdClass(),
                ]
            ]
        ]));

        $this->setExpectedException(
            InvariantViolation::class,
            'SomeInputObject.f field type has a resolve property, but Input Types cannot define resolvers.'
        );
        $schema->assertValid();
    }


    // DESCRIBE: Type System: Object types must be assertable

    /**
     * @it accepts an Object type with an isTypeOf function
     */
    public function testAcceptsAnObjectTypeWithAnIsTypeOfFunction():void
    {
        $schema = $this->schemaWithFieldType(new ObjectType([
            'name' => 'AnotherObject',
            'isTypeOf' => function () {
                return true;
            },
            'fields' => ['f' => ['type' => GraphQlType::string()]]
        ]));
        $schema->assertValid();
    }

    /**
     * @it rejects an Object type with an incorrect type for isTypeOf
     */
    public function testRejectsAnObjectTypeWithAnIncorrectTypeForIsTypeOf():void
    {
        $schema = $this->schemaWithFieldType(new ObjectType([
            'name' => 'AnotherObject',
            'isTypeOf' => new \stdClass(),
            'fields' => ['f' => ['type' => GraphQlType::string()]]
        ]));

        $this->setExpectedException(
            InvariantViolation::class,
            'AnotherObject must provide \'isTypeOf\' as a function'
        );

        $schema->assertValid();
    }

    // DESCRIBE: Type System: Interface types must be resolvable

    /**
     * @it accepts an Interface type defining resolveType
     */
    public function testAcceptsAnInterfaceTypeDefiningResolveType():void
    {
        $AnotherInterfaceType = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => function () {
            },
            'fields' => ['f' => ['type' => GraphQlType::string()]]
        ]);

        $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'interfaces' => [$AnotherInterfaceType],
            'fields' => ['f' => ['type' => GraphQlType::string()]]
        ]));
    }

    /**
     * @it accepts an Interface with implementing type defining isTypeOf
     */
    public function testAcceptsAnInterfaceWithImplementingTypeDefiningIsTypeOf():void
    {
        $InterfaceTypeWithoutResolveType = new InterfaceType([
            'name' => 'InterfaceTypeWithoutResolveType',
            'fields' => ['f' => ['type' => GraphQlType::string()]]
        ]);

        $schema = $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'isTypeOf' => function () {
                return true;
            },
            'interfaces' => [$InterfaceTypeWithoutResolveType],
            'fields' => ['f' => ['type' => GraphQlType::string()]]
        ]));

        $schema->assertValid();
    }

    /**
     * @it accepts an Interface type defining resolveType with implementing type defining isTypeOf
     */
    public function testAcceptsAnInterfaceTypeDefiningResolveTypeWithImplementingTypeDefiningIsTypeOf():void
    {
        $AnotherInterfaceType = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => function () {
            },
            'fields' => ['f' => ['type' => GraphQlType::string()]]
        ]);

        $schema = $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'isTypeOf' => function () {
                return true;
            },
            'interfaces' => [$AnotherInterfaceType],
            'fields' => ['f' => ['type' => GraphQlType::string()]]
        ]));

        $schema->assertValid();
    }

    /**
     * @it rejects an Interface type with an incorrect type for resolveType
     */
    public function testRejectsAnInterfaceTypeWithAnIncorrectTypeForResolveType():void
    {
        $type = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => new \stdClass(),
            'fields' => ['f' => ['type' => GraphQlType::string()]]
        ]);

        $this->setExpectedException(
            InvariantViolation::class,
            'AnotherInterface must provide "resolveType" as a function.'
        );

        $type->assertValid();
    }

    /**
     * @it rejects an Interface type not defining resolveType with implementing type not defining isTypeOf
     */
    public function testRejectsAnInterfaceTypeNotDefiningResolveTypeWithImplementingTypeNotDefiningIsTypeOf():void
    {
        $InterfaceTypeWithoutResolveType = new InterfaceType([
            'name' => 'InterfaceTypeWithoutResolveType',
            'fields' => ['f' => ['type' => GraphQlType::string()]]
        ]);

        $schema = $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'interfaces' => [$InterfaceTypeWithoutResolveType],
            'fields' => ['f' => ['type' => GraphQlType::string()]]
        ]));

        $this->setExpectedException(
            InvariantViolation::class,
            'Interface Type InterfaceTypeWithoutResolveType does not provide a "resolveType" function and implementing '.
            'Type SomeObject does not provide a "isTypeOf" function. There is no way to resolve this implementing type '.
            'during execution.'
        );

        $schema->assertValid();
    }

    // DESCRIBE: Type System: Union types must be resolvable

    /**
     * @it accepts a Union type defining resolveType
     */
    public function testAcceptsAUnionTypeDefiningResolveType():void
    {
        $schema = $this->schemaWithFieldType(new UnionType([
            'name' => 'SomeUnion',
            'resolveType' => function () {
            },
            'types' => [$this->SomeObjectType],
        ]));
        $schema->assertValid();
    }

    /**
     * @it accepts a Union of Object types defining isTypeOf
     */
    public function testAcceptsAUnionOfObjectTypesDefiningIsTypeOf():void
    {
        $schema = $this->schemaWithFieldType(new UnionType([
            'name' => 'SomeUnion',
            'types' => [$this->ObjectWithIsTypeOf],
        ]));

        $schema->assertValid();
    }

    /**
     * @it accepts a Union type defining resolveType of Object types defining isTypeOf
     */
    public function testAcceptsAUnionTypeDefiningResolveTypeOfObjectTypesDefiningIsTypeOf():void
    {
        $schema = $this->schemaWithFieldType(new UnionType([
            'name' => 'SomeUnion',
            'resolveType' => function () {
            },
            'types' => [$this->ObjectWithIsTypeOf],
        ]));
        $schema->assertValid();
    }

    /**
     * @it rejects a Union type with an incorrect type for resolveType
     */
    public function testRejectsAUnionTypeWithAnIncorrectTypeForResolveType():void
    {
        $schema = $this->schemaWithFieldType(new UnionType([
            'name' => 'SomeUnion',
            'resolveType' => new \stdClass(),
            'types' => [$this->ObjectWithIsTypeOf],
        ]));

        $this->setExpectedException(
            InvariantViolation::class,
            'SomeUnion must provide "resolveType" as a function.'
        );

        $schema->assertValid();
    }

    /**
     * @it rejects a Union type not defining resolveType of Object types not defining isTypeOf
     */
    public function testRejectsAUnionTypeNotDefiningResolveTypeOfObjectTypesNotDefiningIsTypeOf():void
    {
        $schema = $this->schemaWithFieldType(new UnionType([
            'name' => 'SomeUnion',
            'types' => [$this->SomeObjectType],
        ]));

        $this->setExpectedException(
            InvariantViolation::class,
            'Union type "SomeUnion" does not provide a "resolveType" function and possible type "SomeObject" '.
            'does not provide an "isTypeOf" function. There is no way to resolve this possible type during execution.'
        );

        $schema->assertValid();
    }

    // DESCRIBE: Type System: Scalar types must be serializable

    /**
     * @it accepts a Scalar type defining serialize
     */
    public function testAcceptsAScalarTypeDefiningSerialize():void
    {
        $schema = $this->schemaWithFieldType(new CustomScalarType([
            'name' => 'SomeScalar',
            'serialize' => function () {
            },
        ]));
        $schema->assertValid();
    }

    /**
     * @it rejects a Scalar type not defining serialize
     */
    public function testRejectsAScalarTypeNotDefiningSerialize():void
    {
        $schema = $this->schemaWithFieldType(new CustomScalarType([
            'name' => 'SomeScalar',
        ]));

        $this->setExpectedException(
            InvariantViolation::class,
            'SomeScalar must provide "serialize" function. If this custom Scalar is also used as an input type, '.
            'ensure "parseValue" and "parseLiteral" functions are also provided.'
        );

        $schema->assertValid();
    }

    /**
     * @it rejects a Scalar type defining serialize with an incorrect type
     */
    public function testRejectsAScalarTypeDefiningSerializeWithAnIncorrectType():void
    {
        $schema = $this->schemaWithFieldType(new CustomScalarType([
            'name' => 'SomeScalar',
            'serialize' => new \stdClass()
        ]));

        $this->setExpectedException(
            InvariantViolation::class,
            'SomeScalar must provide "serialize" function. If this custom Scalar ' .
            'is also used as an input type, ensure "parseValue" and "parseLiteral" ' .
            'functions are also provided.'
        );

        $schema->assertValid();
    }

    /**
     * @it accepts a Scalar type defining parseValue and parseLiteral
     */
    public function testAcceptsAScalarTypeDefiningParseValueAndParseLiteral():void
    {
        $schema = $this->schemaWithFieldType(new CustomScalarType([
            'name' => 'SomeScalar',
            'serialize' => function () {
            },
            'parseValue' => function () {
            },
            'parseLiteral' => function () {
            },
        ]));

        $schema->assertValid();
    }

    /**
     * @it rejects a Scalar type defining parseValue but not parseLiteral
     */
    public function testRejectsAScalarTypeDefiningParseValueButNotParseLiteral():void
    {
        $schema = $this->schemaWithFieldType(new CustomScalarType([
            'name' => 'SomeScalar',
            'serialize' => function () {
            },
            'parseValue' => function () {
            },
        ]));

        $this->setExpectedException(
            InvariantViolation::class,
            'SomeScalar must provide both "parseValue" and "parseLiteral" functions.'
        );
        $schema->assertValid();
    }

    /**
     * @it rejects a Scalar type defining parseLiteral but not parseValue
     */
    public function testRejectsAScalarTypeDefiningParseLiteralButNotParseValue():void
    {
        $schema = $this->schemaWithFieldType(new CustomScalarType([
            'name' => 'SomeScalar',
            'serialize' => function () {
            },
            'parseLiteral' => function () {
            },
        ]));

        $this->setExpectedException(
            InvariantViolation::class,
            'SomeScalar must provide both "parseValue" and "parseLiteral" functions.'
        );

        $schema->assertValid();
    }

    /**
     * @it rejects a Scalar type defining parseValue and parseLiteral with an incorrect type
     */
    public function testRejectsAScalarTypeDefiningParseValueAndParseLiteralWithAnIncorrectType():void
    {
        $schema = $this->schemaWithFieldType(new CustomScalarType([
            'name' => 'SomeScalar',
            'serialize' => function () {
            },
            'parseValue' => new \stdClass(),
            'parseLiteral' => new \stdClass(),
        ]));

        $this->setExpectedException(
            InvariantViolation::class,
            'SomeScalar must provide both "parseValue" and "parseLiteral" functions.'
        );

        $schema->assertValid();
    }


    // DESCRIBE: Type System: Enum types must be well defined

    /**
     * @it accepts a well defined Enum type with empty value definition
     */
    public function testAcceptsAWellDefinedEnumTypeWithEmptyValueDefinition():void
    {
        $type = new EnumType([
            'name' => 'SomeEnum',
            'values' => [
                'FOO' => [],
                'BAR' => [],
            ]
        ]);

        $type->assertValid();
    }

    // TODO: accepts a well defined Enum type with internal value definition

    /**
     * @it accepts a well defined Enum type with internal value definition
     */
    public function testAcceptsAWellDefinedEnumTypeWithInternalValueDefinition():void
    {
        $type = new EnumType([
            'name' => 'SomeEnum',
            'values' => [
                'FOO' => ['value' => 10],
                'BAR' => ['value' => 20],
            ]
        ]);
        $type->assertValid();
    }

    /**
     * @it rejects an Enum type without values
     */
    public function testRejectsAnEnumTypeWithoutValues():void
    {
        $type = new EnumType([
            'name' => 'SomeEnum',
        ]);

        $this->setExpectedException(
            InvariantViolation::class,
            'SomeEnum values must be an array.'
        );

        $type->assertValid();
    }

    /**
     * @it rejects an Enum type with empty values
     */
    public function testRejectsAnEnumTypeWithEmptyValues():void
    {
        $type = new EnumType([
            'name' => 'SomeEnum',
            'values' => []
        ]);

        $this->setExpectedException(
            InvariantViolation::class,
            'SomeEnum values must be not empty.'
        );

        $type->assertValid();
    }

    /**
     * @it rejects an Enum type with incorrectly typed values
     */
    public function testRejectsAnEnumTypeWithIncorrectlyTypedValues():void
    {
        $type = new EnumType([
            'name' => 'SomeEnum',
            'values' => [
                ['FOO' => 10]
            ]
        ]);

        $this->setExpectedException(
            InvariantViolation::class,
            'SomeEnum values must be an array with value names as keys.'
        );
        $type->assertValid();
    }

    public function invalidEnumValueName():array<array<string>>
    {
        return [
            ['#value', 'SomeEnum has value with invalid name: "#value" (Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but "#value" does not.)'],
            ['true', 'SomeEnum: "true" can not be used as an Enum value.'],
            ['false', 'SomeEnum: "false" can not be used as an Enum value.'],
            ['null', 'SomeEnum: "null" can not be used as an Enum value.'],
        ];
    }

    public function testDoesNotAllowIsDeprecatedWithoutDeprecationReasonOnEnum():void
    {
        $enum = new EnumType([
            'name' => 'SomeEnum',
            'values' => [
                'value' => ['isDeprecated' => true]
            ]
        ]);
        $this->setExpectedException(
            InvariantViolation::class,
            'SomeEnum.value should provide "deprecationReason" instead of "isDeprecated".'
        );
        $enum->assertValid();
    }

    private function enumValue(string $name):EnumType
    {
        return new EnumType([
            'name' => 'SomeEnum',
            'values' => [
                $name => []
            ]
        ]);
    }

    /**
     * @it rejects an Enum type with incorrectly named values
     */
    <<DataProvider('invalidEnumValueName')>>
    public function testRejectsAnEnumTypeWithIncorrectlyNamedValues(string $name, string $expectedMessage):void
    {
        $enum = $this->enumValue($name);

        $this->setExpectedException(InvariantViolation::class, $expectedMessage);
        $enum->assertValid();
    }

    // DESCRIBE: Type System: Object fields must have output types

    /**
     * @it accepts an output type as an Object field type
     */
    public function testAcceptsAnOutputTypeAsNnObjectFieldType():void
    {
        foreach ($this->outputTypes ?? [] as $type) {
            $schema = $this->schemaWithObjectFieldOfType($type);
            $schema->assertValid();
        }
    }

    // TODO: rejects an empty Object field type

    /**
     * @it rejects an empty Object field type
     */
    public function testRejectsAnEmptyObjectFieldType():void
    {
        $schema = $this->schemaWithObjectFieldOfType(null);

        $this->setExpectedException(
            InvariantViolation::class,
            'BadObject.badField field type must be Output Type but got: null'
        );

        $schema->assertValid();
    }

    /**
     * @it rejects a non-output type as an Object field type
     */
    public function testRejectsANonOutputTypeAsAnObjectFieldType():void
    {
        foreach ($this->notOutputTypes ?? [] as $type) {
            $schema = $this->schemaWithObjectFieldOfType($type);

            try {
                $schema->assertValid();
                self::fail('Expected exception not thrown for ' . Utils::printSafe($type));
            } catch (InvariantViolation $e) {
                expect($e->getMessage())
                    ->toBePHPEqual('BadObject.badField field type must be Output Type but got: ' . Utils::printSafe($type));
            }
        }
    }

    // DESCRIBE: Type System: Object fields must have valid resolve values

    /**
     * @it accepts a lambda as an Object field resolver
     */
    public function testAcceptsALambdaAsAnObjectFieldResolver():void
    {
        $schema = $this->schemaWithObjectWithFieldResolver(function() {return [];});
        $schema->assertValid();
    }

    /**
     * @it rejects an empty Object field resolver
     */
    public function testRejectsAnEmptyObjectFieldResolver():void
    {
        $schema = $this->schemaWithObjectWithFieldResolver([]);

        $this->setExpectedException(
            InvariantViolation::class,
            'BadResolver.badField field resolver must be a function if provided, but got: array(0)'
        );

        $schema->assertValid();
    }

    /**
     * @it rejects a constant scalar value resolver
     */
    public function testRejectsAConstantScalarValueResolver():void
    {
        $schema = $this->schemaWithObjectWithFieldResolver(0);
        $this->setExpectedException(
            InvariantViolation::class,
            'BadResolver.badField field resolver must be a function if provided, but got: 0'
        );
        $schema->assertValid();
    }



    // DESCRIBE: Type System: Objects can only implement interfaces

    /**
     * @it accepts an Object implementing an Interface
     */
    public function testAcceptsAnObjectImplementingAnInterface():void
    {
        $AnotherInterfaceType = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => function () {
            },
            'fields' => ['f' => ['type' => GraphQlType::string()]]
        ]);

        $schema = $this->schemaWithObjectImplementingType($AnotherInterfaceType);
        $schema->assertValid();
    }

    /**
     * @it rejects an Object implementing a non-Interface type
     */
    public function testRejectsAnObjectImplementingANonInterfaceType():void
    {
        foreach ($this->notInterfaceTypes ?? [] as $type) {
            $schema = $this->schemaWithObjectImplementingType($type);

            try {
                $schema->assertValid();
                self::fail('Exepected exception not thrown for type ' . $type);
            } catch (InvariantViolation $e) {
                expect($e->getMessage())
                    ->toBePHPEqual('BadObject may only implement Interface types, it cannot implement ' . $type . '.');
            }
        }
    }


    // DESCRIBE: Type System: Unions must represent Object types

    /**
     * @it accepts a Union of an Object Type
     */
    public function testAcceptsAUnionOfAnObjectType():void
    {
        $schema = $this->schemaWithUnionOfType($this->SomeObjectType);
        $schema->assertValid();
    }

    /**
     * @it rejects a Union of a non-Object type
     */
    public function testRejectsAUnionOfANonObjectType():void
    {
        foreach ($this->notObjectTypes ?? [] as $type) {
            $schema = $this->schemaWithUnionOfType($type);
            try {
                $schema->assertValid();
                self::fail('Expected exception not thrown for type: ' . $type);
            } catch (InvariantViolation $e) {
                expect($e->getMessage())
                    ->toBePHPEqual('BadUnion may only contain Object types, it cannot contain: ' . $type . '.');
            }
        }

        // "BadUnion may only contain Object types, it cannot contain: $type."
    }


    // DESCRIBE: Type System: Interface fields must have output types

    /**
     * @it accepts an output type as an Interface field type
     */
    public function testAcceptsAnOutputTypeAsAnInterfaceFieldType():void
    {
        foreach ($this->outputTypes ?? [] as $type) {
            $schema = $this->schemaWithInterfaceFieldOfType($type);
            $schema->assertValid();
        }
    }

    /**
     * @it rejects an empty Interface field type
     */
    public function testRejectsAnEmptyInterfaceFieldType():void
    {
        $schema = $this->schemaWithInterfaceFieldOfType(null);

        $this->setExpectedException(
            InvariantViolation::class,
            'BadInterface.badField field type must be Output Type but got: null'
        );

        $schema->assertValid();
    }

    /**
     * @it rejects a non-output type as an Interface field type
     */
    public function testRejectsANonOutputTypeAsAnInterfaceFieldType():void
    {
        foreach ($this->notOutputTypes ?? [] as $type) {
            $schema = $this->schemaWithInterfaceFieldOfType($type);

            try {
                $schema->assertValid();
                self::fail('Expected exception not thrown for type ' . $type);
            } catch (InvariantViolation $e) {
                expect($e->getMessage())
                    ->toBePHPEqual('BadInterface.badField field type must be Output Type but got: ' . Utils::printSafe($type));
            }
        }
    }


    // DESCRIBE: Type System: Field arguments must have input types

    /**
     * @it accepts an input type as a field arg type
     */
    public function testAcceptsAnInputTypeAsAFieldArgType():void
    {
        foreach ($this->inputTypes ?? [] as $type) {
            $schema = $this->schemaWithArgOfType($type);
            $schema->assertValid();
        }
    }

    /**
     * @it rejects an empty field arg type
     */
    public function testRejectsAnEmptyFieldArgType():void
    {
        $schema = $this->schemaWithArgOfType(null);

        $this->setExpectedException(InvariantViolation::class, 'BadObject.badField(badArg): argument type must be Input Type but got: null');
        $schema->assertValid();
    }

    /**
     * @it rejects a non-input type as a field arg type
     */
    public function testRejectsANonInputTypeAsAFieldArgType():void
    {
        foreach ($this->notInputTypes ?? [] as $type) {
            $schema = $this->schemaWithArgOfType($type);
            try {
                $schema->assertValid();
                self::fail('Expected exception not thrown for type ' . $type);
            } catch (InvariantViolation $e) {
                expect($e->getMessage())
                    ->toBePHPEqual('BadObject.badField(badArg): argument type must be Input Type but got: ' . Utils::printSafe($type));
            }
        }
    }


    // DESCRIBE: Type System: Input Object fields must have input types

    /**
     * @it accepts an input type as an input field type
     */
    public function testAcceptsAnInputTypeAsAnInputFieldType():void
    {
        foreach ($this->inputTypes ?? [] as $type) {
            $schema = $this->schemaWithInputFieldOfType($type);
            $schema->assertValid();
        }
    }

    /**
     * @it rejects an empty input field type
     */
    public function testRejectsAnEmptyInputFieldType():void
    {
        $schema = $this->schemaWithInputFieldOfType(null);

        $this->setExpectedException(
            InvariantViolation::class,
            'BadInputObject.badField field type must be Input Type but got: null.'
        );
        $schema->assertValid();
    }

    /**
     * @it rejects a non-input type as an input field type
     */
    public function testRejectsANonInputTypeAsAnInputFieldType():void
    {
        foreach ($this->notInputTypes ?? [] as $type) {
            $schema = $this->schemaWithInputFieldOfType($type);
            try {
                $schema->assertValid();
                self::fail('Expected exception not thrown for type ' . $type);
            } catch (InvariantViolation $e) {
                expect($e->getMessage())
                    ->toBePHPEqual("BadInputObject.badField field type must be Input Type but got: " . Utils::printSafe($type) . ".");
            }
        }
    }


    // DESCRIBE: Type System: List must accept GraphQL types

    /**
     * @it accepts an type as item type of list
     */
    public function testAcceptsAnTypeAsItemTypeOfList():void
    {
        foreach ($this->types ?? [] as $type) {
            try {
                GraphQlType::listOf($type);
            } catch (\Exception $e) {
                throw new \Exception("Expection thrown for type $type: {$e->getMessage()}", 0, $e);
            }
        }
    }

    /**
     * @it rejects a non-type as item type of list
     */
    public function testRejectsANonTypeAsItemTypeOfList():void
    {
        $notTypes = [
            [],
            new \stdClass(),
            'String',
            10,
            null,
            true,
            false,
            // TODO: function() {}
        ];
        foreach ($notTypes as $type) {
            try {
                GraphQlType::listOf($type);
                self::fail("Expected exception not thrown for: " . Utils::printSafe($type));
            } catch (InvariantViolation $e) {
                expect($e->getMessage())
                    ->toBePHPEqual('Can only create List of a GraphQLType but got: ' . Utils::printSafe($type));
            }
        }
    }


    // DESCRIBE: Type System: NonNull must accept GraphQL types

    /**
     * @it accepts an type as nullable type of non-null
     */
    public function testAcceptsAnTypeAsNullableTypeOfNonNull():void
    {
        $nullableTypes = [
            GraphQlType::string(),
            $this->SomeScalarType,
            $this->SomeObjectType,
            $this->SomeUnionType,
            $this->SomeInterfaceType,
            $this->SomeEnumType,
            $this->SomeInputObjectType,
            GraphQlType::listOf(GraphQlType::string()),
            GraphQlType::listOf(GraphQlType::nonNull(GraphQlType::string())),
        ];
        foreach ($nullableTypes as $type) {
            try {
                GraphQlType::nonNull($type);
            } catch (\Exception $e) {
                throw new \Exception("Exception thrown for type $type: " . $e->getMessage(), 0, $e);
            }
        }
    }

    // TODO: rejects a non-type as nullable type of non-null: ${type}

    /**
     * @it rejects a non-type as nullable type of non-null
     */
    public function testRejectsANonTypeAsNullableTypeOfNonNull():void
    {
        $notNullableTypes = [
            GraphQlType::nonNull(GraphQlType::string()),
            [],
            new \stdClass(),
            'String',
            null,
            true,
            false
        ];
        foreach ($notNullableTypes as $type) {
            try {
                GraphQlType::nonNull($type);
                self::fail("Expected exception not thrown for: " . Utils::printSafe($type));
            } catch (InvariantViolation $e) {
                expect($e->getMessage())
                    ->toBePHPEqual('Can only create NoNull of a Nullable GraphQLType but got: ' . Utils::printSafe($type));
            }
        }
    }


    // DESCRIBE: Objects must adhere to Interface they implement

    /**
     * @it accepts an Object which implements an Interface
     */
    public function testAcceptsAnObjectWhichImplementsAnInterface():void
    {
        $AnotherInterface = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => function () {
            },
            'fields' => [
                'field' => [
                    'type' => GraphQlType::string(),
                    'args' => [
                        'input' => ['type' => GraphQlType::string()]
                    ]
                ]
            ]
        ]);

        $AnotherObject = new ObjectType([
            'name' => 'AnotherObject',
            'interfaces' => [$AnotherInterface],
            'fields' => [
                'field' => [
                    'type' => GraphQlType::string(),
                    'args' => [
                        'input' => ['type' => GraphQlType::string()]
                    ]
                ]
            ]
        ]);

        $schema = $this->schemaWithFieldType($AnotherObject);
        $schema->assertValid();
    }

    /**
     * @it accepts an Object which implements an Interface along with more fields
     */
    public function testAcceptsAnObjectWhichImplementsAnInterfaceAlongWithMoreFields():void
    {
        $AnotherInterface = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => function () {
            },
            'fields' => [
                'field' => [
                    'type' => GraphQlType::string(),
                    'args' => [
                        'input' => ['type' => GraphQlType::string()],
                    ]
                ]
            ]
        ]);

        $AnotherObject = new ObjectType([
            'name' => 'AnotherObject',
            'interfaces' => [$AnotherInterface],
            'fields' => [
                'field' => [
                    'type' => GraphQlType::string(),
                    'args' => [
                        'input' => ['type' => GraphQlType::string()],
                    ]
                ],
                'anotherfield' => ['type' => GraphQlType::string()]
            ]
        ]);

        $schema = $this->schemaWithFieldType($AnotherObject);
        $schema->assertValid();
    }

    /**
     * @it accepts an Object which implements an Interface field along with additional optional arguments
     */
    public function testAcceptsAnObjectWhichImplementsAnInterfaceFieldAlongWithAdditionalOptionalArguments():void
    {
        $AnotherInterface = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => function () {
            },
            'fields' => [
                'field' => [
                    'type' => GraphQlType::string(),
                    'args' => [
                        'input' => ['type' => GraphQlType::string()],
                    ]
                ]
            ]
        ]);

        $AnotherObject = new ObjectType([
            'name' => 'AnotherObject',
            'interfaces' => [$AnotherInterface],
            'fields' => [
                'field' => [
                    'type' => GraphQlType::string(),
                    'args' => [
                        'input' => ['type' => GraphQlType::string()],
                        'anotherInput' => ['type' => GraphQlType::string()],
                    ]
                ]
            ]
        ]);

        $schema = $this->schemaWithFieldType($AnotherObject);
        $schema->assertValid();
    }

    /**
     * @it rejects an Object which implements an Interface field along with additional required arguments
     */
    public function testRejectsAnObjectWhichImplementsAnInterfaceFieldAlongWithAdditionalRequiredArguments():void
    {
        $AnotherInterface = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => function () {
            },
            'fields' => [
                'field' => [
                    'type' => GraphQlType::string(),
                    'args' => [
                        'input' => ['type' => GraphQlType::string()],
                    ]
                ]
            ]
        ]);

        $AnotherObject = new ObjectType([
            'name' => 'AnotherObject',
            'interfaces' => [$AnotherInterface],
            'fields' => [
                'field' => [
                    'type' => GraphQlType::string(),
                    'args' => [
                        'input' => ['type' => GraphQlType::string()],
                        'anotherInput' => ['type' => GraphQlType::nonNull(GraphQlType::string())],
                    ]
                ]
            ]
        ]);

        $schema = $this->schemaWithFieldType($AnotherObject);

        $this->setExpectedException(
            InvariantViolation::class,
            'AnotherObject.field(anotherInput:) is of required type "String!" but is not also provided by the interface AnotherInterface.field.'
        );

        $schema->assertValid();
    }

    /**
     * @it rejects an Object missing an Interface field
     */
    public function testRejectsAnObjectMissingAnInterfaceField():void
    {
        $AnotherInterface = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => function () {
            },
            'fields' => [
                'field' => [
                    'type' => GraphQlType::string(),
                    'args' => [
                        'input' => ['type' => GraphQlType::string()],
                    ]
                ]
            ]
        ]);

        $AnotherObject = new ObjectType([
            'name' => 'AnotherObject',
            'interfaces' => [$AnotherInterface],
            'fields' => [
                'anotherfield' => ['type' => GraphQlType::string()]
            ]
        ]);

        $schema = $this->schemaWithFieldType($AnotherObject);

        $this->setExpectedException(
            InvariantViolation::class,
            'AnotherInterface expects field "field" but AnotherObject does not provide it'
        );
        $schema->assertValid();
    }

    /**
     * @it rejects an Object with an incorrectly typed Interface field
     */
    public function testRejectsAnObjectWithAnIncorrectlyTypedInterfaceField():void
    {
        $AnotherInterface = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => function () {
            },
            'fields' => [
                'field' => ['type' => GraphQlType::string()]
            ]
        ]);

        $AnotherObject = new ObjectType([
            'name' => 'AnotherObject',
            'interfaces' => [$AnotherInterface],
            'fields' => [
                'field' => ['type' => $this->SomeScalarType]
            ]
        ]);

        $schema = $this->schemaWithFieldType($AnotherObject);
        $this->setExpectedException(
            InvariantViolation::class,
            'AnotherInterface.field expects type "String" but AnotherObject.field provides type "SomeScalar"'
        );
        $schema->assertValid();
    }

    /**
     * @it rejects an Object with a differently typed Interface field
     */
    public function testRejectsAnObjectWithADifferentlyTypedInterfaceField():void
    {
        $TypeA = new ObjectType([
            'name' => 'A',
            'fields' => [
                'foo' => ['type' => GraphQlType::string()]
            ]
        ]);

        $TypeB = new ObjectType([
            'name' => 'B',
            'fields' => [
                'foo' => ['type' => GraphQlType::string()]
            ]
        ]);

        $AnotherInterface = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => function () {
            },
            'fields' => [
                'field' => ['type' => $TypeA]
            ]
        ]);

        $AnotherObject = new ObjectType([
            'name' => 'AnotherObject',
            'interfaces' => [$AnotherInterface],
            'fields' => [
                'field' => ['type' => $TypeB]
            ]
        ]);

        $schema = $this->schemaWithFieldType($AnotherObject);

        $this->setExpectedException(
            InvariantViolation::class,
            'AnotherInterface.field expects type "A" but AnotherObject.field provides type "B"'
        );

        $schema->assertValid();
    }

    /**
     * @it accepts an Object with a subtyped Interface field (interface)
     */
    public function testAcceptsAnObjectWithASubtypedInterfaceFieldForInterface():void
    {
        $AnotherInterface = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => function () {
            },
            /* HH_FIXME[2087]*/
            'fields' => function () use (&$AnotherInterface) {
                return [
                    'field' => ['type' => $AnotherInterface]
                ];
            }
        ]);

        $AnotherObject = new ObjectType([
            'name' => 'AnotherObject',
            'interfaces' => [$AnotherInterface],
            /* HH_FIXME[2087]*/
            'fields' => function () use (&$AnotherObject) {
                return [
                    'field' => ['type' => $AnotherObject]
                ];
            }
        ]);

        $schema = $this->schemaWithFieldType($AnotherObject);
        $schema->assertValid();
    }

    /**
     * @it accepts an Object with a subtyped Interface field (union)
     */
    public function testAcceptsAnObjectWithASubtypedInterfaceFieldForUnion():void
    {
        $AnotherInterface = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => function () {
            },
            'fields' => [
                'field' => ['type' => $this->SomeUnionType]
            ]
        ]);

        $AnotherObject = new ObjectType([
            'name' => 'AnotherObject',
            'interfaces' => [$AnotherInterface],
            'fields' => [
                'field' => ['type' => $this->SomeObjectType]
            ]
        ]);

        $schema = $this->schemaWithFieldType($AnotherObject);
        $schema->assertValid();
    }

    /**
     * @it rejects an Object missing an Interface argument
     */
    public function testRejectsAnObjectMissingAnInterfaceArgument():void
    {
        $AnotherInterface = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => function () {
            },
            'fields' => [
                'field' => [
                    'type' => GraphQlType::string(),
                    'args' => [
                        'input' => ['type' => GraphQlType::string()],
                    ]
                ]
            ]
        ]);

        $AnotherObject = new ObjectType([
            'name' => 'AnotherObject',
            'interfaces' => [$AnotherInterface],
            'fields' => [
                'field' => [
                    'type' => GraphQlType::string(),
                ]
            ]
        ]);

        $schema = $this->schemaWithFieldType($AnotherObject);

        $this->setExpectedException(
            InvariantViolation::class,
            'AnotherInterface.field expects argument "input" but AnotherObject.field does not provide it.'
        );

        $schema->assertValid();
    }

    /**
     * @it rejects an Object with an incorrectly typed Interface argument
     */
    public function testRejectsAnObjectWithAnIncorrectlyTypedInterfaceArgument():void
    {
        $AnotherInterface = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => function () {
            },
            'fields' => [
                'field' => [
                    'type' => GraphQlType::string(),
                    'args' => [
                        'input' => ['type' => GraphQlType::string()],
                    ]
                ]
            ]
        ]);

        $AnotherObject = new ObjectType([
            'name' => 'AnotherObject',
            'interfaces' => [$AnotherInterface],
            'fields' => [
                'field' => [
                    'type' => GraphQlType::string(),
                    'args' => [
                        'input' => ['type' => $this->SomeScalarType],
                    ]
                ]
            ]
        ]);

        $schema = $this->schemaWithFieldType($AnotherObject);

        $this->setExpectedException(
            InvariantViolation::class,
            'AnotherInterface.field(input:) expects type "String" but AnotherObject.field(input:) provides type "SomeScalar".'
        );

        $schema->assertValid();
    }

    /**
     * @it accepts an Object with an equivalently modified Interface field type
     */
    public function testAcceptsAnObjectWithAnEquivalentlyModifiedInterfaceFieldType():void
    {
        $AnotherInterface = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => function () {
            },
            'fields' => [
                'field' => ['type' => GraphQlType::nonNull(GraphQlType::listOf(GraphQlType::string()))]
            ]
        ]);

        $AnotherObject = new ObjectType([
            'name' => 'AnotherObject',
            'interfaces' => [$AnotherInterface],
            'fields' => [
                'field' => ['type' => GraphQlType::nonNull(GraphQlType::listOf(GraphQlType::string()))]
            ]
        ]);

        $schema = $this->schemaWithFieldType($AnotherObject);
        $schema->assertValid();
    }

    /**
     * @it rejects an Object with a non-list Interface field list type
     */
    public function testRejectsAnObjectWithANonListInterfaceFieldListType():void
    {
        $AnotherInterface = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => function () {
            },
            'fields' => [
                'field' => ['type' => GraphQlType::listOf(GraphQlType::string())]
            ]
        ]);

        $AnotherObject = new ObjectType([
            'name' => 'AnotherObject',
            'interfaces' => [$AnotherInterface],
            'fields' => [
                'field' => ['type' => GraphQlType::string()]
            ]
        ]);

        $schema = $this->schemaWithFieldType($AnotherObject);

        $this->setExpectedException(
            InvariantViolation::class,
            'AnotherInterface.field expects type "[String]" but AnotherObject.field provides type "String"'
        );

        $schema->assertValid();
    }

    /**
     * @it rejects an Object with a list Interface field non-list type
     */
    public function testRejectsAnObjectWithAListInterfaceFieldNonListType():void
    {
        $AnotherInterface = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => function () {
            },
            'fields' => [
                'field' => ['type' => GraphQlType::string()]
            ]
        ]);

        $AnotherObject = new ObjectType([
            'name' => 'AnotherObject',
            'interfaces' => [$AnotherInterface],
            'fields' => [
                'field' => ['type' => GraphQlType::listOf(GraphQlType::string())]
            ]
        ]);

        $schema = $this->schemaWithFieldType($AnotherObject);
        $this->setExpectedException(
            InvariantViolation::class,
            'AnotherInterface.field expects type "String" but AnotherObject.field provides type "[String]"'
        );
        $schema->assertValid();
    }

    /**
     * @it accepts an Object with a subset non-null Interface field type
     */
    public function testAcceptsAnObjectWithASubsetNonNullInterfaceFieldType():void
    {
        $AnotherInterface = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => function () {
            },
            'fields' => [
                'field' => ['type' => GraphQlType::string()]
            ]
        ]);

        $AnotherObject = new ObjectType([
            'name' => 'AnotherObject',
            'interfaces' => [$AnotherInterface],
            'fields' => [
                'field' => ['type' => GraphQlType::nonNull(GraphQlType::string())]
            ]
        ]);

        $schema = $this->schemaWithFieldType($AnotherObject);
        $schema->assertValid();
    }

    /**
     * @it rejects an Object with a superset nullable Interface field type
     */
    public function testRejectsAnObjectWithASupersetNullableInterfaceFieldType():void
    {
        $AnotherInterface = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => function () {
            },
            'fields' => [
                'field' => ['type' => GraphQlType::nonNull(GraphQlType::string())]
            ]
        ]);

        $AnotherObject = new ObjectType([
            'name' => 'AnotherObject',
            'interfaces' => [$AnotherInterface],
            'fields' => [
                'field' => ['type' => GraphQlType::string()]
            ]
        ]);

        $schema = $this->schemaWithFieldType($AnotherObject);

        $this->setExpectedException(
            InvariantViolation::class,
            'AnotherInterface.field expects type "String!" but AnotherObject.field provides type "String"'
        );

        $schema->assertValid();
    }

    /**
     * @it does not allow isDeprecated without deprecationReason on field
     */
    public function testDoesNotAllowIsDeprecatedWithoutDeprecationReasonOnField():void
    {
        $OldObject = new ObjectType([
            'name' => 'OldObject',
            'fields' => [
                'field' => [
                    'type' => GraphQlType::string(),
                    'isDeprecated' => true
                ]
            ]
        ]);

        $schema = $this->schemaWithFieldType($OldObject);
        $this->setExpectedException(
            InvariantViolation::class,
            'OldObject.field should provide "deprecationReason" instead of "isDeprecated".'
        );
        $schema->assertValid();
    }

    public function testRejectsDifferentInstancesOfTheSameType():void
    {
        // Invalid: always creates new instance vs returning one from registry
        $typeLoader = function($name) use (&$typeLoader) {
            switch ($name) {
                case 'Query':
                    return new ObjectType([
                        'name' => 'Query',
                        'fields' => [
                            'test' => GraphQlType::string()
                        ]
                    ]);
                default:
                    return null;
            }
        };

        $schema = new Schema([
            'query' => $typeLoader('Query'),
            'typeLoader' => $typeLoader
        ]);
        $this->setExpectedException(
            InvariantViolation::class,
            'Type loader returns different instance for Query than field/argument definitions. '.
            'Make sure you always return the same instance for the same type name.'
        );
        $schema->assertValid();
    }


    private function assertEachCallableThrows(array<(function():GraphQlType)> $closures, string $expectedError):void
    {
        foreach ($closures as $index => $factory) {
            try {
                $factory();
                self::fail('Expected exception not thrown for entry ' . $index);
            } catch (InvariantViolation $e) {
                expect($e->getMessage())->toBePHPEqual($expectedError, 'Error in callable #' . $index);
            }
        }
    }

    private function assertWarnsOnce(array<(function():GraphQlType)> $closures, string $expectedError):void
    {
        $warned = false;
        \set_error_handler(
            function($errno, $errstr, $errfile, $errline){
                throw new HackWarningException($errstr, $errno, 0, $errfile, $errline);
            },
            E_WARNING | E_USER_WARNING
        );

        foreach ($closures as $index => $factory) {
            if (!$warned) {
                try {
                    $factory();
                    self::fail('Expected exception not thrown for entry ' . $index);
                } catch (HackWarningException $e) {
                    $warned = true;
                    expect($e->getMessage())->toBePHPEqual($expectedError, 'Error in callable #' . $index);
                }

            } else {
                // Should not throw
                $factory();
            }
        }
        \restore_error_handler();
    }

    private function schemaWithFieldType(GraphQlType $type):Schema
    {
        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => ['f' => ['type' => $type]]
            ]),
            'types' => [$type],
        ]);
    }

    private function schemaWithInputObject(GraphQlType $inputObjectType):Schema
    {
        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'f' => [
                        'type' => GraphQlType::string(),
                        'args' => [
                            'input' => ['type' => $inputObjectType]
                        ]
                    ]
                ]
            ])
        ]);
    }

    private function schemaWithObjectFieldOfType(mixed $fieldType):Schema
    {
        $BadObjectType = new ObjectType([
            'name' => 'BadObject',
            'fields' => [
                'badField' => ['type' => $fieldType]
            ]
        ]);

        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'f' => ['type' => $BadObjectType]
                ]
            ]),
            'types' => [$this->SomeObjectType]
        ]);
    }

    private function schemaWithObjectWithFieldResolver(mixed $resolveValue):Schema
    {
        $BadResolverType = new ObjectType([
            'name' => 'BadResolver',
            'fields' => [
                'badField' => [
                    'type' => GraphQlType::string(),
                    'resolve' => $resolveValue
                ]
            ]
        ]);

        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'f' => ['type' => $BadResolverType]
                ]
            ])
        ]);
    }

    private function schemaWithObjectImplementingType(mixed $implementedType):Schema
    {
        $BadObjectType = new ObjectType([
            'name' => 'BadObject',
            'interfaces' => [$implementedType],
            'fields' => ['f' => ['type' => GraphQlType::string()]]
        ]);

        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'f' => ['type' => $BadObjectType]
                ]
            ]),
            'types' => [$BadObjectType]
        ]);
    }

    private function withModifiers(array<GraphQlType> $types):array<GraphQlType>
    {
        return \array_merge(
            $types,
            Utils::map($types, function ($type, $key) {
                return GraphQlType::listOf($type);
            }),
            Utils::map($types, function ($type, $key) {
                return GraphQlType::nonNull($type);
            }),
            Utils::map($types, function ($type, $key) {
                return GraphQlType::nonNull(GraphQlType::listOf($type));
            })
        );
    }

    private function schemaWithUnionOfType(mixed $type):Schema
    {
        $BadUnionType = new UnionType([
            'name' => 'BadUnion',
            'resolveType' => function () {
            },
            'types' => [$type],
        ]);
        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'f' => ['type' => $BadUnionType]
                ]
            ])
        ]);
    }

    private function schemaWithInterfaceFieldOfType(mixed $fieldType):Schema
    {
        $BadInterfaceType = new InterfaceType([
            'name' => 'BadInterface',
            'fields' => [
                'badField' => ['type' => $fieldType]
            ]
        ]);

        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'f' => ['type' => $BadInterfaceType]
                ]
            ]),
            // Have to add types implementing interfaces to bypass the "could not find implementers" exception
            'types' => [
                new ObjectType([
                    'name' => 'BadInterfaceImplementer',
                    'fields' => [
                        'badField' => ['type' => $fieldType]
                    ],
                    'interfaces' => [$BadInterfaceType],
                    'isTypeOf' => function() {}
                ]),
                $this->SomeObjectType
            ]
        ]);
    }

    private function schemaWithArgOfType(mixed $argType):Schema
    {
        $BadObjectType = new ObjectType([
            'name' => 'BadObject',
            'fields' => [
                'badField' => [
                    'type' => GraphQlType::string(),
                    'args' => [
                        'badArg' => ['type' => $argType]
                    ]
                ]
            ]
        ]);

        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'f' => ['type' => $BadObjectType]
                ]
            ])
        ]);
    }

    private function schemaWithInputFieldOfType(mixed $inputFieldType):Schema
    {
        $BadInputObjectType = new InputObjectType([
            'name' => 'BadInputObject',
            'fields' => [
                'badField' => ['type' => $inputFieldType]
            ]
        ]);

        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'f' => [
                        'type' => GraphQlType::string(),
                        'args' => [
                            'badArg' => ['type' => $BadInputObjectType]
                        ]
                    ]
                ]
            ])
        ]);
    }
}
