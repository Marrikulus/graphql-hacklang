<?hh //partial
//decl
namespace GraphQL\Tests;

use GraphQL\Error\Debug;
use function Facebook\FBExpect\expect;
use GraphQL\Error\FormattedError;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\SyntaxError;
use GraphQL\Error\UserError;
use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Schema;
use GraphQL\Server;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\GraphQlType;
use GraphQL\Type\EagerResolution;
use GraphQL\Validator\DocumentValidator;

class ServerTest extends \Facebook\HackTest\HackTest
{
    public function testDefaults():void
    {
        $server = @new Server();
        expect($server->getQueryType())->toBePHPEqual(null);
        expect($server->getMutationType())->toBePHPEqual(null);
        expect($server->getSubscriptionType())->toBePHPEqual(null);
        expect($server->getDirectives())->toBePHPEqual(Directive::getInternalDirectives());
        expect($server->getTypes())->toBePHPEqual([]);
        expect($server->getTypeResolutionStrategy())->toBePHPEqual(null);

        expect($server->getContext())->toBePHPEqual(null);
        expect($server->getRootValue())->toBePHPEqual(null);
        expect($server->getDebug())->toBePHPEqual(0);

        expect($server->getExceptionFormatter())->toBePHPEqual(['GraphQL\Error\FormattedError', 'createFromException']);
        expect($server->getPhpErrorFormatter())->toBePHPEqual(['GraphQL\Error\FormattedError', 'createFromPHPError']);
        expect($server->getPromiseAdapter())->toBePHPEqual(null);
        expect($server->getUnexpectedErrorMessage())->toBePHPEqual('Unexpected Error');
        expect($server->getUnexpectedErrorStatus())->toBePHPEqual(500);
        expect($server->getValidationRules())->toBePHPEqual(DocumentValidator::allRules());

        $this->setExpectedException(InvariantViolation::class, 'Schema query must be Object Type but got: NULL');
        $server->getSchema();
    }

    public function testCannotUseSetQueryTypeAndSetSchema():void
    {
        $queryType = new ObjectType(['name' => 'A', 'fields' => ['a' => GraphQlType::string()]]);
        $schema = new Schema([
            'query' => $queryType,
        ]);

        $this->setExpectedException(InvariantViolation::class,
            'Cannot set Schema on Server: Query Type is already set ' .
            '(GraphQL\Server::setQueryType is mutually exclusive with GraphQL\Server::setSchema)');
        Server::create()
            ->setQueryType($queryType)
            ->setSchema($schema);
    }

    public function testCannotUseSetMutationTypeAndSetSchema():void
    {
        $mutationType = $queryType = new ObjectType(['name' => 'A', 'fields' => ['a' => GraphQlType::string()]]);
        $schema = new Schema([
            'query' => $queryType,
        ]);

        $this->setExpectedException(InvariantViolation::class,
            'Cannot set Schema on Server: Mutation Type is already set ' .
            '(GraphQL\Server::setMutationType is mutually exclusive with GraphQL\Server::setSchema)');
        Server::create()
            ->setMutationType($mutationType)
            ->setSchema($schema);
    }

    public function testCannotUseSetSubscriptionTypeAndSetSchema():void
    {
        $subscriptionType = $queryType = new ObjectType(['name' => 'A', 'fields' => ['a' => GraphQlType::string()]]);
        $schema = new Schema([
            'query' => $queryType,
        ]);

        $this->setExpectedException(InvariantViolation::class,
            'Cannot set Schema on Server: Subscription Type is already set ' .
            '(GraphQL\Server::setSubscriptionType is mutually exclusive with GraphQL\Server::setSchema)');
        Server::create()
            ->setSubscriptionType($subscriptionType)
            ->setSchema($schema);
    }

    public function testCannotUseSetDirectivesAndSetSchema():void
    {
        $queryType = new ObjectType(['name' => 'A', 'fields' => ['a' => GraphQlType::string()]]);
        $schema = new Schema([
            'query' => $queryType,
        ]);

        $this->setExpectedException(InvariantViolation::class,
            'Cannot set Schema on Server: Directives are already set ' .
            '(GraphQL\Server::setDirectives is mutually exclusive with GraphQL\Server::setSchema)');
        Server::create()
            ->setDirectives(Directive::getInternalDirectives())
            ->setSchema($schema);
    }

    public function testCannotUseAddTypesAndSetSchema():void
    {
        $mutationType = $queryType = new ObjectType(['name' => 'A', 'fields' => ['a' => GraphQlType::string()]]);
        $schema = new Schema([
            'query' => $queryType,
        ]);

        $this->setExpectedException(InvariantViolation::class,
            'Cannot set Schema on Server: Additional types are already set ' .
            '(GraphQL\Server::addTypes is mutually exclusive with GraphQL\Server::setSchema)');
        Server::create()
            ->addTypes([$queryType, $mutationType])
            ->setSchema($schema);
    }

    public function testCannotUseSetTypeResolutionStrategyAndSetSchema():void
    {
        $mutationType = $queryType = new ObjectType(['name' => 'A', 'fields' => ['a' => GraphQlType::string()]]);
        $schema = new Schema([
            'query' => $queryType,
        ]);

        $this->setExpectedException(InvariantViolation::class,
            'Cannot set Schema on Server: Type Resolution Strategy is already set ' .
            '(GraphQL\Server::setTypeResolutionStrategy is mutually exclusive with GraphQL\Server::setSchema)');
        Server::create()
            ->setTypeResolutionStrategy(new EagerResolution([$queryType, $mutationType]))
            ->setSchema($schema);
    }

    public function testCannotUseSetSchemaAndSetQueryType():void
    {
        $queryType = new ObjectType(['name' => 'A', 'fields' => ['a' => GraphQlType::string()]]);
        $schema = new Schema([
            'query' => $queryType,
        ]);

        $this->setExpectedException(InvariantViolation::class,
            'Cannot set Query Type on Server: Schema is already set ' .
            '(GraphQL\Server::setQueryType is mutually exclusive with GraphQL\Server::setSchema)');
        Server::create()
            ->setSchema($schema)
            ->setQueryType($queryType);
    }

    public function testCannotUseSetSchemaAndSetMutationType():void
    {
        $mutationType = $queryType = new ObjectType(['name' => 'A', 'fields' => ['a' => GraphQlType::string()]]);
        $schema = new Schema([
            'query' => $queryType,
        ]);

        $this->setExpectedException(InvariantViolation::class,
            'Cannot set Mutation Type on Server: Schema is already set ' .
            '(GraphQL\Server::setMutationType is mutually exclusive with GraphQL\Server::setSchema)');
        Server::create()
            ->setSchema($schema)
            ->setMutationType($mutationType);
    }

    public function testCannotUseSetSchemaAndSetSubscriptionType():void
    {
        $subscriptionType = $queryType = new ObjectType(['name' => 'A', 'fields' => ['a' => GraphQlType::string()]]);
        $schema = new Schema([
            'query' => $queryType,
        ]);

        $this->setExpectedException(InvariantViolation::class,
            'Cannot set Subscription Type on Server: Schema is already set ' .
            '(GraphQL\Server::setSubscriptionType is mutually exclusive with GraphQL\Server::setSchema)');
        Server::create()
            ->setSchema($schema)
            ->setSubscriptionType($subscriptionType);
    }

    public function testCannotUseSetSchemaAndSetDirectives():void
    {
        $queryType = new ObjectType(['name' => 'A', 'fields' => ['a' => GraphQlType::string()]]);
        $schema = new Schema([
            'query' => $queryType,
        ]);

        $this->setExpectedException(InvariantViolation::class,
            'Cannot set Directives on Server: Schema is already set ' .
            '(GraphQL\Server::setDirectives is mutually exclusive with GraphQL\Server::setSchema)');
        Server::create()
            ->setSchema($schema)
            ->setDirectives([]);

    }

    public function testCannotUseSetSchemaAndAddTypes():void
    {
        $mutationType = $queryType = new ObjectType(['name' => 'A', 'fields' => ['a' => GraphQlType::string()]]);
        $schema = new Schema([
            'query' => $queryType,
        ]);

        $this->setExpectedException(InvariantViolation::class,
            'Cannot set Types on Server: Schema is already set ' .
            '(GraphQL\Server::addTypes is mutually exclusive with GraphQL\Server::setSchema)');
        Server::create()
            ->setSchema($schema)
            ->addTypes([$queryType, $mutationType]);
    }

    public function testCanUseSetSchemaAndAddEmptyTypes():void
    {
        $queryType = new ObjectType(['name' => 'A', 'fields' => ['a' => GraphQlType::string()]]);
        $schema = new Schema([
            'query' => $queryType,
        ]);

        // But empty types should work (as they don't change anything):
        Server::create()
            ->setSchema($schema)
            ->addTypes([]);
    }

    public function testCannotUseSetSchemaAndSetTypeResolutionStrategy():void
    {
        $mutationType = $queryType = new ObjectType(['name' => 'A', 'fields' => ['a' => GraphQlType::string()]]);
        $schema = new Schema([
            'query' => $queryType,
        ]);

        $this->setExpectedException(InvariantViolation::class,
            'Cannot set Type Resolution Strategy on Server: Schema is already set ' .
            '(GraphQL\Server::setTypeResolutionStrategy is mutually exclusive with GraphQL\Server::setSchema)');
        Server::create()
            ->setSchema($schema)
            ->setTypeResolutionStrategy(new EagerResolution([$queryType, $mutationType]));

    }

    public function testCannotUseSetSchemaAndSetSchema():void
    {
        $queryType = new ObjectType(['name' => 'A', 'fields' => ['a' => GraphQlType::string()]]);
        $schema = new Schema([
            'query' => $queryType,
        ]);

        $this->setExpectedException(InvariantViolation::class,
            'Cannot set Schema on Server: Different schema is already set');
        Server::create()
            ->setSchema($schema)
            ->setSchema(new Schema(['query' => $queryType]));
        self::fail('Expected exception not thrown');
    }

    public function testSchemaDefinition():void
    {
        $mutationType = $queryType = $subscriptionType = new ObjectType(['name' => 'A', 'fields' => ['a' => GraphQlType::string()]]);
        $schema = new Schema([
            'query' => $queryType,
        ]);

        $server = Server::create()
            ->setSchema($schema);

        expect($server->getSchema())->toBeSame($schema);

        $server = Server::create()
            ->setQueryType($queryType);
        expect($server->getQueryType())->toBeSame($queryType);
        expect($server->getSchema()->getQueryType())->toBeSame($queryType);

        $server = Server::create()
            ->setQueryType($queryType)
            ->setMutationType($mutationType);

        expect($server->getMutationType())->toBeSame($mutationType);
        expect($server->getSchema()->getMutationType())->toBeSame($mutationType);

        $server = Server::create()
            ->setQueryType($queryType)
            ->setSubscriptionType($subscriptionType);

        expect($server->getSubscriptionType())->toBeSame($subscriptionType);
        expect($server->getSchema()->getSubscriptionType())->toBeSame($subscriptionType);

        $server = Server::create()
            ->setQueryType($queryType)
            ->addTypes($types = [$queryType, $subscriptionType]);

        expect($server->getTypes())->toBeSame($types);
        $server->addTypes([$mutationType]);
        expect($server->getTypes())->toBeSame(\array_merge($types, [$mutationType]));

        $server = Server::create()
            ->setDirectives($directives = []);

        expect($server->getDirectives())->toBeSame($directives);
    }

    public function testParse():void
    {
        $server = Server::create();
        $ast = $server->parse('{q}');
        expect($ast)->toBeInstanceOf(\GraphQL\Language\AST\DocumentNode::class);

        try
        {
            $server->parse('{q');
        }
        catch(SyntaxError $e)
        {
            expect($e->getMessage())->toMatchRegExp('/' . \preg_quote('{q', '/') . '/');
            return;
        }
        self::fail('Expected exception not thrown');
    }

    public function testValidate():void
    {
        $server = Server::create()
            ->setQueryType(new ObjectType(['name' => 'Q', 'fields' => ['a' => GraphQlType::string()]]));

        $ast = $server->parse('{q}');
        $errors = $server->validate($ast);

        expect($errors)->toBeType('array');
        expect($errors)->toNotBeEmpty();

        $this->setExpectedException(InvariantViolation::class, 'Cannot validate, schema contains errors: Schema query must be Object Type but got: NULL');
        $server = Server::create();
        $server->validate($ast);
    }

    public function testPromiseAdapter():void
    {
        $adapter1 = new SyncPromiseAdapter();
        $adapter2 = new SyncPromiseAdapter();

        $server = Server::create()
            ->setPromiseAdapter($adapter1);

        expect($server->getPromiseAdapter())->toBeSame($adapter1);
        $server->setPromiseAdapter($adapter1);

        $this->setExpectedException(InvariantViolation::class, 'Cannot set promise adapter: Different adapter is already set');
        $server->setPromiseAdapter($adapter2);
    }

    public function testValidationRules():void
    {
        $rules = [];
        $server = Server::create()
            ->setValidationRules($rules);

        expect($server->getValidationRules())->toBeSame($rules);
    }

    public function testExecuteQuery():void
    {
        $called = false;
        $queryType = new ObjectType([
            'name' => 'Q',
            'fields' => [
                'field' => [
                    'type' => GraphQlType::string(),
                    'resolve' => function($value, $args, $context, ResolveInfo $info) use (&$called) {
                        $called = true;
                        expect($context)->toBePHPEqual(null);
                        expect($value)->toBePHPEqual(null);
                        expect($info->rootValue)->toBePHPEqual(null);
                        return 'ok';
                    }
                ]
            ]
        ]);

        $server = Server::create()
            ->setQueryType($queryType);

        $result = $server->executeQuery('{field}');
        expect($called)->toBePHPEqual(true);
        expect($result)->toBeInstanceOf(\GraphQL\Executor\ExecutionResult::class);
        expect($result->toArray())->toBePHPEqual(['data' => ['field' => 'ok']]);

        $called = false;
        $contextValue = new \stdClass();
        $rootValue = new \stdClass();

        $queryType = new ObjectType([
            'name' => 'QueryType',
            'fields' => [
                'field' => [
                    'type' => GraphQlType::string(),
                    'resolve' => function($value, $args, $context, ResolveInfo $info) use (&$called, $contextValue, $rootValue) {
                        $called = true;
                        expect($value)->toBeSame($rootValue);
                        expect($context)->toBeSame($contextValue);
                        expect($info->rootValue)->toBePHPEqual($rootValue);
                        return 'ok';
                    }
                ]
            ]
        ]);

        $server = Server::create()
            ->setQueryType($queryType)
            ->setRootValue($rootValue)
            ->setContext($contextValue);

        $result = $server->executeQuery('{field}');
        expect($called)->toBePHPEqual(true);
        expect($result)->toBeInstanceOf(\GraphQL\Executor\ExecutionResult::class);
        expect($result->toArray())->toBePHPEqual(['data' => ['field' => 'ok']]);
    }

    public function testDebugPhpErrors():void
    {
        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'err' => [
                    'type' => GraphQlType::string(),
                    'resolve' => function() {
                        \trigger_error('notice', \E_USER_NOTICE);
                        return 'err';
                    }
                ]
            ]
        ]);

        $server = Server::create()
            ->setDebug(0)
            ->setQueryType($queryType);

        $result = @$server->executeQuery('{err}');

        $expected = [
            'data' => ['err' => 'err']
        ];
        expect($result->toArray())->toBePHPEqual($expected);

        $server->setDebug(Server::DEBUG_PHP_ERRORS);
        $result = @$server->executeQuery('{err}');

        $expected = [
            'data' => ['err' => 'err'],
            'extensions' => [
                'phpErrors' => [
                    [
                        'message' => 'notice',
                        'severity' => 1024,
                        // 'trace' => [...]
                    ]
                ]
            ]
        ];

        expect($result->toArray())->toInclude($expected);

        $server->setPhpErrorFormatter(function(\ErrorException $e) {
            return ['test' => $e->getMessage()];
        });

        $result = $server->executeQuery('{err}');
        $expected = [
            'data' => ['err' => 'err'],
            'extensions' => [
                'phpErrors' => [
                    [
                        'test' => 'notice'
                    ]
                ]
            ]
        ];
        expect($result->toArray())->toBePHPEqual($expected);
    }

    public function testDebugExceptions():void
    {
        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'withException' => [
                    'type' => GraphQlType::string(),
                    'resolve' => function() {
                        throw new UserError("Error");
                    }
                ]
            ]
        ]);

        $server = Server::create()
            ->setDebug(0)
            ->setQueryType($queryType);

        $result = $server->executeQuery('{withException}');
        $expected = [
            'data' => [
                'withException' => null
            ],
            'errors' => [[
                'message' => 'Error',
                'path' => ['withException'],
                'locations' => [[
                    'line' => 1,
                    'column' => 2
                ]],
            ]]
        ];
        expect($result->toArray())->toInclude($expected);

        $server->setDebug(Server::DEBUG_EXCEPTIONS);
        $server->setExceptionFormatter(function($e) {
            $debug = Debug::INCLUDE_TRACE;
            return FormattedError::createFromException($e, $debug);
        });
        $result = $server->executeQuery('{withException}');

        $expected['errors'][0]['exception'] = ['message' => 'Error', 'trace' => []];
        expect($result->toArray())->toInclude($expected);

        $server->setExceptionFormatter(function(\Exception $e) {
            return ['test' => $e->getMessage()];
        });

        $result = $server->executeQuery('{withException}');
        $expected['errors'][0]['exception'] = ['test' => 'Error'];
        expect($result->toArray())->toInclude($expected);
    }

    /*public function testHandleRequest():void
    {
        $mock = $this->getMockBuilder('GraphQL\Server')
            ->setMethods(['readInput', 'produceOutput'])
            ->getMock();

        $mock->method('readInput')
            ->will($this->returnValue(\json_encode(['query' => '{err}'])));

        $output = null;
        $mock->method('produceOutput')
            ->will($this->returnCallback(function($a1, $a2) use (&$output) {
                $output = \func_get_args();
            }));

        // @var $mock Server
        $mock->handleRequest();

        expect($output)->toBeType('array');
        if($output !== null && is_array($output))
        {
            expect($output[0])->toInclude(['errors' => [['message' => 'Unexpected Error']]]);
            expect($output[1])->toBePHPEqual(500);
        }

        $output = null;
        $mock->setUnexpectedErrorMessage($newErr = 'Hey! Something went wrong!');
        $mock->setUnexpectedErrorStatus(501);
        $mock->handleRequest();

        expect($output)->toBeType('array');
        if($output !== null && is_array($output))
        {
            expect($output[0])->toBePHPEqual(['errors' => [['message' => $newErr]]]);
            expect($output[1])->toBePHPEqual(501);
        }

        $mock->setQueryType(new ObjectType([
            'name' => 'Query',
            'fields' => [
                'test' => [
                    'type' => GraphQlType::string(),
                    'resolve' => function() {
                        return 'ok';
                    }
                ]
            ]
        ]));

        $_REQUEST = ['query' => '{err}'];
        $output = null;
        $mock->handleRequest();
        expect($output)->toBeType('array');

        $expectedOutput = [
            ['errors' => [[
                'message' => 'Cannot query field "err" on type "Query".',
                'locations' => [[
                    'line' => 1,
                    'column' => 2
                ]],
                'category' => 'graphql',
            ]]],
            200
        ];

        expect($output)->toBePHPEqual($expectedOutput);

        $output = null;
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_REQUEST = [];
        $mock->handleRequest();

        expect($output)->toBeType('array');
        expect($output)->toBePHPEqual($expectedOutput);
    }*/
}
