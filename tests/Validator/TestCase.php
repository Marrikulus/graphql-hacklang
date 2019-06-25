<?hh //strict
namespace GraphQL\Tests\Validator;

use GraphQL\GraphQL;
use function Facebook\FBExpect\expect;
use GraphQL\Error\Error;
use GraphQL\Language\Lexer;
use GraphQL\Language\Parser;
use GraphQL\Schema;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\AbstractValidationRule;

abstract class TestCase extends \Facebook\HackTest\HackTest
{
    /**
     * @return Schema
     */
    public static function getDefaultSchema():Schema
    {
        $FurColor = null;

        $Being = new InterfaceType([
            'name' => 'Being',
            'fields' => [
                'name' => [
                    'type' => GraphQlType::string(),
                    'args' => [ 'surname' => [ 'type' => GraphQlType::boolean() ] ]
                ]
            ],
        ]);

        $Pet = new InterfaceType([
            'name' => 'Pet',
            'fields' => [
                'name' => [
                    'type' => GraphQlType::string(),
                    'args' => [ 'surname' => [ 'type' => GraphQlType::boolean() ] ]
                ]
            ],
        ]);

        $Canine = new InterfaceType([
            'name' => 'Canine',
            'fields' => function() {
                return [
                    'name' => [
                        'type' => GraphQlType::string(),
                        'args' => ['surname' => ['type' => GraphQlType::boolean()]]
                    ]
                ];
            }
        ]);

        $DogCommand = new EnumType([
            'name' => 'DogCommand',
            'values' => [
                'SIT' => ['value' => 0],
                'HEEL' => ['value' => 1],
                'DOWN' => ['value' => 2]
            ]
        ]);

        $Dog = new ObjectType([
            'name' => 'Dog',
            'isTypeOf' => function() {return true;},
            'fields' => [
                'name' => [
                    'type' => GraphQlType::string(),
                    'args' => [ 'surname' => [ 'type' => GraphQlType::boolean() ] ]
                ],
                'nickname' => ['type' => GraphQlType::string()],
                'barkVolume' => ['type' => GraphQlType::int()],
                'barks' => ['type' => GraphQlType::boolean()],
                'doesKnowCommand' => [
                    'type' => GraphQlType::boolean(),
                    'args' => ['dogCommand' => ['type' => $DogCommand]]
                ],
                'isHousetrained' => [
                    'type' => GraphQlType::boolean(),
                    'args' => ['atOtherHomes' => ['type' => GraphQlType::boolean(), 'defaultValue' => true]]
                ],
                'isAtLocation' => [
                    'type' => GraphQlType::boolean(),
                    'args' => ['x' => ['type' => GraphQlType::int()], 'y' => ['type' => GraphQlType::int()]]
                ]
            ],
            'interfaces' => [$Being, $Pet, $Canine]
        ]);

        $Cat = new ObjectType([
            'name' => 'Cat',
            'isTypeOf' => function() {return true;},
            'fields' => function() use (&$FurColor) {
                return [
                    'name' => [
                        'type' => GraphQlType::string(),
                        'args' => [ 'surname' => [ 'type' => GraphQlType::boolean() ] ]
                    ],
                    'nickname' => ['type' => GraphQlType::string()],
                    'meows' => ['type' => GraphQlType::boolean()],
                    'meowVolume' => ['type' => GraphQlType::int()],
                    'furColor' => $FurColor
                ];
            },
            'interfaces' => [$Being, $Pet]
        ]);

        $CatOrDog = new UnionType([
            'name' => 'CatOrDog',
            'types' => [$Dog, $Cat],
            'resolveType' => function($value) {
                // not used for validation
                return null;
            }
        ]);

        $Intelligent = new InterfaceType([
            'name' => 'Intelligent',
            'fields' => [
                'iq' => ['type' => GraphQlType::int()]
            ]
        ]);

        $Human = null;
        $Human = new ObjectType([
            'name' => 'Human',
            'isTypeOf' => function() {return true;},
            'interfaces' => [$Being, $Intelligent],
            'fields' => function() use (&$Human, $Pet) {
                return [
                    'name' => [
                        'type' => GraphQlType::string(),
                        'args' => ['surname' => ['type' => GraphQlType::boolean()]]
                    ],
                    'pets' => ['type' => GraphQlType::listOf($Pet)],
                    'relatives' => ['type' => GraphQlType::listOf($Human)],
                    'iq' => ['type' => GraphQlType::int()]
                ];
            }
        ]);

        $Alien = new ObjectType([
            'name' => 'Alien',
            'isTypeOf' => function() {return true;},
            'interfaces' => [$Being, $Intelligent],
            'fields' => [
                'iq' => ['type' => GraphQlType::int()],
                'name' => [
                    'type' => GraphQlType::string(),
                    'args' => ['surname' => ['type' => GraphQlType::boolean()]]
                ],
                'numEyes' => ['type' => GraphQlType::int()]
            ]
        ]);

        $DogOrHuman = new UnionType([
            'name' => 'DogOrHuman',
            'types' => [$Dog, $Human],
            'resolveType' => function() {
                // not used for validation
                return null;
            }
        ]);

        $HumanOrAlien = new UnionType([
            'name' => 'HumanOrAlien',
            'types' => [$Human, $Alien],
            'resolveType' => function() {
                // not used for validation
                return null;
            }
        ]);

        $FurColor = new EnumType([
            'name' => 'FurColor',
            'values' => [
                'BROWN' => [ 'value' => 0 ],
                'BLACK' => [ 'value' => 1 ],
                'TAN' => [ 'value' => 2 ],
                'SPOTTED' => [ 'value' => 3 ],
                'NO_FUR' => [ 'value' => null ],
            ],
        ]);

        $ComplexInput = new InputObjectType([
            'name' => 'ComplexInput',
            'fields' => [
                'requiredField' => ['type' => GraphQlType::nonNull(GraphQlType::boolean())],
                'intField' => ['type' => GraphQlType::int()],
                'stringField' => ['type' => GraphQlType::string()],
                'booleanField' => ['type' => GraphQlType::boolean()],
                'stringListField' => ['type' => GraphQlType::listOf(GraphQlType::string())]
            ]
        ]);

        $ComplicatedArgs = new ObjectType([
            'name' => 'ComplicatedArgs',
            // TODO List
            // TODO Coercion
            // TODO NotNulls
            'fields' => [
                'intArgField' => [
                    'type' => GraphQlType::string(),
                    'args' => ['intArg' => ['type' => GraphQlType::int()]],
                ],
                'nonNullIntArgField' => [
                    'type' => GraphQlType::string(),
                    'args' => [ 'nonNullIntArg' => [ 'type' => GraphQlType::nonNull(GraphQlType::int())]],
                ],
                'stringArgField' => [
                    'type' => GraphQlType::string(),
                    'args' => [ 'stringArg' => [ 'type' => GraphQlType::string()]],
                ],
                'booleanArgField' => [
                    'type' => GraphQlType::string(),
                    'args' => ['booleanArg' => [ 'type' => GraphQlType::boolean() ]],
                ],
                'enumArgField' => [
                    'type' => GraphQlType::string(),
                    'args' => [ 'enumArg' => ['type' => $FurColor ]],
                ],
                'floatArgField' => [
                    'type' => GraphQlType::string(),
                    'args' => [ 'floatArg' => [ 'type' => GraphQlType::float()]],
                ],
                'idArgField' => [
                    'type' => GraphQlType::string(),
                    'args' => [ 'idArg' => [ 'type' => GraphQlType::id() ]],
                ],
                'stringListArgField' => [
                    'type' => GraphQlType::string(),
                    'args' => [ 'stringListArg' => [ 'type' => GraphQlType::listOf(GraphQlType::string())]],
                ],
                'complexArgField' => [
                    'type' => GraphQlType::string(),
                    'args' => [ 'complexArg' => [ 'type' => $ComplexInput ]],
                ],
                'multipleReqs' => [
                    'type' => GraphQlType::string(),
                    'args' => [
                        'req1' => [ 'type' => GraphQlType::nonNull(GraphQlType::int())],
                        'req2' => [ 'type' => GraphQlType::nonNull(GraphQlType::int())],
                    ],
                ],
                'multipleOpts' => [
                    'type' => GraphQlType::string(),
                    'args' => [
                        'opt1' => [
                            'type' => GraphQlType::int(),
                            'defaultValue' => 0,
                        ],
                        'opt2' => [
                            'type' => GraphQlType::int(),
                            'defaultValue' => 0,
                        ],
                    ],
                ],
                'multipleOptAndReq' => [
                    'type' => GraphQlType::string(),
                    'args' => [
                        'req1' => [ 'type' => GraphQlType::nonNull(GraphQlType::int())],
                        'req2' => [ 'type' => GraphQlType::nonNull(GraphQlType::int())],
                        'opt1' => [
                            'type' => GraphQlType::int(),
                            'defaultValue' => 0,
                        ],
                        'opt2' => [
                            'type' => GraphQlType::int(),
                            'defaultValue' => 0,
                        ],
                    ],
                ],
            ]
        ]);

        $queryRoot = new ObjectType([
            'name' => 'QueryRoot',
            'fields' => [
                'human' => [
                    'args' => ['id' => ['type' => GraphQlType::id()]],
                    'type' => $Human
                ],
                'alien' => ['type' => $Alien],
                'dog' => ['type' => $Dog],
                'cat' => ['type' => $Cat],
                'pet' => ['type' => $Pet],
                'catOrDog' => ['type' => $CatOrDog],
                'dogOrHuman' => ['type' => $DogOrHuman],
                'humanOrAlien' => ['type' => $HumanOrAlien],
                'complicatedArgs' => ['type' => $ComplicatedArgs]
            ]
        ]);

        $defaultSchema = new Schema([
            'query' => $queryRoot,
            'directives' => \array_merge(GraphQL::getInternalDirectives(), [
                new Directive([
                    'name' => 'operationOnly',
                    'locations' => [ 'QUERY' ],
                ])
            ])
        ]);
        return $defaultSchema;
    }

    public function expectValid(Schema $schema, array<AbstractValidationRule> $rules, string $queryString):void
    {
        expect(DocumentValidator::validate($schema, Parser::parse($queryString), $rules))
            ->toBePHPEqual([],'Should validate');
    }

    public function expectInvalid(Schema $schema, array<AbstractValidationRule> $rules, string $queryString, array<array<string, mixed>> $expectedErrors):array<Error>
    {
        $errors = DocumentValidator::validate($schema, Parser::parse($queryString), $rules);

        expect($errors)->toNotBeEmpty('GraphQL should not validate');
        expect(\array_map( class_meth(Error::class, 'formatError'), $errors))->toBePHPEqual($expectedErrors);

        return $errors;
    }

    public function expectPassesRule(AbstractValidationRule $rule, string $queryString):void
    {
        $this->expectValid(TestCase::getDefaultSchema(), [$rule], $queryString);
    }

    public function expectFailsRule(AbstractValidationRule $rule, string $queryString, array<array<string, mixed>> $errors):array<Error>
    {
        return $this->expectInvalid(TestCase::getDefaultSchema(), [$rule], $queryString, $errors);
    }

    public function expectPassesRuleWithSchema(Schema $schema, AbstractValidationRule $rule, string $queryString):void
    {
        $this->expectValid($schema, [$rule], $queryString);
    }

    public function expectFailsRuleWithSchema(Schema $schema, AbstractValidationRule $rule, string $queryString, array<array<string, mixed>> $errors):void
    {
        $this->expectInvalid($schema, [$rule], $queryString, $errors);
    }

    public function expectPassesCompleteValidation(string $queryString):void
    {
        $this->expectValid(TestCase::getDefaultSchema(), DocumentValidator::allRules(), $queryString);
    }

    public function expectFailsCompleteValidation(string $queryString, array<array<string, mixed>> $errors):void
    {
        $this->expectInvalid(TestCase::getDefaultSchema(), DocumentValidator::allRules(), $queryString, $errors);
    }
}
