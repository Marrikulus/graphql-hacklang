<?hh //strict
//decl
namespace GraphQL\Tests\Validator;

use GraphQL\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;

class QuerySecuritySchema
{
    private static ?Schema $schema;

    private static ?ObjectType $dogType;

    private static ?ObjectType $humanType;

    private static ?ObjectType $queryRootType;

    /**
     * @return Schema
     */
    public static function buildSchema():Schema
    {
        if (null !== self::$schema) {
            return self::$schema;
        }

        self::$schema = new Schema([
            'query' => static::buildQueryRootType()
        ]);

        return self::$schema;
    }

    public static function buildQueryRootType():ObjectType
    {
        if (null !== self::$queryRootType) {
            return self::$queryRootType;
        }

        self::$queryRootType = new ObjectType([
            'name' => 'QueryRoot',
            'fields' => [
                'human' => [
                    'type' => self::buildHumanType(),
                    'args' => ['name' => ['type' => GraphQlType::string()]],
                ],
            ],
        ]);

        return self::$queryRootType;
    }

    public static function buildHumanType():ObjectType
    {
        if (null !== self::$humanType) {
            return self::$humanType;
        }

        self::$humanType = new ObjectType(
            [
                'name' => 'Human',
                'fields' => function() {
                    return [
                        'firstName' => ['type' => GraphQlType::nonNull(GraphQlType::string())],
                        'dogs' => [
                            'type' => GraphQlType::nonNull(
                                GraphQlType::listOf(
                                    GraphQlType::nonNull(self::buildDogType())
                                )
                            ),
                            'complexity' => function ($childrenComplexity, $args) {
                                $complexity = \array_key_exists('name', $args)? 1 : 10;

                                return $childrenComplexity + $complexity;
                            },
                            'args' => ['name' => ['type' => GraphQlType::string()]],
                        ],
                    ];
                },
            ]
        );

        return self::$humanType;
    }

    public static function buildDogType():ObjectType
    {
        if (null !== self::$dogType) {
            return self::$dogType;
        }

        self::$dogType = new ObjectType(
            [
                'name' => 'Dog',
                'fields' => [
                    'name' => ['type' => GraphQlType::nonNull(GraphQlType::string())],
                    'master' => [
                        'type' => self::buildHumanType(),
                    ],
                ],
            ]
        );

        return self::$dogType;
    }
}
