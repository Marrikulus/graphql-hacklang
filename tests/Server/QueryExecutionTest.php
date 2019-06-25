<?hh //partial

namespace GraphQL\Tests\Server;

use GraphQL\Deferred;
use function Facebook\FBExpect\expect;
use GraphQL\Error\Debug;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\UserError;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Server\Helper;
use GraphQL\Server\OperationParams;
use GraphQL\Server\RequestError;
use GraphQL\Server\ServerConfig;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\CustomValidationRule;
use GraphQL\Validator\ValidationContext;

class QueryExecutionTest extends TestCase
{
    /**
     * @var ServerConfig
     */
    private $config;

    public async function beforeEachTestAsync(): Awaitable<void>
    {
        $schema = $this->buildSchema();
        $this->config = ServerConfig::create()
            ->setSchema($schema);
    }

    public function testSimpleQueryExecution():void
    {
        $query = '{f1}';

        $expected = [
            'data' => [
                'f1' => 'f1'
            ]
        ];

        $this->assertQueryResultEquals($expected, $query);
    }

    public function testReturnsSyntaxErrors():void
    {
        $query = '{f1';

        $result = $this->executeQuery($query);
        expect($result->data)->toBeSame(null);
        expect(\count($result->errors))->toBeSame(1);
        expect($result->errors[0]->getMessage())
            ->toContain('Syntax Error GraphQL (1:4) Expected Name, found <EOF>');
    }

    public function testDebugExceptions():void
    {
        $debug = Debug::INCLUDE_DEBUG_MESSAGE | Debug::INCLUDE_TRACE;
        $this->config->setDebug($debug);

        $query = '
        {
            fieldWithException
            f1
        }
        ';

        $expected = [
            'data' => [
                'fieldWithException' => null,
                'f1' => 'f1'
            ],
            'errors' => [
                [
                    'message' => 'This is the exception we want',
                    'path' => ['fieldWithException'],
                    'trace' => []
                ]
            ]
        ];

        $result = $this->executeQuery($query)->toArray();
        expect($result)->toInclude($expected);
    }

    public function testPassesRootValueAndContext():void
    {
        $rootValue = 'myRootValue';
        $context = new \stdClass();

        $this->config
            ->setContext($context)
            ->setRootValue($rootValue);

        $query = '
        {
            testContextAndRootValue
        }
        ';

        expect(!isset($context->testedRootValue))->toBeTrue();
        $this->executeQuery($query);
        expect($context->testedRootValue)->toBeSame($rootValue);
    }

    public function testPassesVariables():void
    {
        $variables = ['a' => 'a', 'b' => 'b'];
        $query = '
            query ($a: String!, $b: String!) {
                a: fieldWithArg(arg: $a)
                b: fieldWithArg(arg: $b)
            }
        ';
        $expected = [
            'data' => [
                'a' => 'a',
                'b' => 'b'
            ]
        ];
        $this->assertQueryResultEquals($expected, $query, $variables);
    }

    public function testPassesCustomValidationRules():void
    {
        $query = '
            {nonExistentField}
        ';
        $expected = [
            'errors' => [
                ['message' => 'Cannot query field "nonExistentField" on type "Query".']
            ]
        ];

        $this->assertQueryResultEquals($expected, $query);

        $called = false;

        $rules = [
            new CustomValidationRule('SomeRule', function($context) use (&$called) {
                $called = true;
                return [];
            })
        ];

        $this->config->setValidationRules($rules);
        $expected = [
            'data' => []
        ];
        $this->assertQueryResultEquals($expected, $query);
        expect($called)->toBeTrue();
    }

    public function testAllowsValidationRulesAsClosure():void
    {
        $called = false;
        $params = $doc = $operationType = null;

        $this->config->setValidationRules(function($p, $d, $o) use (&$called, &$params, &$doc, &$operationType) {
            $called = true;
            $params = $p;
            $doc = $d;
            $operationType = $o;
            return [];
        });

        expect($called)->toBeFalse();
        $this->executeQuery('{f1}');
        expect($called)->toBeTrue();
        expect($params)->toBeInstanceOf(OperationParams::class);
        expect($doc)->toBeInstanceOf(DocumentNode::class);
        expect($operationType)->toBePHPEqual('query');
    }

    public function testAllowsDifferentValidationRulesDependingOnOperation():void
    {
        $q1 = '{f1}';
        $q2 = '{invalid}';
        $called1 = false;
        $called2 = false;

        $this->config->setValidationRules(function(OperationParams $params) use ($q1, $q2, &$called1, &$called2) {
            if ($params->query === $q1) {
                $called1 = true;
                return DocumentValidator::allRules();
            } else {
                $called2 = true;
                return [
                    new CustomValidationRule('MyRule', function(ValidationContext $context) {
                        $context->reportError(new Error("This is the error we are looking for!"));
                        return [];
                    })
                ];
            }
        });

        $expected = ['data' => ['f1' => 'f1']];
        $this->assertQueryResultEquals($expected, $q1);
        expect($called1)->toBeTrue();
        expect($called2)->toBeFalse();

        $called1 = false;
        $called2 = false;
        $expected = ['errors' => [['message' => 'This is the error we are looking for!']]];
        $this->assertQueryResultEquals($expected, $q2);
        expect($called1)->toBeFalse();
        expect($called2)->toBeTrue();
    }

    public function testAllowsSkippingValidation():void
    {
        $this->config->setValidationRules([]);
        $query = '{nonExistentField}';
        $expected = ['data' => []];
        $this->assertQueryResultEquals($expected, $query);
    }

    public function testPersistedQueriesAreDisabledByDefault():void
    {
        $result = $this->executePersistedQuery('some-id');

        $expected = [
            'errors' => [
                [
                    'message' => 'Persisted queries are not supported by this server',
                    'category' => 'request'
                ]
            ]
        ];
        expect($result->toArray())->toBePHPEqual($expected);
    }

    public function testBatchedQueriesAreDisabledByDefault():void
    {
        $batch = [
            [
                'query' => '{invalid}'
            ],
            [
                'query' => '{f1,fieldWithException}'
            ]
        ];

        $result = $this->executeBatchedQuery($batch);

        $expected = [
            [
                'errors' => [
                    [
                        'message' => 'Batched queries are not supported by this server',
                        'category' => 'request'
                    ]
                ]
            ],
            [
                'errors' => [
                    [
                        'message' => 'Batched queries are not supported by this server',
                        'category' => 'request'
                    ]
                ]
            ],
        ];

        expect($result[0]->toArray())->toBePHPEqual($expected[0]);
        expect($result[1]->toArray())->toBePHPEqual($expected[1]);
    }

    public function testMutationsAreNotAllowedInReadonlyMode():void
    {
        $mutation = 'mutation { a }';

        $expected = [
            'errors' => [
                [
                    'message' => 'GET supports only query operation',
                    'category' => 'request'
                ]
            ]
        ];

        $result = $this->executeQuery($mutation, null, true);
        expect($result->toArray())->toBePHPEqual($expected);
    }

    public function testAllowsPersistentQueries():void
    {
        $called = false;
        $this->config->setPersistentQueryLoader(function($queryId, OperationParams $params) use (&$called) {
            $called = true;
            expect($queryId)->toBePHPEqual('some-id');
            return '{f1}';
        });

        $result = $this->executePersistedQuery('some-id');
        expect($called)->toBeTrue();

        $expected = [
            'data' => [
                'f1' => 'f1'
            ]
        ];
        expect($result->toArray())->toBePHPEqual($expected);

        // Make sure it allows returning document node:
        $called = false;
        $this->config->setPersistentQueryLoader(function($queryId, OperationParams $params) use (&$called) {
            $called = true;
            expect($queryId)->toBePHPEqual('some-id');
            return Parser::parse('{f1}');
        });
        $result = $this->executePersistedQuery('some-id');
        expect($called)->toBeTrue();
        expect($result->toArray())->toBePHPEqual($expected);
    }

    public function testProhibitsInvalidPersistedQueryLoader():void
    {
        $this->setExpectedException(
            InvariantViolation::class,
            'Persistent query loader must return query string or instance of GraphQL\Language\AST\DocumentNode '.
            'but got: associative array(1) with first key: "err"'
        );
        $this->config->setPersistentQueryLoader(function($queryId, OperationParams $params) use (&$called) {
            return ['err' => 'err'];
        });
        $this->executePersistedQuery('some-id');
    }

    public function testPersistedQueriesAreStillValidatedByDefault():void
    {
        $this->config->setPersistentQueryLoader(function() {
            return '{invalid}';
        });
        $result = $this->executePersistedQuery('some-id');
        $expected = [
            'errors' => [
                [
                    'message' => 'Cannot query field "invalid" on type "Query".',
                    'locations' => [ ['line' => 1, 'column' => 2] ],
                    'category' => 'graphql'
                ]
            ]
        ];
        expect($result->toArray())->toBePHPEqual($expected);

    }

    public function testAllowSkippingValidationForPersistedQueries():void
    {
        $this->config
            ->setPersistentQueryLoader(function($queryId) {
                if ($queryId === 'some-id') {
                    return '{invalid}';
                } else {
                    return '{invalid2}';
                }
            })
            ->setValidationRules(function(OperationParams $params) {
                if ($params->queryId === 'some-id') {
                    return [];
                } else {
                    return DocumentValidator::allRules();
                }
            });

        $result = $this->executePersistedQuery('some-id');
        $expected = [
            'data' => []
        ];
        expect($result->toArray())->toBePHPEqual($expected);

        $result = $this->executePersistedQuery('some-other-id');
        $expected = [
            'errors' => [
                [
                    'message' => 'Cannot query field "invalid2" on type "Query".',
                    'locations' => [ ['line' => 1, 'column' => 2] ],
                    'category' => 'graphql'
                ]
            ]
        ];
        expect($result->toArray())->toBePHPEqual($expected);
    }

    public function testProhibitsUnexpectedValidationRules():void
    {
        $this->setExpectedException(
            InvariantViolation::class,
            'Expecting validation rules to be array or callable returning array, but got: instance of stdClass'
        );
        $this->config->setValidationRules(function(OperationParams $params) {
            return new \stdClass();
        });
        $this->executeQuery('{f1}');
    }

    public function testExecutesBatchedQueries():void
    {
        $this->config->setQueryBatching(true);

        $batch = [
            [
                'query' => '{invalid}'
            ],
            [
                'query' => '{f1,fieldWithException}'
            ],
            [
                'query' => '
                    query ($a: String!, $b: String!) {
                        a: fieldWithArg(arg: $a)
                        b: fieldWithArg(arg: $b)
                    }
                ',
                'variables' => ['a' => 'a', 'b' => 'b'],
            ]
        ];

        $result = $this->executeBatchedQuery($batch);

        $expected = [
            [
                'errors' => [['message' => 'Cannot query field "invalid" on type "Query".']]
            ],
            [
                'data' => [
                    'f1' => 'f1',
                    'fieldWithException' => null
                ],
                'errors' => [
                    ['message' => 'This is the exception we want']
                ]
            ],
            [
                'data' => [
                    'a' => 'a',
                    'b' => 'b'
                ]
            ]
        ];

        expect($result[0]->toArray())->toInclude($expected[0]);
        expect($result[1]->toArray())->toInclude($expected[1]);
        expect($result[2]->toArray())->toInclude($expected[2]);
    }

    public function testDeferredsAreSharedAmongAllBatchedQueries():void
    {
        $batch = [
            [
                'query' => '{dfd(num: 1)}'
            ],
            [
                'query' => '{dfd(num: 2)}'
            ],
            [
                'query' => '{dfd(num: 3)}',
            ]
        ];

        $calls = [];

        $this->config
            ->setQueryBatching(true)
            ->setRootValue('1')
            ->setContext([
                'buffer' => function($num) use (&$calls) {
                    $calls[] = "buffer: $num";
                },
                'load' => function($num) use (&$calls) {
                    $calls[] = "load: $num";
                    return "loaded: $num";
                }
            ]);

        $result = $this->executeBatchedQuery($batch);

        $expectedCalls = [
            'buffer: 1',
            'buffer: 2',
            'buffer: 3',
            'load: 1',
            'load: 2',
            'load: 3',
        ];
        expect($calls)->toBePHPEqual($expectedCalls);

        $expected = [
            [
                'data' => [
                    'dfd' => 'loaded: 1'
                ]
            ],
            [
                'data' => [
                    'dfd' => 'loaded: 2'
                ]
            ],
            [
                'data' => [
                    'dfd' => 'loaded: 3'
                ]
            ],
        ];

        expect($result[0]->toArray())->toBePHPEqual($expected[0]);
        expect($result[1]->toArray())->toBePHPEqual($expected[1]);
        expect($result[2]->toArray())->toBePHPEqual($expected[2]);
    }

    public function testValidatesParamsBeforeExecution():void
    {
        $op = OperationParams::create(['queryBad' => '{f1}']);
        $helper = new Helper();
        $result = $helper->executeOperation($this->config, $op);
        expect($result)->toBeInstanceOf(ExecutionResult::class);

        expect($result->data)->toBePHPEqual(null);
        expect(\count($result->errors))->toBeSame(1);

        expect($result->errors[0]->getMessage())
            ->toBePHPEqual('GraphQL Request must include at least one of those two parameters: "query" or "queryId"');

        expect($result->errors[0]->getPrevious())
            ->toBeInstanceOf(RequestError::class);
    }

    public function testAllowsContextAsClosure():void
    {
        $called = false;
        $params = $doc = $operationType = null;

        $this->config->setContext(function($p, $d, $o) use (&$called, &$params, &$doc, &$operationType) {
            $called = true;
            $params = $p;
            $doc = $d;
            $operationType = $o;
        });

        expect($called)->toBeFalse();
        $this->executeQuery('{f1}');
        expect($called)->toBeTrue();
        expect($params)->toBeInstanceOf(OperationParams::class);
        expect($doc)->toBeInstanceOf(DocumentNode::class);
        expect($operationType)->toBePHPEqual('query');
    }

    public function testAllowsRootValueAsClosure():void
    {
        $called = false;
        $params = $doc = $operationType = null;

        $this->config->setRootValue(function($p, $d, $o) use (&$called, &$params, &$doc, &$operationType) {
            $called = true;
            $params = $p;
            $doc = $d;
            $operationType = $o;
        });

        expect($called)->toBeFalse();
        $this->executeQuery('{f1}');
        expect($called)->toBeTrue();
        expect($params)->toBeInstanceOf(OperationParams::class);
        expect($doc)->toBeInstanceOf(DocumentNode::class);
        expect($operationType)->toBePHPEqual('query');
    }

    public function testAppliesErrorFormatter():void
    {
        $called = false;
        $error = null;
        $this->config->setErrorFormatter(function($e) use (&$called, &$error) {
            $called = true;
            $error = $e;
            return ['test' => 'formatted'];
        });

        $result = $this->executeQuery('{fieldWithException}');
        expect($called)->toBeFalse();
        $formatted = $result->toArray();
        $expected = [
            'errors' => [
                ['test' => 'formatted']
            ]
        ];
        expect($called)->toBeTrue();
        expect($formatted)->toInclude($expected);
        expect($error)->toBeInstanceOf(Error::class);

        // Assert debugging still works even with custom formatter
        $formatted = $result->toArray(Debug::INCLUDE_TRACE);
        $expected = [
            'errors' => [
                [
                    'test' => 'formatted',
                    'trace' => []
                ]
            ]
        ];
        expect($formatted)->toInclude($expected);
    }

    public function testAppliesErrorsHandler():void
    {
        $called = false;
        $errors = null;
        $formatter = null;
        $this->config->setErrorsHandler(function($e, $f) use (&$called, &$errors, &$formatter) {
            $called = true;
            $errors = $e;
            $formatter = $f;
            return [
                ['test' => 'handled']
            ];
        });

        $result = $this->executeQuery('{fieldWithException,test: fieldWithException}');

        expect($called)->toBeFalse();
        $formatted = $result->toArray();
        $expected = [
            'errors' => [
                ['test' => 'handled']
            ]
        ];
        expect($called)->toBeTrue();
        expect($formatted)->toInclude($expected);
        expect($errors)->toBeType('array');
        expect(\count($errors))->toBeSame(2);
        expect($formatter)->toBeType('callable');
        expect($formatted)->toInclude($expected);
    }

    private function executePersistedQuery($queryId, $variables = null)
    {
        $op = OperationParams::create(['queryId' => $queryId, 'variables' => $variables]);
        $helper = new Helper();
        $result = $helper->executeOperation($this->config, $op);
        expect($result)->toBeInstanceOf(ExecutionResult::class);
        return $result;
    }

    private function executeQuery($query, $variables = null, $readonly = false)
    {
        $op = OperationParams::create(['query' => $query, 'variables' => $variables], $readonly);
        $helper = new Helper();
        $result = $helper->executeOperation($this->config, $op);
        expect($result)->toBeInstanceOf(ExecutionResult::class);
        return $result;
    }

    private function executeBatchedQuery(array $qs)
    {
        $batch = [];
        foreach ($qs as $params) {
            $batch[] = OperationParams::create($params);
        }
        $helper = new Helper();
        $result = $helper->executeBatch($this->config, $batch);
        expect($result)->toBeType('array');
        expect(\count($result))->toBeSame(\count($qs));

        foreach ($result as $index => $entry) {
            expect($entry)->toBeInstanceOf(ExecutionResult::class, "Result at $index is not an instance of " . ExecutionResult::class);
        }
        return $result;
    }

    private function assertQueryResultEquals($expected, $query, $variables = null)
    {
        $result = $this->executeQuery($query, $variables);
        expect($result->toArray(1))->toInclude($expected);
        return $result;
    }
}
