<?hh //strict
//decl
namespace GraphQL\Tests\Executor;

require_once __DIR__ . '/TestClasses.php';

use GraphQL\Deferred;
use function Facebook\FBExpect\expect;
use GraphQL\Error\Error;
use GraphQL\Error\UserError;
use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Schema;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\GraphQlType;

class ExecutorTest extends \Facebook\HackTest\HackTest
{
    public async function afterEachTestAsync(): Awaitable<void>
    {
        Executor::setPromiseAdapter(null);
    }

    // Execute: Handles basic execution tasks

    /**
     * @it executes arbitrary code
     */
    public function testExecutesArbitraryCode():void
    {
        $deepData = null;
        $data = null;

        $promiseData = function () use (&$data) {
            return new Deferred(function () use (&$data) {
                return $data;
            });
        };

        $data = [
            'a' => function () { return 'Apple';},
            'b' => function () {return 'Banana';},
            'c' => function () {return 'Cookie';},
            'd' => function () {return 'Donut';},
            'e' => function () {return 'Egg';},
            'f' => 'Fish',
            'pic' => function ($size = 50) {
                return 'Pic of size: ' . $size;
            },
            'promise' => function() use ($promiseData) {
                return $promiseData();
            },
            'deep' => function () use (&$deepData) {
                return $deepData;
            }
        ];

        $deepData = [
            'a' => function () { return 'Already Been Done'; },
            'b' => function () { return 'Boring'; },
            'c' => function () {
                return ['Contrived', null, 'Confusing'];
            },
            'deeper' => function () use (&$data) {
                return [$data, null, $data];
            }
        ];


        $doc = '
      query Example($size: Int) {
        a,
        b,
        x: c
        ...c
        f
        ...on DataType {
          pic(size: $size)
          promise {
            a
          }
        }
        deep {
          a
          b
          c
          deeper {
            a
            b
          }
        }
      }

      fragment c on DataType {
        d
        e
      }
    ';

        $ast = Parser::parse($doc);
        $expected = [
            'data' => [
                'a' => 'Apple',
                'b' => 'Banana',
                'x' => 'Cookie',
                'd' => 'Donut',
                'e' => 'Egg',
                'f' => 'Fish',
                'pic' => 'Pic of size: 100',
                'promise' => [
                    'a' => 'Apple'
                ],
                'deep' => [
                    'a' => 'Already Been Done',
                    'b' => 'Boring',
                    'c' => [ 'Contrived', null, 'Confusing' ],
                    'deeper' => [
                        [ 'a' => 'Apple', 'b' => 'Banana' ],
                        null,
                        [ 'a' => 'Apple', 'b' => 'Banana' ]
                    ]
                ]
            ]
        ];

        $deepDataType = null;
        $dataType = new ObjectType([
            'name' => 'DataType',
            'fields' => function() use (&$dataType, &$deepDataType) {
                return [
                    'a' => [ 'type' => GraphQlType::string() ],
                    'b' => [ 'type' => GraphQlType::string() ],
                    'c' => [ 'type' => GraphQlType::string() ],
                    'd' => [ 'type' => GraphQlType::string() ],
                    'e' => [ 'type' => GraphQlType::string() ],
                    'f' => [ 'type' => GraphQlType::string() ],
                    'pic' => [
                        'args' => [ 'size' => ['type' => GraphQlType::int() ] ],
                        'type' => GraphQlType::string(),
                        'resolve' => function($obj, $args) {
                            return $obj['pic']($args['size']);
                        }
                    ],
                    'promise' => ['type' => $dataType],
                    'deep' => ['type' => $deepDataType],
                ];
            }
        ]);

        $deepDataType = new ObjectType([
            'name' => 'DeepDataType',
            'fields' => [
                'a' => [ 'type' => GraphQlType::string() ],
                'b' => [ 'type' => GraphQlType::string() ],
                'c' => [ 'type' => GraphQlType::listOf(GraphQlType::string()) ],
                'deeper' => [ 'type' => GraphQlType::listOf($dataType) ]
            ]
        ]);
        $schema = new Schema(['query' => $dataType]);

        expect(Executor::execute($schema, $ast, $data, null, ['size' => 100], 'Example')->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it merges parallel fragments
     */
    public function testMergesParallelFragments():void
    {
        $ast = Parser::parse('
      { a, ...FragOne, ...FragTwo }

      fragment FragOne on Type {
        b
        deep { b, deeper: deep { b } }
      }

      fragment FragTwo on Type {
        c
        deep { c, deeper: deep { c } }
      }
        ');

        $Type = new ObjectType([
            'name' => 'Type',
            'fields' => function() use (&$Type) {
                return [
                    'a' => ['type' => GraphQlType::string(), 'resolve' => function () {
                        return 'Apple';
                    }],
                    'b' => ['type' => GraphQlType::string(), 'resolve' => function () {
                        return 'Banana';
                    }],
                    'c' => ['type' => GraphQlType::string(), 'resolve' => function () {
                        return 'Cherry';
                    }],
                    'deep' => [
                        'type' => $Type,
                        'resolve' => function () {
                            return [];
                        }
                    ]
                ];
            }
        ]);
        $schema = new Schema(['query' => $Type]);
        $expected = [
            'data' => [
                'a' => 'Apple',
                'b' => 'Banana',
                'c' => 'Cherry',
                'deep' => [
                    'b' => 'Banana',
                    'c' => 'Cherry',
                    'deeper' => [
                        'b' => 'Banana',
                        'c' => 'Cherry'
                    ]
                ]
            ]
        ];

        expect(Executor::execute($schema, $ast)->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it provides info about current execution state
     */
    public function testProvidesInfoAboutCurrentExecutionState():void
    {
        $ast = Parser::parse('query ($var: String) { result: test }');

        /** @var ResolveInfo $info */
        $info = null;
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Test',
                'fields' => [
                    'test' => [
                        'type' => GraphQlType::string(),
                        'resolve' => function($val, $args, $ctx, $_info) use (&$info) {
                            $info = $_info;
                        }
                    ]
                ]
            ])
        ]);

        $rootValue = [ 'root' => 'val' ];

        Executor::execute($schema, $ast, $rootValue, null, [ 'var' => '123' ]);

        expect(\array_keys((array) $info))->toBePHPEqual([
            'fieldName',
            'fieldASTs',
            'fieldNodes',
            'returnType',
            'parentType',
            'path',
            'schema',
            'fragments',
            'rootValue',
            'operation',
            'variableValues',
        ]);

        expect($info->fieldName)->toBePHPEqual('test');
        expect(\count($info->fieldNodes))->toBePHPEqual(1);
        expect($info->fieldNodes[0])->toBeSame($ast->definitions[0]->selectionSet->selections[0]);
        expect($info->returnType)->toBeSame(GraphQlType::string());
        expect($info->parentType)->toBeSame($schema->getQueryType());
        expect($info->path)->toBePHPEqual(['result']);
        expect($info->schema)->toBeSame($schema);
        expect($info->rootValue)->toBeSame($rootValue);
        expect($info->operation)->toBePHPEqual($ast->definitions[0]);
        expect($info->variableValues)->toBePHPEqual(['var' => '123']);
    }

    /**
     * @it threads root value context correctly
     */
    public function testThreadsContextCorrectly():void
    {
        // threads context correctly
        $doc = 'query Example { a }';

        $gotHere = false;

        $data = [
            'contextThing' => 'thing',
        ];

        $ast = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'a' => [
                        'type' => GraphQlType::string(),
                        'resolve' => function ($context) use ($doc, &$gotHere) {
                            expect($context['contextThing'])->toBePHPEqual('thing');
                            $gotHere = true;
                        }
                    ]
                ]
            ])
        ]);

        Executor::execute($schema, $ast, $data, null, [], 'Example');
        expect($gotHere)->toBePHPEqual(true);
    }

    /**
     * @it correctly threads arguments
     */
    public function testCorrectlyThreadsArguments():void
    {
        $doc = '
      query Example {
        b(numArg: 123, stringArg: "foo")
      }
        ';

        $gotHere = false;

        $docAst = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'b' => [
                        'args' => [
                            'numArg' => ['type' => GraphQlType::int()],
                            'stringArg' => ['type' => GraphQlType::string()]
                        ],
                        'type' => GraphQlType::string(),
                        'resolve' => function ($_, $args) use (&$gotHere) {
                            expect($args['numArg'])->toBePHPEqual(123);
                            expect($args['stringArg'])->toBePHPEqual('foo');
                            $gotHere = true;
                        }
                    ]
                ]
            ])
        ]);
        Executor::execute($schema, $docAst, null, null, [], 'Example');
        expect(true)->toBeSame($gotHere);
    }

    /**
     * @it nulls out error subtrees
     */
    public function testNullsOutErrorSubtrees():void
    {
        $doc = '{
      sync
      syncError
      syncRawError
      syncReturnError
      syncReturnErrorList
      async
      asyncReject
      asyncRawReject
      asyncEmptyReject
      asyncError
      asyncRawError
      asyncReturnError
        }';

        $data = [
            'sync' => function () {
                return 'sync';
            },
            'syncError' => function () {
                throw new UserError('Error getting syncError');
            },
            'syncRawError' => function() {
                throw new UserError('Error getting syncRawError');
            },
            // inherited from JS reference implementation, but make no sense in this PHP impl
            // leaving it just to simplify migrations from newer js versions
            'syncReturnError' => function() {
                return new UserError('Error getting syncReturnError');
            },
            'syncReturnErrorList' => function () {
                return [
                    'sync0',
                    new UserError('Error getting syncReturnErrorList1'),
                    'sync2',
                    new UserError('Error getting syncReturnErrorList3')
                ];
            },
            'async' => function() {
                return new Deferred(function() { return 'async'; });
            },
            'asyncReject' => function() {
                return new Deferred(function() { throw new UserError('Error getting asyncReject'); });
            },
            'asyncRawReject' => function () {
                return new Deferred(function() {
                    throw new UserError('Error getting asyncRawReject');
                });
            },
            'asyncEmptyReject' => function () {
                return new Deferred(function() {
                    throw new UserError();
                });
            },
            'asyncError' => function() {
                return new Deferred(function() {
                    throw new UserError('Error getting asyncError');
                });
            },
            // inherited from JS reference implementation, but make no sense in this PHP impl
            // leaving it just to simplify migrations from newer js versions
            'asyncRawError' => function() {
                return new Deferred(function() {
                    throw new UserError('Error getting asyncRawError');
                });
            },
            'asyncReturnError' => function() {
                return new Deferred(function() {
                    throw new UserError('Error getting asyncReturnError');
                });
            },
        ];

        $docAst = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'sync' => ['type' => GraphQlType::string()],
                    'syncError' => ['type' => GraphQlType::string()],
                    'syncRawError' => ['type' => GraphQlType::string()],
                    'syncReturnError' => ['type' => GraphQlType::string()],
                    'syncReturnErrorList' => ['type' => GraphQlType::listOf(GraphQlType::string())],
                    'async' => ['type' => GraphQlType::string()],
                    'asyncReject' => ['type' => GraphQlType::string() ],
                    'asyncRawReject' => ['type' => GraphQlType::string() ],
                    'asyncEmptyReject' => ['type' => GraphQlType::string() ],
                    'asyncError' => ['type' => GraphQlType::string()],
                    'asyncRawError' => ['type' => GraphQlType::string()],
                    'asyncReturnError' => ['type' => GraphQlType::string()],
                ]
            ])
        ]);

        $expected = [
            'data' => [
                'sync' => 'sync',
                'syncError' => null,
                'syncRawError' => null,
                'syncReturnError' => null,
                'syncReturnErrorList' => ['sync0', null, 'sync2', null],
                'async' => 'async',
                'asyncReject' => null,
                'asyncRawReject' => null,
                'asyncEmptyReject' => null,
                'asyncError' => null,
                'asyncRawError' => null,
                'asyncReturnError' => null,
            ],
            'errors' => [
                [
                    'message' => 'Error getting syncError',
                    'locations' => [['line' => 3, 'column' => 7]],
                    'path' => ['syncError']
                ],
                [
                    'message' => 'Error getting syncRawError',
                    'locations' => [ [ 'line' => 4, 'column' => 7 ] ],
                    'path'=> [ 'syncRawError' ]
                ],
                [
                    'message' => 'Error getting syncReturnError',
                    'locations' => [['line' => 5, 'column' => 7]],
                    'path' => ['syncReturnError']
                ],
                [
                    'message' => 'Error getting syncReturnErrorList1',
                    'locations' => [['line' => 6, 'column' => 7]],
                    'path' => ['syncReturnErrorList', 1]
                ],
                [
                    'message' => 'Error getting syncReturnErrorList3',
                    'locations' => [['line' => 6, 'column' => 7]],
                    'path' => ['syncReturnErrorList', 3]
                ],
                [
                    'message' => 'Error getting asyncReject',
                    'locations' => [['line' => 8, 'column' => 7]],
                    'path' => ['asyncReject']
                ],
                [
                    'message' => 'Error getting asyncRawReject',
                    'locations' => [['line' => 9, 'column' => 7]],
                    'path' => ['asyncRawReject']
                ],
                [
                    'message' => 'An unknown error occurred.',
                    'locations' => [['line' => 10, 'column' => 7]],
                    'path' => ['asyncEmptyReject']
                ],
                [
                    'message' => 'Error getting asyncError',
                    'locations' => [['line' => 11, 'column' => 7]],
                    'path' => ['asyncError']
                ],
                [
                    'message' => 'Error getting asyncRawError',
                    'locations' => [ [ 'line' => 12, 'column' => 7 ] ],
                    'path' => [ 'asyncRawError' ]
                ],
                [
                    'message' => 'Error getting asyncReturnError',
                    'locations' => [['line' => 13, 'column' => 7]],
                    'path' => ['asyncReturnError']
                ],
            ]
        ];

        $result = Executor::execute($schema, $docAst, $data)->toArray();

        expect($result)->toInclude($expected);
    }

    /**
     * @it uses the inline operation if no operation name is provided
     */
    public function testUsesTheInlineOperationIfNoOperationIsProvided():void
    {
        $doc = '{ a }';
        $data = ['a' => 'b'];
        $ast = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'a' => ['type' => GraphQlType::string()],
                ]
            ])
        ]);

        $ex = Executor::execute($schema, $ast, $data);

        expect($ex->toArray())->toBePHPEqual(['data' => ['a' => 'b']]);
    }

    /**
     * @it uses the only operation if no operation name is provided
     */
    public function testUsesTheOnlyOperationIfNoOperationIsProvided():void
    {
        $doc = 'query Example { a }';
        $data = [ 'a' => 'b' ];
        $ast = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'a' => [ 'type' => GraphQlType::string() ],
                ]
            ])
        ]);

        $ex = Executor::execute($schema, $ast, $data);
        expect($ex->toArray())->toBePHPEqual(['data' => ['a' => 'b']]);
    }

    /**
     * @it uses the named operation if operation name is provided
     */
    public function testUsesTheNamedOperationIfOperationNameIsProvided():void
    {
        $doc = 'query Example { first: a } query OtherExample { second: a }';
        $data = [ 'a' => 'b' ];
        $ast = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'a' => [ 'type' => GraphQlType::string() ],
                ]
            ])
        ]);

        $result = Executor::execute($schema, $ast, $data, null, null, 'OtherExample');
        expect($result->toArray())->toBePHPEqual(['data' => ['second' => 'b']]);
    }

    /**
     * @it provides error if no operation is provided
     */
    public function testProvidesErrorIfNoOperationIsProvided():void
    {
        $doc = 'fragment Example on Type { a }';
        $data = [ 'a' => 'b' ];
        $ast = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'a' => [ 'type' => GraphQlType::string() ],
                ]
            ])
        ]);

        $result = Executor::execute($schema, $ast, $data);
        $expected = [
            'errors' => [
                [
                    'message' => 'Must provide an operation.',
                ]
            ]
        ];

        expect($result->toArray())->toInclude($expected);
    }

    /**
     * @it errors if no op name is provided with multiple operations
     */
    public function testErrorsIfNoOperationIsProvidedWithMultipleOperations():void
    {
        $doc = 'query Example { a } query OtherExample { a }';
        $data = ['a' => 'b'];
        $ast = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'a' => ['type' => GraphQlType::string()],
                ]
            ])
        ]);

        $result = Executor::execute($schema, $ast, $data);

        $expected = [
            'errors' => [
                [
                    'message' => 'Must provide operation name if query contains multiple operations.',
                ]
            ]
        ];

        expect($result->toArray())->toInclude($expected);
    }

    /**
     * @it errors if unknown operation name is provided
     */
    public function testErrorsIfUnknownOperationNameIsProvided():void
    {
        $doc = 'query Example { a } query OtherExample { a }';
        $ast = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'a' => ['type' => GraphQlType::string()],
                ]
            ])
        ]);


        $result = Executor::execute(
            $schema,
            $ast,
            null,
            null,
            null,
            'UnknownExample'
        );

        $expected = [
            'errors' => [
                [
                    'message' => 'Unknown operation named "UnknownExample".',
                ]
            ]

        ];

        expect($result->toArray())->toInclude($expected);
    }

    /**
     * @it uses the query schema for queries
     */
    public function testUsesTheQuerySchemaForQueries():void
    {
        $doc = 'query Q { a } mutation M { c }';
        $data = ['a' => 'b', 'c' => 'd'];
        $ast = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Q',
                'fields' => [
                    'a' => ['type' => GraphQlType::string()],
                ]
            ]),
            'mutation' => new ObjectType([
                'name' => 'M',
                'fields' => [
                    'c' => ['type' => GraphQlType::string()],
                ]
            ])
        ]);

        $queryResult = Executor::execute($schema, $ast, $data, null, [], 'Q');
        expect($queryResult->toArray())->toBePHPEqual(['data' => ['a' => 'b']]);
    }

    /**
     * @it uses the mutation schema for mutations
     */
    public function testUsesTheMutationSchemaForMutations():void
    {
        $doc = 'query Q { a } mutation M { c }';
        $data = [ 'a' => 'b', 'c' => 'd' ];
        $ast = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Q',
                'fields' => [
                    'a' => ['type' => GraphQlType::string()],
                ]
            ]),
            'mutation' => new ObjectType([
                'name' => 'M',
                'fields' => [
                    'c' => [ 'type' => GraphQlType::string() ],
                ]
            ])
        ]);
        $mutationResult = Executor::execute($schema, $ast, $data, null, [], 'M');
        expect($mutationResult->toArray())->toBePHPEqual(['data' => ['c' => 'd']]);
    }

    /**
     * @it uses the subscription schema for subscriptions
     */
    public function testUsesTheSubscriptionSchemaForSubscriptions():void
    {
        $doc = 'query Q { a } subscription S { a }';
        $data = [ 'a' => 'b', 'c' => 'd' ];
        $ast = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Q',
                'fields' => [
                    'a' => [ 'type' => GraphQlType::string() ],
                ]
            ]),
            'subscription' => new ObjectType([
                'name' => 'S',
                'fields' => [
                    'a' => [ 'type' => GraphQlType::string() ],
                ]
            ])
        ]);

        $subscriptionResult = Executor::execute($schema, $ast, $data, null, [], 'S');
        expect($subscriptionResult->toArray())->toBePHPEqual(['data' => ['a' => 'b']]);
    }

    public function testCorrectFieldOrderingDespiteExecutionOrder():void
    {
        $doc = '{
      a,
      b,
      c,
      d,
      e
    }';
        $data = [
            'a' => function () {
                return 'a';
            },
            'b' => function () {
                return new Deferred(function () { return 'b'; });
            },
            'c' => function () {
                return 'c';
            },
            'd' => function () {
                return new Deferred(function () { return 'd'; });
            },
            'e' => function () {
                return 'e';
            },
        ];

        $ast = Parser::parse($doc);

        $queryType = new ObjectType([
            'name' => 'DeepDataType',
            'fields' => [
                'a' => [ 'type' => GraphQlType::string() ],
                'b' => [ 'type' => GraphQlType::string() ],
                'c' => [ 'type' => GraphQlType::string() ],
                'd' => [ 'type' => GraphQlType::string() ],
                'e' => [ 'type' => GraphQlType::string() ],
            ]
        ]);
        $schema = new Schema(['query' => $queryType]);

        $expected = [
            'data' => [
                'a' => 'a',
                'b' => 'b',
                'c' => 'c',
                'd' => 'd',
                'e' => 'e',
            ]
        ];

        expect(Executor::execute($schema, $ast, $data)->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it Avoids recursion
     */
    public function testAvoidsRecursion():void
    {
        $doc = '
      query Q {
        a
        ...Frag
        ...Frag
      }

      fragment Frag on DataType {
        a,
        ...Frag
      }
        ';
        $data = ['a' => 'b'];
        $ast = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'a' => ['type' => GraphQlType::string()],
                ]
            ])
        ]);

        $queryResult = Executor::execute($schema, $ast, $data, null, [], 'Q');
        expect($queryResult->toArray())->toBePHPEqual(['data' => ['a' => 'b']]);
    }

    /**
     * @it does not include illegal fields in output
     */
    public function testDoesNotIncludeIllegalFieldsInOutput():void
    {
        $doc = 'mutation M {
      thisIsIllegalDontIncludeMe
    }';
        $ast = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Q',
                'fields' => [
                    'a' => ['type' => GraphQlType::string()],
                ]
            ]),
            'mutation' => new ObjectType([
                'name' => 'M',
                'fields' => [
                    'c' => ['type' => GraphQlType::string()],
                ]
            ])
        ]);
        $mutationResult = Executor::execute($schema, $ast);
        expect($mutationResult->toArray())->toBePHPEqual(['data' => []]);
    }

    /**
     * @it does not include arguments that were not set
     */
    public function testDoesNotIncludeArgumentsThatWereNotSet():void
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'field' => [
                        'type' => GraphQlType::string(),
                        'resolve' => function($data, $args) {return $args ? \json_encode($args) : '';},
                        'args' => [
                            'a' => ['type' => GraphQlType::boolean()],
                            'b' => ['type' => GraphQlType::boolean()],
                            'c' => ['type' => GraphQlType::boolean()],
                            'd' => ['type' => GraphQlType::int()],
                            'e' => ['type' => GraphQlType::int()]
                        ]
                    ]
                ]
            ])
        ]);

        $query = Parser::parse('{ field(a: true, c: false, e: 0) }');
        $result = Executor::execute($schema, $query);
        $expected = [
            'data' => [
                'field' => '{"a":true,"c":false,"e":0}'
            ]
        ];

        expect($result->toArray())->toBePHPEqual($expected);
    }

    /**
     * @it fails when an isTypeOf check is not met
     */
    public function testFailsWhenAnIsTypeOfCheckIsNotMet():void
    {
        $SpecialType = new ObjectType([
            'name' => 'SpecialType',
            'isTypeOf' => function($obj) {
                return $obj instanceof Special;
            },
            'fields' => [
                'value' => ['type' => GraphQlType::string()]
            ]
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'specials' => [
                        'type' => GraphQlType::listOf($SpecialType),
                        'resolve' => function($rootValue) {
                            return $rootValue['specials'];
                        }
                    ]
                ]
            ])
        ]);

        $query = Parser::parse('{ specials { value } }');
        $value = [
            'specials' => [ new Special('foo'), new NotSpecial('bar') ]
        ];
        $result = Executor::execute($schema, $query, $value);

        expect($result->data)->toBePHPEqual([
            'specials' => [
                ['value' => 'foo'],
                null
            ]
        ]);

        expect(\count($result->errors))->toBePHPEqual(1);
        expect($result->errors[0]->toSerializableArray())->toBePHPEqual([
            'message' => 'Expected value of type "SpecialType" but got: instance of GraphQL\Tests\Executor\NotSpecial.',
            'locations' => [['line' => 1, 'column' => 3]],
            'path' => ['specials', 1]
        ]);
    }

    /**
     * @it fails to execute a query containing a type definition
     */
    public function testFailsToExecuteQueryContainingTypeDefinition():void
    {
        $query = Parser::parse('
      { foo }

      type Query { foo: String }
    ');

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'foo' => ['type' => GraphQlType::string()]
                ]
            ])
        ]);


        $result = Executor::execute($schema, $query);

        $expected = [
            'errors' => [
                [
                    'message' => 'GraphQL cannot execute a request containing a ObjectTypeDefinition.',
                    'locations' => [['line' => 4, 'column' => 7]],
                ]
            ]
        ];

        expect($result->toArray())->toInclude($expected);
    }

    /**
     * @it uses a custom field resolver
     */
    public function testUsesACustomFieldResolver():void
    {
        $query = Parser::parse('{ foo }');

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'foo' => ['type' => GraphQlType::string()]
                ]
            ])
        ]);

        // For the purposes of test, just return the name of the field!
        $customResolver = function ($source, $args, $context, ResolveInfo $info) {
            return $info->fieldName;
        };

        $result = Executor::execute(
            $schema,
            $query,
            null,
            null,
            null,
            null,
            $customResolver
        );

        $expected = [
            'data' => ['foo' => 'foo']
        ];

        expect($result->toArray())->toBePHPEqual($expected);
    }

    public function testSubstitutesArgumentWithDefaultValue():void
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'field' => [
                        'type' => GraphQlType::string(),
                        'resolve' => function($data, $args) {return $args ? \json_encode($args) : '';},
                        'args' => [
                            'a' => ['type' => GraphQlType::boolean(), 'defaultValue' => 1],
                            'b' => ['type' => GraphQlType::boolean(), 'defaultValue' => null],
                            'c' => ['type' => GraphQlType::boolean(), 'defaultValue' => 0],
                            'd' => ['type' => GraphQlType::int(), 'defaultValue' => false],
                            'e' => ['type' => GraphQlType::int(), 'defaultValue' => '0'],
                            'f' => ['type' => GraphQlType::int(), 'defaultValue' => 'some-string'],
                            'g' => ['type' => GraphQlType::boolean()],
                            'h' => ['type' => new InputObjectType([
                                'name' => 'ComplexType',
                                'fields' => [
                                    'a' => ['type' => GraphQlType::int()],
                                    'b' => ['type' => GraphQlType::string()]
                                ]
                            ]), 'defaultValue' => ['a' => 1, 'b' => 'test']]
                        ]
                    ]
                ]
            ])
        ]);

        $query = Parser::parse('{ field }');
        $result = Executor::execute($schema, $query);
        $expected = [
            'data' => [
                'field' => '{"a":1,"b":null,"c":0,"d":false,"e":"0","f":"some-string","h":{"a":1,"b":"test"}}'
            ]
        ];

        expect($result->toArray())->toBePHPEqual($expected);
    }

    /**
     * @see https://github.com/webonyx/graphql-php/issues/59
     */
    public function testSerializesToEmptyObjectVsEmptyArray():void
    {
        $iface = null;

        $a = new ObjectType([
            'name' => 'A',
            'fields' => [
                'id' => GraphQlType::id()
            ],
            'interfaces' => function() use (&$iface) {
                return [$iface];
            }
        ]);

        $b = new ObjectType([
            'name' => 'B',
            'fields' => [
                'id' => GraphQlType::id()
            ],
            'interfaces' => function() use (&$iface) {
                return [$iface];
            }
        ]);

        $iface = new InterfaceType([
            'name' => 'Iface',
            'fields' => [
                'id' => GraphQlType::id()
            ],
            'resolveType' => function($v) use ($a, $b) {
                return $v['type'] === 'A' ? $a : $b;
            }
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'ab' => GraphQlType::listOf($iface)
                ]
            ]),
            'types' => [$a, $b]
        ]);

        $data = [
            'ab' => [
                ['id' => 1, 'type' => 'A'],
                ['id' => 2, 'type' => 'A'],
                ['id' => 3, 'type' => 'B'],
                ['id' => 4, 'type' => 'B']
            ]
        ];

        $query = Parser::parse('
            {
                ab {
                    ... on A{
                        id
                    }
                }
            }
        ');

        $result = Executor::execute($schema, $query, $data, null);

        expect($result->toArray())->toBePHPEqual([
            'data' => [
                'ab' => [
                    ['id' => '1'],
                    ['id' => '2'],
                    new \stdClass(),
                    new \stdClass()
                ]
            ]
        ]);
    }
}
