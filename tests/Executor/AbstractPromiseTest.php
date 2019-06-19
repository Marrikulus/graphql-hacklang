<?hh //strict
//decl
namespace GraphQL\Tests\Executor;

use GraphQL\Deferred;
use GraphQL\Error\UserError;
use GraphQL\Error\Warning;
use GraphQL\GraphQL;
use GraphQL\Schema;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;
use GraphQL\Type\Definition\UnionType;

require_once __DIR__ . '/TestClasses.php';

class AbstractPromiseTest extends \PHPUnit_Framework_TestCase
{
    // DESCRIBE: Execute: Handles execution of abstract types with promises

    /**
     * @it isTypeOf used to resolve runtime type for Interface
     */
    public function testIsTypeOfUsedToResolveRuntimeTypeForInterface():void
    {
        $PetType = new InterfaceType([
            'name' => 'Pet',
            'fields' => [
                'name' => [ 'type' => GraphQlType::string() ]
            ]
        ]);

        $DogType = new ObjectType([
            'name' => 'Dog',
            'interfaces' => [ $PetType ],
            'isTypeOf' => function($obj) {
                return new Deferred(function() use ($obj) {
                    return $obj instanceof Dog;
                });
            },
            'fields' => [
                'name' => [ 'type' => GraphQlType::string() ],
                'woofs' => [ 'type' => GraphQlType::boolean() ],
            ]
        ]);

        $CatType = new ObjectType([
            'name' => 'Cat',
            'interfaces' => [ $PetType ],
            'isTypeOf' => function($obj) {
                return new Deferred(function() use ($obj) {
                    return $obj instanceof Cat;
                });
            },
            'fields' => [
                'name' => [ 'type' => GraphQlType::string() ],
                'meows' => [ 'type' => GraphQlType::boolean() ],
            ]
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => GraphQlType::listOf($PetType),
                        'resolve' => function() {
                            return [
                                new Dog('Odie', true),
                                new Cat('Garfield', false)
                            ];
                        }
                    ]
                ]
            ]),
            'types' => [ $CatType, $DogType ]
        ]);

        $query = '{
          pets {
            name
            ... on Dog {
              woofs
            }
            ... on Cat {
              meows
            }
          }
        }';

        Warning::suppress(Warning::WARNING_FULL_SCHEMA_SCAN);
        $result = GraphQL::execute($schema, $query);
        Warning::enable(Warning::WARNING_FULL_SCHEMA_SCAN);

        $expected = [
            'data' => [
                'pets' => [
                    [ 'name' => 'Odie', 'woofs' => true ],
                    [ 'name' => 'Garfield', 'meows' => false ]
                ]
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * @it isTypeOf can be rejected
     */
    public function testIsTypeOfCanBeRejected():void
    {

        $PetType = new InterfaceType([
            'name' => 'Pet',
            'fields' => [
                'name' => ['type' => GraphQlType::string()]
            ]
        ]);

        $DogType = new ObjectType([
            'name' => 'Dog',
            'interfaces' => [$PetType],
            'isTypeOf' => function () {
                return new Deferred(function () {
                    throw new UserError('We are testing this error');
                });
            },
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
                'woofs' => ['type' => GraphQlType::boolean()],
            ]
        ]);

        $CatType = new ObjectType([
            'name' => 'Cat',
            'interfaces' => [$PetType],
            'isTypeOf' => function ($obj) {
                return new Deferred(function () use ($obj) {
                    return $obj instanceof Cat;
                });
            },
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
                'meows' => ['type' => GraphQlType::boolean()],
            ]
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => GraphQlType::listOf($PetType),
                        'resolve' => function () {
                            return [
                                new Dog('Odie', true),
                                new Cat('Garfield', false)
                            ];
                        }
                    ]
                ]
            ]),
            'types' => [$CatType, $DogType]
        ]);

        $query = '{
      pets {
        name
        ... on Dog {
          woofs
        }
        ... on Cat {
          meows
        }
      }
    }';

        Warning::suppress(Warning::WARNING_FULL_SCHEMA_SCAN);
        $result = GraphQL::execute($schema, $query);
        Warning::enable(Warning::WARNING_FULL_SCHEMA_SCAN);

        $expected = [
            'data' => [
                'pets' => [null, null]
            ],
            'errors' => [
                [
                    'message' => 'We are testing this error',
                    'locations' => [['line' => 2, 'column' => 7]],
                    'path' => ['pets', 0],
                ],
                [
                    'message' => 'We are testing this error',
                    'locations' => [['line' => 2, 'column' => 7]],
                    'path' => ['pets', 1]
                ]
            ]
        ];

        $this->assertArraySubset($expected, $result);
    }

    /**
     * @it isTypeOf used to resolve runtime type for Union
     */
    public function testIsTypeOfUsedToResolveRuntimeTypeForUnion():void
    {

        $DogType = new ObjectType([
            'name' => 'Dog',
            'isTypeOf' => function ($obj) {
                return new Deferred(function () use ($obj) {
                    return $obj instanceof Dog;
                });
            },
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
                'woofs' => ['type' => GraphQlType::boolean()],
            ]
        ]);

        $CatType = new ObjectType([
            'name' => 'Cat',
            'isTypeOf' => function ($obj) {
                return new Deferred(function () use ($obj) {
                    return $obj instanceof Cat;
                });
            },
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
                'meows' => ['type' => GraphQlType::boolean()],
            ]
        ]);

        $PetType = new UnionType([
            'name' => 'Pet',
            'types' => [$DogType, $CatType]
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => GraphQlType::listOf($PetType),
                        'resolve' => function () {
                            return [new Dog('Odie', true), new Cat('Garfield', false)];
                        }
                    ]
                ]
            ])
        ]);

        $query = '{
      pets {
        ... on Dog {
          name
          woofs
        }
        ... on Cat {
          name
          meows
        }
      }
    }';

        $result = GraphQL::execute($schema, $query);

        $expected = [
            'data' => [
                'pets' => [
                    ['name' => 'Odie', 'woofs' => true],
                    ['name' => 'Garfield', 'meows' => false]
                ]
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * @it resolveType on Interface yields useful error
     */
    public function testResolveTypeOnInterfaceYieldsUsefulError():void
    {
        $PetType = new InterfaceType([
            'name' => 'Pet',
            'resolveType' => function ($obj) use (&$DogType, &$CatType, &$HumanType) {
                return new Deferred(function () use ($obj, $DogType, $CatType, $HumanType) {
                    if ($obj instanceof Dog) {
                        return $DogType;
                    }
                    if ($obj instanceof Cat) {
                        return $CatType;
                    }
                    if ($obj instanceof Human) {
                        return $HumanType;
                    }
                    return null;
                });
            },
            'fields' => [
                'name' => ['type' => GraphQlType::string()]
            ]
        ]);

        $HumanType = new ObjectType([
            'name' => 'Human',
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
            ]
        ]);

        $DogType = new ObjectType([
            'name' => 'Dog',
            'interfaces' => [$PetType],
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
                'woofs' => ['type' => GraphQlType::boolean()],
            ]
        ]);

        $CatType = new ObjectType([
            'name' => 'Cat',
            'interfaces' => [$PetType],
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
                'meows' => ['type' => GraphQlType::boolean()],
            ]
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => GraphQlType::listOf($PetType),
                        'resolve' => function () {
                            return new Deferred(function () {
                                return [
                                    new Dog('Odie', true),
                                    new Cat('Garfield', false),
                                    new Human('Jon')
                                ];
                            });
                        }
                    ]
                ]
            ]),
            'types' => [$CatType, $DogType]
        ]);

        $query = '{
      pets {
        name
        ... on Dog {
          woofs
        }
        ... on Cat {
          meows
        }
      }
    }';

        $result = GraphQL::executeAndReturnResult($schema, $query)->toArray(true);

        $expected = [
            'data' => [
                'pets' => [
                    ['name' => 'Odie', 'woofs' => true],
                    ['name' => 'Garfield', 'meows' => false],
                    null
                ]
            ],
            'errors' => [
                [
                    'debugMessage' => 'Runtime Object type "Human" is not a possible type for "Pet".',
                    'locations' => [['line' => 2, 'column' => 7]],
                    'path' => ['pets', 2]
                ],
            ]
        ];

        $this->assertArraySubset($expected, $result);
    }

    /**
     * @it resolveType on Union yields useful error
     */
    public function testResolveTypeOnUnionYieldsUsefulError():void
    {

        $HumanType = new ObjectType([
            'name' => 'Human',
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
            ]
        ]);

        $DogType = new ObjectType([
            'name' => 'Dog',
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
                'woofs' => ['type' => GraphQlType::boolean()],
            ]
        ]);

        $CatType = new ObjectType([
            'name' => 'Cat',
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
                'meows' => ['type' => GraphQlType::boolean()],
            ]
        ]);

        $PetType = new UnionType([
            'name' => 'Pet',
            'resolveType' => function ($obj) use ($DogType, $CatType, $HumanType) {
                return new Deferred(function () use ($obj, $DogType, $CatType, $HumanType) {
                    if ($obj instanceof Dog) {
                        return $DogType;
                    }
                    if ($obj instanceof Cat) {
                        return $CatType;
                    }
                    if ($obj instanceof Human) {
                        return $HumanType;
                    }
                    return null;
                });
            },
            'types' => [$DogType, $CatType]
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => GraphQlType::listOf($PetType),
                        'resolve' => function () {
                            return [
                                new Dog('Odie', true),
                                new Cat('Garfield', false),
                                new Human('Jon')
                            ];
                        }
                    ]
                ]
            ])
        ]);

        $query = '{
      pets {
        ... on Dog {
          name
          woofs
        }
        ... on Cat {
          name
          meows
        }
      }
    }';

        $result = GraphQL::executeAndReturnResult($schema, $query)->toArray(true);

        $expected = [
            'data' => [
                'pets' => [
                    ['name' => 'Odie', 'woofs' => true],
                    ['name' => 'Garfield', 'meows' => false],
                    null
                ]
            ],
            'errors' => [
                [
                    'debugMessage' => 'Runtime Object type "Human" is not a possible type for "Pet".',
                    'locations' => [['line' => 2, 'column' => 7]],
                    'path' => ['pets', 2]
                ]
            ]
        ];

        $this->assertArraySubset($expected, $result);
    }

    /**
     * @it resolveType allows resolving with type name
     */
    public function testResolveTypeAllowsResolvingWithTypeName():void
    {
        $PetType = new InterfaceType([
            'name' => 'Pet',
            'resolveType' => function ($obj) {
                return new Deferred(function () use ($obj) {
                    if ($obj instanceof Dog) {
                        return 'Dog';
                    }
                    if ($obj instanceof Cat) {
                        return 'Cat';
                    }
                    return null;
                });
            },
            'fields' => [
                'name' => ['type' => GraphQlType::string()]
            ]
        ]);


        $DogType = new ObjectType([
            'name' => 'Dog',
            'interfaces' => [$PetType],
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
                'woofs' => ['type' => GraphQlType::boolean()],
            ]
        ]);

        $CatType = new ObjectType([
            'name' => 'Cat',
            'interfaces' => [$PetType],
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
                'meows' => ['type' => GraphQlType::boolean()],
            ]
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => GraphQlType::listOf($PetType),
                        'resolve' => function () {
                            return [
                                new Dog('Odie', true),
                                new Cat('Garfield', false)
                            ];
                        }
                    ]
                ]
            ]),
            'types' => [$CatType, $DogType]
        ]);

        $query = '{
      pets {
        name
        ... on Dog {
          woofs
        }
        ... on Cat {
          meows
        }
      }
    }';

        $result = GraphQL::execute($schema, $query);

        $expected = [
            'data' => [
                'pets' => [
                    ['name' => 'Odie', 'woofs' => true],
                    ['name' => 'Garfield', 'meows' => false],
                ]
            ]
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * @it resolveType can be caught
     */
    public function testResolveTypeCanBeCaught():void
    {

        $PetType = new InterfaceType([
            'name' => 'Pet',
            'resolveType' => function () {
                return new Deferred(function () {
                    throw new UserError('We are testing this error');
                });
            },
            'fields' => [
                'name' => ['type' => GraphQlType::string()]
            ]
        ]);

        $DogType = new ObjectType([
            'name' => 'Dog',
            'interfaces' => [$PetType],
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
                'woofs' => ['type' => GraphQlType::boolean()],
            ]
        ]);

        $CatType = new ObjectType([
            'name' => 'Cat',
            'interfaces' => [$PetType],
            'fields' => [
                'name' => ['type' => GraphQlType::string()],
                'meows' => ['type' => GraphQlType::boolean()],
            ]
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => GraphQlType::listOf($PetType),
                        'resolve' => function () {
                            return [
                                new Dog('Odie', true),
                                new Cat('Garfield', false)
                            ];
                        }
                    ]
                ]
            ]),
            'types' => [$CatType, $DogType]
        ]);

        $query = '{
      pets {
        name
        ... on Dog {
          woofs
        }
        ... on Cat {
          meows
        }
      }
    }';

        $result = GraphQL::execute($schema, $query);

        $expected = [
            'data' => [
                'pets' => [null, null]
            ],
            'errors' => [
                [
                    'message' => 'We are testing this error',
                    'locations' => [['line' => 2, 'column' => 7]],
                    'path' => ['pets', 0]
                ],
                [
                    'message' => 'We are testing this error',
                    'locations' => [['line' => 2, 'column' => 7]],
                    'path' => ['pets', 1]
                ]
            ]
        ];

        $this->assertArraySubset($expected, $result);
    }
}
