<?hh //strict
//decl
namespace GraphQL\Tests\Executor;

require_once __DIR__ . '/TestClasses.php';

use GraphQL\Error\Warning;
use function Facebook\FBExpect\expect;
use GraphQL\Executor\Executor;
use GraphQL\GraphQL;
use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\GraphQlType;
use GraphQL\Type\Definition\UnionType;

class UnionInterfaceTest extends \Facebook\HackTest\HackTest
{
    public $schema;
    public $garfield;
    public $odie;
    public $liz;
    public $john;

    public async function beforeEachTestAsync(): Awaitable<void>
    {
        $NamedType = new InterfaceType([
            'name' => 'Named',
            'fields' => [
                'name' => ['type' => GraphQlType::string()]
            ]
        ]);

        $DogType = new ObjectType([
            'name' => 'Dog',
            'interfaces' => [$NamedType],
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
                'woofs' => ['type' => GraphQlType::boolean()]
            ],
            'isTypeOf' => function ($value) {
                return $value instanceof Dog;
            }
        ]);

        $CatType = new ObjectType([
            'name' => 'Cat',
            'interfaces' => [$NamedType],
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
                'meows' => ['type' => GraphQlType::boolean()]
            ],
            'isTypeOf' => function ($value) {
                return $value instanceof Cat;
            }
        ]);

        $PetType = new UnionType([
            'name' => 'Pet',
            'types' => [$DogType, $CatType],
            'resolveType' => function ($value) use ($DogType, $CatType) {
                if ($value instanceof Dog) {
                    return $DogType;
                }
                if ($value instanceof Cat) {
                    return $CatType;
                }
            }
        ]);

        $PersonType = new ObjectType([
            'name' => 'Person',
            'interfaces' => [$NamedType],
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
                'pets' => ['type' => GraphQlType::listOf($PetType)],
                'friends' => ['type' => GraphQlType::listOf($NamedType)]
            ],
            'isTypeOf' => function ($value) {
                return $value instanceof Person;
            }
        ]);

        $this->schema = new Schema([
            'query' => $PersonType,
            'types' => [ $PetType ]
        ]);

        $this->garfield = new Cat('Garfield', false);
        $this->odie = new Dog('Odie', true);
        $this->liz = new Person('Liz');
        $this->john = new Person('John', [$this->garfield, $this->odie], [$this->liz, $this->odie]);

    }

    // Execute: Union and intersection types

    /**
     * @it can introspect on union and intersection types
     */
    public function testCanIntrospectOnUnionAndIntersectionTypes():void
    {

        $ast = Parser::parse('
      {
        Named: __type(name: "Named") {
          kind
          name
          fields { name }
          interfaces { name }
          possibleTypes { name }
          enumValues { name }
          inputFields { name }
        }
        Pet: __type(name: "Pet") {
          kind
          name
          fields { name }
          interfaces { name }
          possibleTypes { name }
          enumValues { name }
          inputFields { name }
        }
      }
    ');

        $expected = [
            'data' => [
                'Named' => [
                    'kind' => 'INTERFACE',
                    'name' => 'Named',
                    'fields' => [
                        ['name' => 'name']
                    ],
                    'interfaces' => null,
                    'possibleTypes' => [
                        ['name' => 'Person'],
                        ['name' => 'Dog'],
                        ['name' => 'Cat']
                    ],
                    'enumValues' => null,
                    'inputFields' => null
                ],
                'Pet' => [
                    'kind' => 'UNION',
                    'name' => 'Pet',
                    'fields' => null,
                    'interfaces' => null,
                    'possibleTypes' => [
                        ['name' => 'Dog'],
                        ['name' => 'Cat']
                    ],
                    'enumValues' => null,
                    'inputFields' => null
                ]
            ]
        ];
        expect(Executor::execute($this->schema, $ast)->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it executes using union types
     */
    public function testExecutesUsingUnionTypes():void
    {
        // NOTE: This is an *invalid* query, but it should be an *executable* query.
        $ast = Parser::parse('
      {
        __typename
        name
        pets {
          __typename
          name
          woofs
          meows
        }
      }
        ');
        $expected = [
            'data' => [
                '__typename' => 'Person',
                'name' => 'John',
                'pets' => [
                    ['__typename' => 'Cat', 'name' => 'Garfield', 'meows' => false],
                    ['__typename' => 'Dog', 'name' => 'Odie', 'woofs' => true]
                ]
            ]
        ];

        expect(Executor::execute($this->schema, $ast, $this->john)->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it executes union types with inline fragments
     */
    public function testExecutesUnionTypesWithInlineFragments():void
    {
        // This is the valid version of the query in the above test.
        $ast = Parser::parse('
      {
        __typename
        name
        pets {
          __typename
          ... on Dog {
            name
            woofs
          }
          ... on Cat {
            name
            meows
          }
        }
      }
        ');
        $expected = [
            'data' => [
                '__typename' => 'Person',
                'name' => 'John',
                'pets' => [
                    ['__typename' => 'Cat', 'name' => 'Garfield', 'meows' => false],
                    ['__typename' => 'Dog', 'name' => 'Odie', 'woofs' => true]
                ]

            ]
        ];
        expect(Executor::execute($this->schema, $ast, $this->john)->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it executes using interface types
     */
    public function testExecutesUsingInterfaceTypes():void
    {
        // NOTE: This is an *invalid* query, but it should be an *executable* query.
        $ast = Parser::parse('
      {
        __typename
        name
        friends {
          __typename
          name
          woofs
          meows
        }
      }
        ');
        $expected = [
            'data' => [
                '__typename' => 'Person',
                'name' => 'John',
                'friends' => [
                    ['__typename' => 'Person', 'name' => 'Liz'],
                    ['__typename' => 'Dog', 'name' => 'Odie', 'woofs' => true]
                ]
            ]
        ];

        Warning::suppress(Warning::WARNING_FULL_SCHEMA_SCAN);
        expect(Executor::execute($this->schema, $ast, $this->john)->toArray())->toBePHPEqual($expected);
        Warning::enable(Warning::WARNING_FULL_SCHEMA_SCAN);
    }

    /**
     * @it executes interface types with inline fragments
     */
    public function testExecutesInterfaceTypesWithInlineFragments():void
    {
        // This is the valid version of the query in the above test.
        $ast = Parser::parse('
      {
        __typename
        name
        friends {
          __typename
          name
          ... on Dog {
            woofs
          }
          ... on Cat {
            meows
          }
        }
      }
        ');
        $expected = [
            'data' => [
                '__typename' => 'Person',
                'name' => 'John',
                'friends' => [
                    ['__typename' => 'Person', 'name' => 'Liz'],
                    ['__typename' => 'Dog', 'name' => 'Odie', 'woofs' => true]
                ]
            ]
        ];

        Warning::suppress(Warning::WARNING_FULL_SCHEMA_SCAN);
        expect(Executor::execute($this->schema, $ast, $this->john)->toArray())->toBePHPEqual($expected);
        Warning::enable(Warning::WARNING_FULL_SCHEMA_SCAN);
    }

    /**
     * @it allows fragment conditions to be abstract types
     */
    public function testAllowsFragmentConditionsToBeAbstractTypes():void
    {
        $ast = Parser::parse('
      {
        __typename
        name
        pets { ...PetFields }
        friends { ...FriendFields }
      }

      fragment PetFields on Pet {
        __typename
        ... on Dog {
          name
          woofs
        }
        ... on Cat {
          name
          meows
        }
      }

      fragment FriendFields on Named {
        __typename
        name
        ... on Dog {
          woofs
        }
        ... on Cat {
          meows
        }
      }
    ');

        $expected = [
            'data' => [
                '__typename' => 'Person',
                'name' => 'John',
                'pets' => [
                    ['__typename' => 'Cat', 'name' => 'Garfield', 'meows' => false],
                    ['__typename' => 'Dog', 'name' => 'Odie', 'woofs' => true]
                ],
                'friends' => [
                    ['__typename' => 'Person', 'name' => 'Liz'],
                    ['__typename' => 'Dog', 'name' => 'Odie', 'woofs' => true]
                ]
            ]
        ];

        Warning::suppress(Warning::WARNING_FULL_SCHEMA_SCAN);
        expect(Executor::execute($this->schema, $ast, $this->john)->toArray())->toBePHPEqual($expected);
        Warning::enable(Warning::WARNING_FULL_SCHEMA_SCAN);
    }

    /**
     * @it gets execution info in resolver
     */
    public function testGetsExecutionInfoInResolver():void
    {
        $encounteredContext = null;
        $encounteredSchema = null;
        $encounteredRootValue = null;
        $PersonType2 = null;

        $NamedType2 = new InterfaceType([
            'name' => 'Named',
            'fields' => [
                'name' => ['type' => GraphQlType::string()]
            ],
            'resolveType' => function ($obj, $context, ResolveInfo $info) use (&$encounteredContext, &$encounteredSchema, &$encounteredRootValue, &$PersonType2) {
                $encounteredContext = $context;
                $encounteredSchema = $info->schema;
                $encounteredRootValue = $info->rootValue;
                return $PersonType2;
            }
        ]);

        $PersonType2 = new ObjectType([
            'name' => 'Person',
            'interfaces' => [$NamedType2],
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
                'friends' => ['type' => GraphQlType::listOf($NamedType2)],
            ],
        ]);

        $schema2 = new Schema([
            'query' => $PersonType2
        ]);

        $john2 = new Person('John', [], [$this->liz]);

        $context = ['authToken' => '123abc'];

        $ast = Parser::parse('{ name, friends { name } }');

        expect(GraphQL::execute($schema2, $ast, $john2, $context))
            ->toBePHPEqual(['data' => ['name' => 'John', 'friends' => [['name' => 'Liz']]]]);
        expect($encounteredContext)->toBeSame($context);
        expect($encounteredSchema)->toBeSame($schema2);
        expect($encounteredRootValue)->toBeSame($john2);
    }
}
