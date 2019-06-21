<?hh //strict
//decl
namespace GraphQL\Tests\Server;


use GraphQL\Deferred;
use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;
use GraphQL\Type\Schema;

abstract class TestCase extends \Facebook\HackTest\HackTest
{
    protected function buildSchema()
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'f1' => [
                        'type' => GraphQlType::string(),
                        'resolve' => function($root, $args, $context, $info) {
                            return $info->fieldName;
                        }
                    ],
                    'fieldWithPhpError' => [
                        'type' => GraphQlType::string(),
                        'resolve' => function($root, $args, $context, $info) {
                            \trigger_error('deprecated', \E_USER_DEPRECATED);
                            \trigger_error('notice', \E_USER_NOTICE);
                            \trigger_error('warning', \E_USER_WARNING);
                            $a = [];
                            $a['test']; // should produce PHP notice
                            return $info->fieldName;
                        }
                    ],
                    'fieldWithException' => [
                        'type' => GraphQlType::string(),
                        'resolve' => function($root, $args, $context, $info) {
                            throw new UserError("This is the exception we want");
                        }
                    ],
                    'testContextAndRootValue' => [
                        'type' => GraphQlType::string(),
                        'resolve' => function($root, $args, $context, $info) {
                            $context->testedRootValue = $root;
                            return $info->fieldName;
                        }
                    ],
                    'fieldWithArg' => [
                        'type' => GraphQlType::string(),
                        'args' => [
                            'arg' => [
                                'type' => GraphQlType::nonNull(GraphQlType::string())
                            ],
                        ],
                        'resolve' => function($root, $args) {
                            return $args['arg'];
                        }
                    ],
                    'dfd' => [
                        'type' => GraphQlType::string(),
                        'args' => [
                            'num' => [
                                'type' => GraphQlType::nonNull(GraphQlType::int())
                            ],
                        ],
                        'resolve' => function($root, $args, $context) {
                            $context['buffer']($args['num']);

                            return new Deferred(function() use ($args, $context) {
                                return $context['load']($args['num']);
                            });
                        }
                    ]
                ]
            ]),
            'mutation' => new ObjectType([
                'name' => 'Mutation',
                'fields' => [
                    'm1' => [
                        'type' => new ObjectType([
                            'name' => 'TestMutation',
                            'fields' => [
                                'result' => GraphQlType::string()
                            ]
                        ])
                    ]
                ]
            ])
        ]);
        return $schema;
    }
}
