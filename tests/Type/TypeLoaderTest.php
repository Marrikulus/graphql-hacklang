<?hh //strict
//decl
namespace GraphQL\Tests\Type;


use GraphQL\Error\InvariantViolation;
use function Facebook\FBExpect\expect;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;
use GraphQL\Type\Schema;

class TypeLoaderTest extends \Facebook\HackTest\HackTest
{
    /**
     * @var ObjectType
     */
    private ?ObjectType $query;

    /**
     * @var ObjectType
     */
    private ?ObjectType $mutation;

    /**
     * @var InterfaceType
     */
    private ?InterfaceType $node;

    /**
     * @var InterfaceType
     */
    private ?InterfaceType $content;

    /**
     * @var ObjectType
     */
    private ?ObjectType $blogStory;

    /**
     * @var ObjectType
     */
    private ?ObjectType $postStoryMutation;

    /**
     * @var InputObjectType
     */
    private ?InputObjectType $postStoryMutationInput;

    /**
     * @var callable
     */
    private (function(string):mixed) $typeLoader;

    /**
     * @var array
     */
    private array<string> $calls = [];

    /*public async function beforeEachTestAsync(): Awaitable<void>
    {
        $this->node = new InterfaceType([
            'name' => 'Node',
            'fields' => function() {
                $this->calls[] = 'Node.fields';
                return [
                    'id' => GraphQlType::string()
                ];
            },
            'resolveType' => function() {}
        ]);

        $this->content = new InterfaceType([
            'name' => 'Content',
            'fields' => function() {
                $this->calls[] = 'Content.fields';
                return [
                    'title' => GraphQlType::string(),
                    'body' => GraphQlType::string(),
                ];
            },
            'resolveType' => function() {}
        ]);

        $this->blogStory = new ObjectType([
            'name' => 'BlogStory',
            'interfaces' => [
                $this->node,
                $this->content
            ],
            'fields' => function() {
                $this->calls[] = 'BlogStory.fields';
                return [
                    $this->node->getField('id'),
                    $this->content->getField('title'),
                    $this->content->getField('body'),
                ];
            },
        ]);

        $this->query = new ObjectType([
            'name' => 'Query',
            'fields' => function() {
                $this->calls[] = 'Query.fields';
                return [
                    'latestContent' => $this->content,
                    'node' => $this->node,
                ];
            }
        ]);

        $this->mutation = new ObjectType([
            'name' => 'Mutation',
            'fields' => function() {
                $this->calls[] = 'Mutation.fields';
                return [
                    'postStory' => [
                        'type' => $this->postStoryMutation,
                        'args' => [
                            'input' => GraphQlType::nonNull($this->postStoryMutationInput),
                            'clientRequestId' => GraphQlType::string()
                        ]
                    ]
                ];
            }
        ]);

        $this->postStoryMutation = new ObjectType([
            'name' => 'PostStoryMutation',
            'fields' => [
                'story' => $this->blogStory
            ]
        ]);

        $this->postStoryMutationInput = new InputObjectType([
            'name' => 'PostStoryMutationInput',
            'fields' => [
                'title' => GraphQlType::string(),
                'body' => GraphQlType::string(),
                'author' => GraphQlType::id(),
                'category' => GraphQlType::id()
            ]
        ]);

        $this->typeLoader = function(string $name):mixed {
            $this->calls[] = $name;
            $prop = \lcfirst($name);
            return isset($this->{$prop}) ? $this->{$prop} : null;
        };
    }

    public function testSchemaAcceptsTypeLoader():void
    {
        new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => ['a' => GraphQlType::string()]
            ]),
            'typeLoader' => function() {}
        ]);
    }

    public function testSchemaRejectsNonCallableTypeLoader():void
    {
        $this->setExpectedException(
            InvariantViolation::class,
            'Schema type loader must be callable if provided but got: array(0)'
        );

        new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => ['a' => GraphQlType::string()]
            ]),
            'typeLoader' => []
        ]);
    }

    public function testWorksWithoutTypeLoader():void
    {
        $schema = new Schema([
            'query' => $this->query,
            'mutation' => $this->mutation,
            'types' => [$this->blogStory]
        ]);

        $expected = [
            'Query.fields',
            'Content.fields',
            'Node.fields',
            'Mutation.fields',
            'BlogStory.fields',
        ];
        expect($this->calls)->toBePHPEqual($expected);

        expect($schema->getType('Query'))->toBeSame($this->query);
        expect($schema->getType('Mutation'))->toBeSame($this->mutation);
        expect($schema->getType('Node'))->toBeSame($this->node);
        expect($schema->getType('Content'))->toBeSame($this->content);
        expect($schema->getType('BlogStory'))->toBeSame($this->blogStory);
        expect($schema->getType('PostStoryMutation'))->toBeSame($this->postStoryMutation);
        expect($schema->getType('PostStoryMutationInput'))->toBeSame($this->postStoryMutationInput);

        $expectedTypeMap = [
            'Query' => $this->query,
            'Mutation' => $this->mutation,
            'Node' => $this->node,
            'String' => GraphQlType::string(),
            'Content' => $this->content,
            'BlogStory' => $this->blogStory,
            'PostStoryMutationInput' => $this->postStoryMutationInput,
        ];

        expect($schema->getTypeMap())->toInclude($expectedTypeMap);
    }

    public function testWorksWithTypeLoader():void
    {
        $schema = new Schema([
            'query' => $this->query,
            'mutation' => $this->mutation,
            'typeLoader' => $this->typeLoader
        ]);
        expect($this->calls)->toBePHPEqual([]);

        $node = $schema->getType('Node');
        expect($node)->toBeSame($this->node);
        expect($this->calls)->toBePHPEqual(['Node']);

        $content = $schema->getType('Content');
        expect($content)->toBeSame($this->content);
        expect($this->calls)->toBePHPEqual(['Node', 'Content']);

        $input = $schema->getType('PostStoryMutationInput');
        expect($input)->toBeSame($this->postStoryMutationInput);
        expect($this->calls)->toBePHPEqual(['Node', 'Content', 'PostStoryMutationInput']);

        $result = $schema->isPossibleType($this->node, $this->blogStory);
        expect($result)->toBeTrue();
        expect($this->calls)->toBePHPEqual(['Node', 'Content', 'PostStoryMutationInput']);
    }

    public function testOnlyCallsLoaderOnce():void
    {
        $schema = new Schema([
            'query' => $this->query,
            'typeLoader' => $this->typeLoader
        ]);

        $schema->getType('Node');
        expect($this->calls)->toBePHPEqual(['Node']);

        $schema->getType('Node');
        expect($this->calls)->toBePHPEqual(['Node']);
    }

    public function testFailsOnNonExistentType():void
    {
        $schema = new Schema([
            'query' => $this->query,
            'typeLoader' => function() {}
        ]);

        $this->setExpectedException(
            InvariantViolation::class,
            'Type loader is expected to return valid type "NonExistingType", but it returned null'
        );

        $schema->getType('NonExistingType');
    }

    public function testFailsOnNonType():void
    {
        $schema = new Schema([
            'query' => $this->query,
            'typeLoader' => function() {
                return new \stdClass();
            }
        ]);

        $this->setExpectedException(
            InvariantViolation::class,
            'Type loader is expected to return valid type "Node", but it returned instance of stdClass'
        );

        $schema->getType('Node');
    }

    public function testFailsOnInvalidLoad():void
    {
        $schema = new Schema([
            'query' => $this->query,
            'typeLoader' => function() {
                return $this->content;
            }
        ]);

        $this->setExpectedException(
            InvariantViolation::class,
            'Type loader is expected to return type "Node", but it returned "Content"'
        );

        $schema->getType('Node');
    }

    public function testPassesThroughAnExceptionInLoader():void
    {
        $schema = new Schema([
            'query' => $this->query,
            'typeLoader' => function() {
                throw new \Exception("This is the exception we are looking for");
            }
        ]);

        $this->setExpectedException(
            \Exception::class,
            'This is the exception we are looking for'
        );

        $schema->getType('Node');
    }

    public function testReturnsIdenticalResults():void
    {
        $withoutLoader = new Schema([
            'query' => $this->query,
            'mutation' => $this->mutation
        ]);

        $withLoader = new Schema([
            'query' => $this->query,
            'mutation' => $this->mutation,
            'typeLoader' => $this->typeLoader
        ]);

        expect($withLoader->getQueryType())->toBeSame($withoutLoader->getQueryType());
        expect($withLoader->getMutationType())->toBeSame($withoutLoader->getMutationType());
        expect($withLoader->getType('BlogStory'))->toBeSame($withoutLoader->getType('BlogStory'));
        expect($withLoader->getDirectives())->toBeSame($withoutLoader->getDirectives());
    }

    public function testSkipsLoaderForInternalTypes():void
    {
        $schema = new Schema([
            'query' => $this->query,
            'mutation' => $this->mutation,
            'typeLoader' => $this->typeLoader
        ]);

        $type = $schema->getType('ID');
        expect($type)->toBeSame(GraphQlType::id());
        expect($this->calls)->toBePHPEqual([]);
    }*/
}
