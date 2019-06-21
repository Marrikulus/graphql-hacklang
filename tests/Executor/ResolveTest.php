<?hh //strict
//decl
namespace GraphQL\Tests\Executor;

use GraphQL\GraphQL;
use function Facebook\FBExpect\expect;
use GraphQL\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;

require_once __DIR__ . '/TestClasses.php';

class ResolveTest extends \Facebook\HackTest\HackTest
{
    // Execute: resolve function

    private function buildSchema($testField)
    {
        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'test' => $testField
                ]
            ])
        ]);
    }

    /**
     * @it default function accesses properties
     */
    public function testDefaultFunctionAccessesProperties():void
    {
        $schema = $this->buildSchema(['type' => GraphQlType::string()]);

        $source = [
            'test' => 'testValue'
        ];

        expect(GraphQL::execute($schema, '{ test }', $source))
            ->toBePHPEqual(['data' => ['test' => 'testValue']]);
    }

    /**
     * @it default function calls methods
     */
    public function testDefaultFunctionCallsClosures():void
    {
        $schema = $this->buildSchema(['type' => GraphQlType::string()]);
        $_secret = 'secretValue' . \uniqid();

        $source = [
            'test' => function() use ($_secret) {
                return $_secret;
            }
        ];
        expect(GraphQL::execute($schema, '{ test }', $source))
            ->toBePHPEqual(['data' => ['test' => $_secret]]);
    }

    /**
     * @it default function passes args and context
     */
    public function testDefaultFunctionPassesArgsAndContext():void
    {
        $schema = $this->buildSchema([
            'type' => GraphQlType::int(),
            'args' => [
                'addend1' => [ 'type' => GraphQlType::int() ],
            ],
        ]);

        $source = new Adder(700);

        $result = GraphQL::execute($schema, '{ test(addend1: 80) }', $source, ['addend2' => 9]);
        expect($result)->toBePHPEqual(['data' => ['test' => 789]]);
    }

    /**
     * @it uses provided resolve function
     */
    public function testUsesProvidedResolveFunction():void
    {
        $schema = $this->buildSchema([
            'type' => GraphQlType::string(),
            'args' => [
                'aStr' => ['type' => GraphQlType::string()],
                'aInt' => ['type' => GraphQlType::int()],
            ],
            'resolve' => function ($source, $args) {
                return \json_encode([$source, $args]);
            }
        ]);

        expect(GraphQL::execute($schema, '{ test }'))->toBePHPEqual(['data' => ['test' => '[null,[]]']]);

        expect(GraphQL::execute($schema, '{ test }', 'Source!'))
            ->toBePHPEqual(['data' => ['test' => '["Source!",[]]']]);

        expect(GraphQL::execute($schema, '{ test(aStr: "String!") }', 'Source!'))
            ->toBePHPEqual(['data' => ['test' => '["Source!",{"aStr":"String!"}]']]);

        expect(GraphQL::execute($schema, '{ test(aInt: -123, aStr: "String!") }', 'Source!'))
            ->toBePHPEqual(['data' => ['test' => '["Source!",{"aStr":"String!","aInt":-123}]']]);
    }
}
