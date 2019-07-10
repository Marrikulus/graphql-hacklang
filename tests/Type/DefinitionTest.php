<?hh //strict
//decl
namespace GraphQL\Tests\Type;

require_once __DIR__ . '/TestClasses.php';

use GraphQL\Type\Definition\CustomScalarType;
use function Facebook\FBExpect\expect;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NoNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Utils\Utils;

class DefinitionTest extends \Facebook\HackTest\HackTest
{
    /**
     * @var ObjectType
     */
    public ?ObjectType $blogImage;

    /**
     * @var ObjectType
     */
    public ?ObjectType $blogArticle;

    /**
     * @var ObjectType
     */
    public ?ObjectType $blogAuthor;

    /**
     * @var ObjectType
     */
    public ?ObjectType $blogMutation;

    /**
     * @var ObjectType
     */
    public ?ObjectType $blogQuery;

    /**
     * @var ObjectType
     */
    public ?ObjectType $blogSubscription;

    /**
     * @var ObjectType
     */
    public ?ObjectType $objectType;

    /**
     * @var InterfaceType
     */
    public ?InterfaceType $interfaceType;

    /**
     * @var UnionType
     */
    public ?UnionType $unionType;

    /**
     * @var EnumType
     */
    public ?EnumType $enumType;

    /**
     * @var InputObjectType
     */
    public ?InputObjectType $inputObjectType;

    public async function beforeEachTestAsync(): Awaitable<void>
    {
        $this->objectType = new ObjectType([
            'name' => 'Object',
            'isTypeOf' => function() {return true;}
        ]);
        $this->interfaceType = new InterfaceType(['name' => 'Interface']);
        $this->unionType = new UnionType(['name' => 'Union', 'types' => [$this->objectType]]);
        $this->enumType = new EnumType(['name' => 'Enum']);
        $this->inputObjectType = new InputObjectType(['name' => 'InputObject']);

        $this->blogImage = new ObjectType([
            'name' => 'Image',
            'fields' => [
                'url' => ['type' => GraphQlType::string()],
                'width' => ['type' => GraphQlType::int()],
                'height' => ['type' => GraphQlType::int()]
            ]
        ]);

        $this->blogAuthor = new ObjectType([
            'name' => 'Author',
            'fields' => function() {
                return [
                    'id' => ['type' => GraphQlType::string()],
                    'name' => ['type' => GraphQlType::string()],
                    'pic' => [ 'type' => $this->blogImage, 'args' => [
                        'width' => ['type' => GraphQlType::int()],
                        'height' => ['type' => GraphQlType::int()]
                    ]],
                    'recentArticle' => $this->blogArticle,
                ];
            },
        ]);

        $this->blogArticle = new ObjectType([
            'name' => 'Article',
            'fields' => [
                'id' => ['type' => GraphQlType::string()],
                'isPublished' => ['type' => GraphQlType::boolean()],
                'author' => ['type' => $this->blogAuthor],
                'title' => ['type' => GraphQlType::string()],
                'body' => ['type' => GraphQlType::string()]
            ]
        ]);

        $this->blogQuery = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'article' => ['type' => $this->blogArticle, 'args' => [
                    'id' => ['type' => GraphQlType::string()]
                ]],
                'feed' => ['type' => new ListOfType($this->blogArticle)]
            ]
        ]);

        $this->blogMutation = new ObjectType([
            'name' => 'Mutation',
            'fields' => [
                'writeArticle' => ['type' => $this->blogArticle]
            ]
        ]);

        $this->blogSubscription = new ObjectType([
            'name' => 'Subscription',
            'fields' => [
                'articleSubscribe' => [
                    'args' => [ 'id' => [ 'type' => GraphQlType::string() ]],
                    'type' => $this->blogArticle
                ]
            ]
        ]);
    }

    // Type System: Example

    /**
     * @it defines a query only schema
     */
    public function testDefinesAQueryOnlySchema():void
    {
        $blogSchema = new Schema([
            'query' => $this->blogQuery
        ]);

        expect($this->blogQuery)->toBeSame($blogSchema->getQueryType());

        $articleField = $this->blogQuery?->getField('article');
        expect($this->blogArticle)->toBeSame($articleField->getType());
        expect('Article')->toBeSame($articleField?->getType()->name);
        expect('article')->toBeSame($articleField?->name);

        /** @var ObjectType $articleFieldType */
        $articleFieldType = $articleField->getType();
        $titleField = $articleFieldType->getField('title');

        expect($titleField)->toBeInstanceOf(\GraphQL\Type\Definition\FieldDefinition::class);
        expect($titleField->name)->toBeSame('title');
        expect($titleField->getType())->toBeSame(GraphQlType::string());

        $authorField = $articleFieldType->getField('author');
        expect($authorField)->toBeInstanceOf(\GraphQL\Type\Definition\FieldDefinition::class);

        /** @var ObjectType $authorFieldType */
        $authorFieldType = $authorField->getType();
        expect($authorFieldType)->toBeSame($this->blogAuthor);

        $recentArticleField = $authorFieldType->getField('recentArticle');
        expect($recentArticleField)->toBeInstanceOf(\GraphQL\Type\Definition\FieldDefinition::class);
        expect($recentArticleField->getType())->toBeSame($this->blogArticle);

        $feedField = $this->blogQuery->getField('feed');
        expect($feedField)->toBeInstanceOf(\GraphQL\Type\Definition\FieldDefinition::class);

        /** @var ListOfType $feedFieldType */
        $feedFieldType = $feedField->getType();
        expect($feedFieldType)->toBeInstanceOf(\GraphQL\Type\Definition\ListOfType::class);
        expect($feedFieldType->getWrappedType())->toBeSame($this->blogArticle);
    }

    /**
     * @it defines a mutation schema
     */
    public function testDefinesAMutationSchema():void
    {
        $schema = new Schema([
            'query' => $this->blogQuery,
            'mutation' => $this->blogMutation
        ]);

        expect($schema->getMutationType())->toBeSame($this->blogMutation);
        $writeMutation = $this->blogMutation->getField('writeArticle');

        expect($writeMutation)->toBeInstanceOf('GraphQL\Type\Definition\FieldDefinition');
        expect($writeMutation->getType())->toBeSame($this->blogArticle);
        expect($writeMutation->getType()->name)->toBeSame('Article');
        expect($writeMutation->name)->toBeSame('writeArticle');
    }

    /**
     * @it defines a subscription schema
     */
    public function testDefinesSubscriptionSchema():void
    {
        $schema = new Schema([
            'query' => $this->blogQuery,
            'subscription' => $this->blogSubscription
        ]);

        expect($schema->getSubscriptionType())->toBePHPEqual($this->blogSubscription);

        $sub = $this->blogSubscription->getField('articleSubscribe');
        expect($this->blogArticle)->toBePHPEqual($sub->getType());
        expect('Article')->toBePHPEqual($sub->getType()->name);
        expect('articleSubscribe')->toBePHPEqual($sub->name);
    }

    /**
     * @it defines an enum type with deprecated value
     */
    public function testDefinesEnumTypeWithDeprecatedValue():void
    {
        $enumTypeWithDeprecatedValue = new EnumType([
            'name' => 'EnumWithDeprecatedValue',
            'values' => [
                'foo' => ['deprecationReason' => 'Just because']
            ]
        ]);

        $value = $enumTypeWithDeprecatedValue->getValues()[0];

        expect((array) $value)->toInclude([
            'name' => 'foo',
            'description' => null,
            'deprecationReason' => 'Just because',
            'value' => 'foo',
            'astNode' => null
        ]);

        expect($value->isDeprecated())->toBePHPEqual(true);
    }

    /**
     * @it defines an enum type with a value of `null` and `undefined`
     */
    public function testDefinesAnEnumTypeWithAValueOfNullAndUndefined():void
    {
        $EnumTypeWithNullishValue = new EnumType([
            'name' => 'EnumWithNullishValue',
            'values' => [
                'NULL' => ['value' => null],
                'UNDEFINED' => ['value' => null],
            ]
        ]);

        $expected = [
            [
                'name' => 'NULL',
                'description' => null,
                'deprecationReason' => null,
                'value' => null,
                'astNode' => null,
            ],
            [
                'name' => 'UNDEFINED',
                'description' => null,
                'deprecationReason' => null,
                'value' => null,
                'astNode' => null,
            ],
        ];

        $actual = $EnumTypeWithNullishValue->getValues();

        expect(\count($actual))->toBePHPEqual(\count($expected));
        expect((array)$actual[0])->toInclude($expected[0]);
        expect((array)$actual[1])->toInclude($expected[1]);
    }

    /**
     * @it defines an object type with deprecated field
     */
    public function testDefinesAnObjectTypeWithDeprecatedField():void
    {
        $TypeWithDeprecatedField = new ObjectType([
          'name' => 'foo',
          'fields' => [
            'bar' => [
              'type' => GraphQlType::string(),
              'deprecationReason' => 'A terrible reason'
            ]
          ]
        ]);

        $field = $TypeWithDeprecatedField->getField('bar');

        expect($field->getType())->toBePHPEqual(GraphQlType::string());
        expect($field->isDeprecated())->toBePHPEqual(true);
        expect($field->deprecationReason)->toBePHPEqual('A terrible reason');
        expect($field->name)->toBePHPEqual('bar');
        expect($field->args)->toBePHPEqual([]);
    }

    /**
     * @it includes nested input objects in the map
     */
    public function testIncludesNestedInputObjectInTheMap():void
    {
        $nestedInputObject = new InputObjectType([
            'name' => 'NestedInputObject',
            'fields' => ['value' => ['type' => GraphQlType::string()]]
        ]);
        $someInputObject = new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => ['nested' => ['type' => $nestedInputObject]]
        ]);
        $someMutation = new ObjectType([
            'name' => 'SomeMutation',
            'fields' => [
                'mutateSomething' => [
                    'type' => $this->blogArticle,
                    'args' => ['input' => ['type' => $someInputObject]]
                ]
            ]
        ]);

        $schema = new Schema([
            'query' => $this->blogQuery,
            'mutation' => $someMutation
        ]);
        expect($schema->getType('NestedInputObject'))->toBeSame($nestedInputObject);
    }

    /**
     * @it includes interfaces\' subtypes in the type map
     */
    public function testIncludesInterfaceSubtypesInTheTypeMap():void
    {
        $someInterface = new InterfaceType([
            'name' => 'SomeInterface',
            'fields' => [
                'f' => ['type' => GraphQlType::int()]
            ]
        ]);

        $someSubtype = new ObjectType([
            'name' => 'SomeSubtype',
            'fields' => [
                'f' => ['type' => GraphQlType::int()]
            ],
            'interfaces' => [$someInterface],
            'isTypeOf' => function() {return true;}
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'iface' => ['type' => $someInterface]
                ]
            ]),
            'types' => [$someSubtype]
        ]);
        expect($schema->getType('SomeSubtype'))->toBeSame($someSubtype);
    }

    /**
     * @it includes interfaces\' thunk subtypes in the type map
     */
    public function testIncludesInterfacesThunkSubtypesInTheTypeMap():void
    {
        $someInterface = null;

        $someSubtype = new ObjectType([
            'name' => 'SomeSubtype',
            'fields' => [
                'f' => ['type' => GraphQlType::int()]
            ],
            'interfaces' => function() use (&$someInterface) { return [$someInterface]; },
            'isTypeOf' => function() {return true;}
        ]);

        $someInterface = new InterfaceType([
            'name' => 'SomeInterface',
            'fields' => [
                'f' => ['type' => GraphQlType::int()]
            ]
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'iface' => ['type' => $someInterface]
                ]
            ]),
            'types' => [$someSubtype]
        ]);

        expect($schema->getType('SomeSubtype'))->toBeSame($someSubtype);
    }

    /**
     * @it stringifies simple types
     */
    public function testStringifiesSimpleTypes():void
    {
        expect((string) GraphQlType::int())->toBeSame('Int');
        expect((string) $this->blogArticle)->toBeSame('Article');

        expect((string) $this->interfaceType)->toBeSame('Interface');
        expect((string) $this->unionType)->toBeSame('Union');
        expect((string) $this->enumType)->toBeSame('Enum');
        expect((string) $this->inputObjectType)->toBeSame('InputObject');
        expect((string) $this->objectType)->toBeSame('Object');

        expect((string) new NoNull(GraphQlType::int()))->toBeSame('Int!');
        expect((string) new ListOfType(GraphQlType::int()))->toBeSame('[Int]');
        expect((string) new NoNull(new ListOfType(GraphQlType::int())))->toBeSame('[Int]!');
        expect((string) new ListOfType(new NoNull(GraphQlType::int())))->toBeSame('[Int!]');
        expect((string) new ListOfType(new ListOfType(GraphQlType::int())))->toBeSame('[[Int]]');
    }

    /**
     * @it identifies input types
     */
    public function testIdentifiesInputTypes():void
    {
        $expected = [
            [GraphQlType::int(), true],
            [$this->objectType, false],
            [$this->interfaceType, false],
            [$this->unionType, false],
            [$this->enumType, true],
            [$this->inputObjectType, true]
        ];

        foreach ($expected as $index => $entry) {
            expect(GraphQlType::isInputType($entry[0]))->toBeSame($entry[1], "Type {$entry[0]} was detected incorrectly");
        }
    }

    /**
     * @it identifies output types
     */
    public function testIdentifiesOutputTypes():void
    {
        $expected = [
            [GraphQlType::int(), true],
            [$this->objectType, true],
            [$this->interfaceType, true],
            [$this->unionType, true],
            [$this->enumType, true],
            [$this->inputObjectType, false]
        ];

        foreach ($expected as $index => $entry) {
            expect(GraphQlType::isOutputType($entry[0]))->toBeSame($entry[1], "Type {$entry[0]} was detected incorrectly");
        }
    }

    /**
     * @it prohibits nesting NonNull inside NonNull
     */
    public function testProhibitsNonNullNesting():void
    {
        $this->setExpectedException('\Exception');
        new NoNull(new NoNull(GraphQlType::int()));
    }

    /**
     * @it prohibits putting non-Object types in unions
     */
    public function testProhibitsPuttingNonObjectTypesInUnions():void
    {
        $int = GraphQlType::int();

        $badUnionTypes = [
            $int,
            new NoNull($int),
            new ListOfType($int),
            $this->interfaceType,
            $this->unionType,
            $this->enumType,
            $this->inputObjectType
        ];

        foreach ($badUnionTypes as $type) {
            try {
                $union = new UnionType(['name' => 'BadUnion', 'types' => [$type]]);
                $union->assertValid();
                self::fail('Expected exception not thrown');
            } catch (\Exception $e) {
                expect($e->getMessage())
                    ->toBeSame('BadUnion may only contain Object types, it cannot contain: ' . Utils::printSafe($type) . '.');
            }
        }
    }

    /**
     * @it allows a thunk for Union\'s types
     */
    public function testAllowsThunkForUnionTypes():void
    {
        $union = new UnionType([
            'name' => 'ThunkUnion',
            'types' => function() {return [$this->objectType]; }
        ]);

        $types = $union->getTypes();
        expect(\count($types))->toBePHPEqual(1);
        expect($types[0])->toBeSame($this->objectType);
    }

    public function testAllowsRecursiveDefinitions():void
    {
        // See https://github.com/webonyx/graphql-php/issues/16
        $node = new InterfaceType([
            'name' => 'Node',
            'fields' => [
                'id' => ['type' => GraphQlType::nonNull(GraphQlType::id())]
            ]
        ]);

        $blog = null;
        $called = false;

        $user = new ObjectType([
            'name' => 'User',
            /* HH_FIXME[2087]*/
            'fields' => function() use (&$blog, &$called) {
                expect($blog)->toNotBeNull('Blog type is expected to be defined at this point, but it is null');
                $called = true;

                return [
                    'id' => ['type' => GraphQlType::nonNull(GraphQlType::id())],
                    'blogs' => ['type' => GraphQlType::nonNull(GraphQlType::listOf(GraphQlType::nonNull($blog)))]
                ];
            },
            'interfaces' => function() use ($node) {
                return [$node];
            }
        ]);

        $blog = new ObjectType([
            'name' => 'Blog',
            'fields' => function() use ($user) {
                return [
                    'id' => ['type' => GraphQlType::nonNull(GraphQlType::id())],
                    'owner' => ['type' => GraphQlType::nonNull($user)]
                ];
            },
            'interfaces' => function() use ($node) {
                return [$node];
            }
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'node' => ['type' => $node]
                ]
            ]),
            'types' => [$user, $blog]
        ]);

        expect($called)->toBeTrue();
        $schema->getType('Blog');

        expect($blog->getInterfaces())->toBePHPEqual([$node]);
        expect($user->getInterfaces())->toBePHPEqual([$node]);

        expect($user->getField('blogs'))->toNotBeNull();
        expect($user->getField('blogs')->getType()->getWrappedType(true))->toBeSame($blog);

        expect($blog->getField('owner'))->toNotBeNull();
        expect($blog->getField('owner')->getType()->getWrappedType(true))->toBeSame($user);
    }

    public function testInputObjectTypeAllowsRecursiveDefinitions():void
    {
        $called = false;
        $inputObject = new InputObjectType([
            'name' => 'InputObject',
            /* HH_FIXME[2087]*/
            'fields' => function() use (&$inputObject, &$called) {
                $called = true;
                return [
                    'value' => ['type' => GraphQlType::string()],
                    'nested' => ['type' => $inputObject ]
                ];
            }
        ]);
        $someMutation = new ObjectType([
            'name' => 'SomeMutation',
            'fields' => [
                'mutateSomething' => [
                    'type' => $this->blogArticle,
                    'args' => ['input' => ['type' => $inputObject]]
                ]
            ]
        ]);

        $schema = new Schema([
            'query' => $this->blogQuery,
            'mutation' => $someMutation
        ]);

        expect($schema->getType('InputObject'))->toBeSame($inputObject);
        expect($called)->toBeTrue();
        expect(2)->toBePHPEqual(\count($inputObject->getFields()));
        expect($inputObject)->toBeSame($inputObject->getField('nested')->getType());
        expect($inputObject)->toBeSame($someMutation->getField('mutateSomething')->getArg('input')->getType());
    }

    public function testInterfaceTypeAllowsRecursiveDefinitions():void
    {
        $called = false;
        $interface = new InterfaceType([
            'name' => 'SomeInterface',
            /* HH_FIXME[2087]*/
            'fields' => function() use (&$interface, &$called) {
                $called = true;
                return [
                    'value' => ['type' => GraphQlType::string()],
                    'nested' => ['type' => $interface ]
                ];
            }
        ]);

        $query = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'test' => ['type' => $interface]
            ]
        ]);

        $schema = new Schema([
            'query' => $query
        ]);

        expect($schema->getType('SomeInterface'))->toBeSame($interface);
        expect($called)->toBeTrue();
        expect(2)->toBePHPEqual(\count($interface->getFields()));
        expect($interface)->toBeSame($interface->getField('nested')->getType());
        expect(GraphQlType::string())->toBeSame($interface->getField('value')->getType());
    }

    public function testAllowsShorthandFieldDefinition():void
    {
        $interface = new InterfaceType([
            'name' => 'SomeInterface',
            /* HH_FIXME[2087]*/
            'fields' => function() use (&$interface) {
                return [
                    'value' => GraphQlType::string(),
                    'nested' => $interface,
                    'withArg' => [
                        'type' => GraphQlType::string(),
                        'args' => [
                            'arg1' => GraphQlType::int()
                        ]
                    ]
                ];
            }
        ]);

        $query = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'test' => $interface
            ]
        ]);

        $schema = new Schema([
            'query' => $query
        ]);

        $valueField = $schema->getType('SomeInterface')->getField('value');
        $nestedField = $schema->getType('SomeInterface')->getField('nested');

        expect($valueField->getType())->toBePHPEqual(GraphQlType::string());
        expect($nestedField->getType())->toBePHPEqual($interface);

        $withArg = $schema->getType('SomeInterface')->getField('withArg');
        expect($withArg->getType())->toBePHPEqual(GraphQlType::string());

        expect($withArg->args[0]->name)->toBePHPEqual('arg1');
        expect($withArg->args[0]->getType())->toBePHPEqual(GraphQlType::int());

        $testField = $schema->getType('Query')->getField('test');
        expect($testField->getType())->toBePHPEqual($interface);
        expect($testField->name)->toBePHPEqual('test');
    }

    public function testInfersNameFromClassname():void
    {
        $myObj = new MyCustomType();
        expect($myObj->name)->toBePHPEqual('MyCustom');

        $otherCustom = new OtherCustom();
        expect($otherCustom->name)->toBePHPEqual('OtherCustom');
    }

    public function testAllowsOverridingInternalTypes():void
    {
        $idType = new CustomScalarType([
            'name' => 'ID',
            'serialize' => function() {},
            'parseValue' => function() {},
            'parseLiteral' => function() {}
        ]);

        $schema = new Schema([
            'query' => new ObjectType(['name' => 'Query', 'fields' => []]),
            'types' => [$idType]
        ]);

        expect($schema->getType('ID'))->toBeSame($idType);
    }
}
