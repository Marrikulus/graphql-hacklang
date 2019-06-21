<?hh //strict
//decl
namespace GraphQL\Tests\Server;

use GraphQL\Error\InvariantViolation;
use function Facebook\FBExpect\expect;
use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Type\Schema;
use GraphQL\Server\ServerConfig;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;

class ServerConfigTest extends \Facebook\HackTest\HackTest
{
    public function testDefaults():void
    {
        $config = ServerConfig::create();
        expect($config->getSchema())->toBePHPEqual(null);
        expect($config->getContext())->toBePHPEqual(null);
        expect($config->getRootValue())->toBePHPEqual(null);
        expect($config->getErrorFormatter())->toBePHPEqual(null);
        expect($config->getErrorsHandler())->toBePHPEqual(null);
        expect($config->getPromiseAdapter())->toBePHPEqual(null);
        expect($config->getValidationRules())->toBePHPEqual(null);
        expect($config->getFieldResolver())->toBePHPEqual(null);
        expect($config->getPersistentQueryLoader())->toBePHPEqual(null);
        expect($config->getDebug())->toBePHPEqual(false);
        expect($config->getQueryBatching())->toBePHPEqual(false);
    }

    public function testAllowsSettingSchema():void
    {
        $schema = new Schema(['query' => new ObjectType(['name' => 'a', 'fields' => []])]);
        $config = ServerConfig::create()
            ->setSchema($schema);

        expect($config->getSchema())->toBeSame($schema);

        $schema2 = new Schema(['query' => new ObjectType(['name' => 'a', 'fields' => []])]);
        $config->setSchema($schema2);
        expect($config->getSchema())->toBeSame($schema2);
    }

    public function testAllowsSettingContext():void
    {
        $config = ServerConfig::create();

        $context = [];
        $config->setContext($context);
        expect($config->getContext())->toBeSame($context);

        $context2 = new \stdClass();
        $config->setContext($context2);
        expect($config->getContext())->toBeSame($context2);
    }

    public function testAllowsSettingRootValue():void
    {
        $config = ServerConfig::create();

        $rootValue = [];
        $config->setRootValue($rootValue);
        expect($config->getRootValue())->toBeSame($rootValue);

        $context2 = new \stdClass();
        $config->setRootValue($context2);
        expect($config->getRootValue())->toBeSame($context2);
    }

    public function testAllowsSettingErrorFormatter():void
    {
        $config = ServerConfig::create();

        $formatter = function() {};
        $config->setErrorFormatter($formatter);
        expect($config->getErrorFormatter())->toBeSame($formatter);

        $formatter = 'date'; // test for callable
        $config->setErrorFormatter($formatter);
        expect($config->getErrorFormatter())->toBeSame($formatter);
    }

    public function testAllowsSettingErrorsHandler():void
    {
        $config = ServerConfig::create();

        $handler = function() {};
        $config->setErrorsHandler($handler);
        expect($config->getErrorsHandler())->toBeSame($handler);

        $handler = 'date'; // test for callable
        $config->setErrorsHandler($handler);
        expect($config->getErrorsHandler())->toBeSame($handler);
    }

    public function testAllowsSettingPromiseAdapter():void
    {
        $config = ServerConfig::create();

        $adapter1 = new SyncPromiseAdapter();
        $config->setPromiseAdapter($adapter1);
        expect($config->getPromiseAdapter())->toBeSame($adapter1);

        $adapter2 = new SyncPromiseAdapter();
        $config->setPromiseAdapter($adapter2);
        expect($config->getPromiseAdapter())->toBeSame($adapter2);
    }

    public function testAllowsSettingValidationRules():void
    {
        $config = ServerConfig::create();

        $rules = [];
        $config->setValidationRules($rules);
        expect($config->getValidationRules())->toBeSame($rules);

        $rules = [function() {}];
        $config->setValidationRules($rules);
        expect($config->getValidationRules())->toBeSame($rules);

        $rules = function() {return [function() {}];};
        $config->setValidationRules($rules);
        expect($config->getValidationRules())->toBeSame($rules);
    }

    public function testAllowsSettingDefaultFieldResolver():void
    {
        $config = ServerConfig::create();

        $resolver = function() {};
        $config->setFieldResolver($resolver);
        expect($config->getFieldResolver())->toBeSame($resolver);

        $resolver = 'date'; // test for callable
        $config->setFieldResolver($resolver);
        expect($config->getFieldResolver())->toBeSame($resolver);
    }

    public function testAllowsSettingPersistedQueryLoader():void
    {
        $config = ServerConfig::create();

        $loader = function() {};
        $config->setPersistentQueryLoader($loader);
        expect($config->getPersistentQueryLoader())->toBeSame($loader);

        $loader = 'date'; // test for callable
        $config->setPersistentQueryLoader($loader);
        expect($config->getPersistentQueryLoader())->toBeSame($loader);
    }

    public function testAllowsSettingCatchPhpErrors():void
    {
        $config = ServerConfig::create();

        $config->setDebug(true);
        expect($config->getDebug())->toBeSame(true);

        $config->setDebug(false);
        expect($config->getDebug())->toBeSame(false);
    }

    public function testAcceptsArray():void
    {
        $arr = [
            'schema' => new \GraphQL\Type\Schema([
                'query' => new ObjectType(['name' => 't', 'fields' => ['a' => GraphQlType::string()]])
            ]),
            'context' => new \stdClass(),
            'rootValue' => new \stdClass(),
            'errorFormatter' => function() {},
            'promiseAdapter' => new SyncPromiseAdapter(),
            'validationRules' => [function() {}],
            'fieldResolver' => function() {},
            'persistentQueryLoader' => function() {},
            'debug' => true,
            'queryBatching' => true,
        ];

        $config = ServerConfig::create($arr);

        expect($config->getSchema())->toBeSame($arr['schema']);
        expect($config->getContext())->toBeSame($arr['context']);
        expect($config->getRootValue())->toBeSame($arr['rootValue']);
        expect($config->getErrorFormatter())->toBeSame($arr['errorFormatter']);
        expect($config->getPromiseAdapter())->toBeSame($arr['promiseAdapter']);
        expect($config->getValidationRules())->toBeSame($arr['validationRules']);
        expect($config->getFieldResolver())->toBeSame($arr['fieldResolver']);
        expect($config->getPersistentQueryLoader())->toBeSame($arr['persistentQueryLoader']);
        expect($config->getDebug())->toBeSame(true);
        expect($config->getQueryBatching())->toBeSame(true);
    }

    public function testThrowsOnInvalidArrayKey():void
    {
        $arr = [
            'missingKey' => 'value'
        ];

        $this->setExpectedException(
            InvariantViolation::class,
            'Unknown server config option "missingKey"'
        );

        ServerConfig::create($arr);
    }

    public function testInvalidValidationRules():void
    {
        $rules = new \stdClass();
        $config = ServerConfig::create();

        $this->setExpectedException(
            InvariantViolation::class,
            'Server config expects array of validation rules or callable returning such array, but got instance of stdClass'
        );

        $config->setValidationRules($rules);
    }
}
