<?hh //strict
//decl
namespace GraphQL\Tests\Type;

use GraphQL\Error\InvariantViolation;
use function Facebook\FBExpect\expect;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\EagerResolution;
use GraphQL\Type\LazyResolution;

class ResolutionTest extends \Facebook\HackTest\HackTest
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
    private ?ObjectType $link;

    /**
     * @var ObjectType
     */
    private ?ObjectType $video;

    /**
     * @var ObjectType
     */
    private ?ObjectType $videoMetadata;

    /**
     * @var ObjectType
     */
    private ?ObjectType $comment;

    /**
     * @var ObjectType
     */
    private ?ObjectType $user;

    /**
     * @var ObjectType
     */
    private ?ObjectType $category;

    /**
     * @var UnionType
     */
    private ?UnionType $mention;

    private ?ObjectType $postStoryMutation;

    private ?InputObjectType $postStoryMutationInput;

    private ?ObjectType $postCommentMutation;

    private ?InputObjectType $postCommentMutationInput;

    public async function beforeEachTestAsync(): Awaitable<void>
    {
        $this->node = new InterfaceType([
            'name' => 'Node',
            'fields' => [
                'id' => GraphQlType::string()
            ]
        ]);

        $this->content = new InterfaceType([
            'name' => 'Content',
            'fields' => function() {
                return [
                    'title' => GraphQlType::string(),
                    'body' => GraphQlType::string(),
                    'author' => $this->user,
                    'comments' => GraphQlType::listOf($this->comment),
                    'categories' => GraphQlType::listOf($this->category)
                ];
            }
        ]);

        $this->blogStory = new ObjectType([
            'name' => 'BlogStory',
            'interfaces' => [
                $this->node,
                $this->content
            ],
            'fields' => function() {
                return [
                    $this->node->getField('id'),
                    $this->content->getField('title'),
                    $this->content->getField('body'),
                    $this->content->getField('author'),
                    $this->content->getField('comments'),
                    $this->content->getField('categories')
                ];
            },
        ]);

        $this->link = new ObjectType([
            'name' => 'Link',
            'interfaces' => [
                $this->node,
                $this->content
            ],
            'fields' => function() {
                return [
                    $this->node->getField('id'),
                    $this->content->getField('title'),
                    $this->content->getField('body'),
                    $this->content->getField('author'),
                    $this->content->getField('comments'),
                    $this->content->getField('categories'),
                    'url' => GraphQlType::string()
                ];
            },
        ]);

        $this->video = new ObjectType([
            'name' => 'Video',
            'interfaces' => [
                $this->node,
                $this->content
            ],
            'fields' => function() {
                return [
                    $this->node->getField('id'),
                    $this->content->getField('title'),
                    $this->content->getField('body'),
                    $this->content->getField('author'),
                    $this->content->getField('comments'),
                    $this->content->getField('categories'),
                    'streamUrl' => GraphQlType::string(),
                    'downloadUrl' => GraphQlType::string(),
                    'metadata' => $this->videoMetadata = new ObjectType([
                        'name' => 'VideoMetadata',
                        'fields' => [
                            'lat' => GraphQlType::float(),
                            'lng' => GraphQlType::float()
                        ]
                    ])
                ];
            }
        ]);

        $this->comment = new ObjectType([
            'name' => 'Comment',
            'interfaces' => [
                $this->node
            ],
            'fields' => function() {
                return [
                    $this->node->getField('id'),
                    'author' => $this->user,
                    'text' => GraphQlType::string(),
                    'replies' => GraphQlType::listOf($this->comment),
                    'parent' => $this->comment,
                    'content' => $this->content
                ];
            }
        ]);

        $this->user = new ObjectType([
            'name' => 'User',
            'interfaces' => [
                $this->node
            ],
            'fields' => function() {
                return [
                    $this->node->getField('id'),
                    'name' => GraphQlType::string(),
                ];
            }
        ]);

        $this->category = new ObjectType([
            'name' => 'Category',
            'interfaces' => [
                $this->node
            ],
            'fields' => function() {
                return [
                    $this->node->getField('id'),
                    'name' => GraphQlType::string()
                ];
            }
        ]);

        $this->mention = new UnionType([
            'name' => 'Mention',
            'types' => [
                $this->user,
                $this->category
            ]
        ]);

        $this->query = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'viewer' => $this->user,
                'latestContent' => $this->content,
                'node' => $this->node,
                'mentions' => GraphQlType::listOf($this->mention)
            ]
        ]);

        $this->mutation = new ObjectType([
            'name' => 'Mutation',
            'fields' => [
                'postStory' => [
                    'type' => $this->postStoryMutation = new ObjectType([
                        'name' => 'PostStoryMutation',
                        'fields' => [
                            'story' => $this->blogStory
                        ]
                    ]),
                    'args' => [
                        'input' => GraphQlType::nonNull($this->postStoryMutationInput = new InputObjectType([
                            'name' => 'PostStoryMutationInput',
                            'fields' => [
                                'title' => GraphQlType::string(),
                                'body' => GraphQlType::string(),
                                'author' => GraphQlType::id(),
                                'category' => GraphQlType::id()
                            ]
                        ])),
                        'clientRequestId' => GraphQlType::string()
                    ]
                ],
                'postComment' => [
                    'type' => $this->postCommentMutation = new ObjectType([
                        'name' => 'PostCommentMutation',
                        'fields' => [
                            'comment' => $this->comment
                        ]
                    ]),
                    'args' => [
                        'input' => GraphQlType::nonNull($this->postCommentMutationInput = new InputObjectType([
                            'name' => 'PostCommentMutationInput',
                            'fields' => [
                                'text' => GraphQlType::nonNull(GraphQlType::string()),
                                'author' => GraphQlType::nonNull(GraphQlType::id()),
                                'content' => GraphQlType::id(),
                                'parent' => GraphQlType::id()
                            ]
                        ])),
                        'clientRequestId' => GraphQlType::string()
                    ]
                ]
            ]
        ]);
    }

    public function testEagerTypeResolution():void
    {
        // Has internal types by default:
        $eagerTypeResolution = new EagerResolution([]);
        $expectedTypeMap = [
            'ID' => GraphQlType::id(),
            'String' => GraphQlType::string(),
            'Float' => GraphQlType::float(),
            'Int' => GraphQlType::int(),
            'Boolean' => GraphQlType::boolean()
        ];
        expect($eagerTypeResolution->getTypeMap())->toBePHPEqual($expectedTypeMap);

        $expectedDescriptor = [
            'version' => '1.0',
            'typeMap' => [
                'ID' => 1,
                'String' => 1,
                'Float' => 1,
                'Int' => 1,
                'Boolean' => 1,
            ],
            'possibleTypeMap' => []
        ];
        expect($eagerTypeResolution->getDescriptor())->toBePHPEqual($expectedDescriptor);

        expect($eagerTypeResolution->resolveType('User'))->toBeSame(null);
        expect($eagerTypeResolution->resolvePossibleTypes($this->node))->toBeSame([]);
        expect($eagerTypeResolution->resolvePossibleTypes($this->content))->toBeSame([]);
        expect($eagerTypeResolution->resolvePossibleTypes($this->mention))->toBeSame([]);

        $eagerTypeResolution = new EagerResolution([$this->query, $this->mutation]);

        expect($eagerTypeResolution->resolveType('Query'))->toBeSame($this->query);
        expect($eagerTypeResolution->resolveType('Mutation'))->toBeSame($this->mutation);
        expect($eagerTypeResolution->resolveType('User'))->toBeSame($this->user);
        expect($eagerTypeResolution->resolveType('Node'))->toBeSame($this->node);
        expect($eagerTypeResolution->resolveType('Node'))->toBeSame($this->node);
        expect($eagerTypeResolution->resolveType('Content'))->toBeSame($this->content);
        expect($eagerTypeResolution->resolveType('Comment'))->toBeSame($this->comment);
        expect($eagerTypeResolution->resolveType('Mention'))->toBeSame($this->mention);
        expect($eagerTypeResolution->resolveType('BlogStory'))->toBeSame($this->blogStory);
        expect($eagerTypeResolution->resolveType('Category'))->toBeSame($this->category);
        expect($eagerTypeResolution->resolveType('PostStoryMutation'))->toBeSame($this->postStoryMutation);
        expect($eagerTypeResolution->resolveType('PostStoryMutationInput'))->toBeSame($this->postStoryMutationInput);
        expect($eagerTypeResolution->resolveType('PostCommentMutation'))->toBeSame($this->postCommentMutation);
        expect($eagerTypeResolution->resolveType('PostCommentMutationInput'))->toBeSame($this->postCommentMutationInput);

        expect($eagerTypeResolution->resolvePossibleTypes($this->content))->toBePHPEqual([$this->blogStory]);
        expect($eagerTypeResolution->resolvePossibleTypes($this->node))->toBePHPEqual([$this->user, $this->comment, $this->category, $this->blogStory]);
        expect($eagerTypeResolution->resolvePossibleTypes($this->mention))->toBePHPEqual([$this->user, $this->category]);

        $expectedTypeMap = [
            'Query' => $this->query,
            'Mutation' => $this->mutation,
            'User' => $this->user,
            'Node' => $this->node,
            'String' => GraphQlType::string(),
            'Content' => $this->content,
            'Comment' => $this->comment,
            'Mention' => $this->mention,
            'BlogStory' => $this->blogStory,
            'Category' => $this->category,
            'PostStoryMutationInput' => $this->postStoryMutationInput,
            'ID' => GraphQlType::id(),
            'PostStoryMutation' => $this->postStoryMutation,
            'PostCommentMutationInput' => $this->postCommentMutationInput,
            'PostCommentMutation' => $this->postCommentMutation,
            'Float' => GraphQlType::float(),
            'Int' => GraphQlType::int(),
            'Boolean' => GraphQlType::boolean()
        ];

        expect($eagerTypeResolution->getTypeMap())->toBePHPEqual($expectedTypeMap);

        $expectedDescriptor = [
            'version' => '1.0',
            'typeMap' => [
                'Query' => 1,
                'Mutation' => 1,
                'User' => 1,
                'Node' => 1,
                'String' => 1,
                'Content' => 1,
                'Comment' => 1,
                'Mention' => 1,
                'BlogStory' => 1,
                'Category' => 1,
                'PostStoryMutationInput' => 1,
                'ID' => 1,
                'PostStoryMutation' => 1,
                'PostCommentMutationInput' => 1,
                'PostCommentMutation' => 1,
                'Float' => 1,
                'Int' => 1,
                'Boolean' => 1
            ],
            'possibleTypeMap' => [
                'Node' => [
                    'User' => 1,
                    'Comment' => 1,
                    'Category' => 1,
                    'BlogStory' => 1
                ],
                'Content' => [
                    'BlogStory' => 1
                ],
                'Mention' => [
                    'User' => 1,
                    'Category' => 1
                ]
            ]
        ];

        expect($eagerTypeResolution->getDescriptor())->toBePHPEqual($expectedDescriptor);

        // Ignores duplicates and nulls in initialTypes:
        $eagerTypeResolution = new EagerResolution([null, $this->query, null, $this->query, $this->mutation, null]);
        expect($eagerTypeResolution->getTypeMap())->toBePHPEqual($expectedTypeMap);
        expect($eagerTypeResolution->getDescriptor())->toBePHPEqual($expectedDescriptor);

        // Those types are only part of interface
        expect($eagerTypeResolution->resolveType('Link'))->toBePHPEqual(null);
        expect($eagerTypeResolution->resolveType('Video'))->toBePHPEqual(null);
        expect($eagerTypeResolution->resolveType('VideoMetadata'))->toBePHPEqual(null);

        expect($eagerTypeResolution->resolvePossibleTypes($this->content))->toBePHPEqual([$this->blogStory]);
        expect($eagerTypeResolution->resolvePossibleTypes($this->node))->toBePHPEqual([$this->user, $this->comment, $this->category, $this->blogStory]);
        expect($eagerTypeResolution->resolvePossibleTypes($this->mention))->toBePHPEqual([$this->user, $this->category]);

        $eagerTypeResolution = new EagerResolution([null, $this->video, null]);
        expect($eagerTypeResolution->resolveType('VideoMetadata'))->toBePHPEqual($this->videoMetadata);
        expect($eagerTypeResolution->resolveType('Video'))->toBePHPEqual($this->video);

        expect($eagerTypeResolution->resolvePossibleTypes($this->content))->toBePHPEqual([$this->video]);
        expect($eagerTypeResolution->resolvePossibleTypes($this->node))->toBePHPEqual([$this->video, $this->user, $this->comment, $this->category]);
        expect($eagerTypeResolution->resolvePossibleTypes($this->mention))->toBePHPEqual([]);

        $expectedTypeMap = [
            'Video' => $this->video,
            'Node' => $this->node,
            'String' => GraphQlType::string(),
            'Content' => $this->content,
            'User' => $this->user,
            'Comment' => $this->comment,
            'Category' => $this->category,
            'VideoMetadata' => $this->videoMetadata,
            'Float' => GraphQlType::float(),
            'ID' => GraphQlType::id(),
            'Int' => GraphQlType::int(),
            'Boolean' => GraphQlType::boolean()
        ];
        expect($eagerTypeResolution->getTypeMap())->toBePHPEqual($expectedTypeMap);

        $expectedDescriptor = [
            'version' => '1.0',
            'typeMap' => [
                'Video' => 1,
                'Node' => 1,
                'String' => 1,
                'Content' => 1,
                'User' => 1,
                'Comment' => 1,
                'Category' => 1,
                'VideoMetadata' => 1,
                'Float' => 1,
                'ID' => 1,
                'Int' => 1,
                'Boolean' => 1
            ],
            'possibleTypeMap' => [
                'Node' => [
                    'Video' => 1,
                    'User' => 1,
                    'Comment' => 1,
                    'Category' => 1
                ],
                'Content' => [
                    'Video' => 1
                ]
            ]
        ];
        expect($eagerTypeResolution->getDescriptor())->toBePHPEqual($expectedDescriptor);
    }

    public function testLazyResolutionFollowsEagerResolution():void
    {
        // Lazy resolution should work the same way as eager resolution works, except that it should load types on demand
        $eager = new EagerResolution([]);
        $emptyDescriptor = $eager->getDescriptor();

        $typeLoader = function($name) {
            throw new \Exception("This should be never called for empty descriptor");
        };

        $lazy = new LazyResolution($emptyDescriptor, $typeLoader);
        expect($lazy->resolveType('User'))->toBeSame($eager->resolveType('User'));
        expect($lazy->resolvePossibleTypes($this->node))->toBeSame($eager->resolvePossibleTypes($this->node));
        expect($lazy->resolvePossibleTypes($this->content))->toBeSame($eager->resolvePossibleTypes($this->content));
        expect($lazy->resolvePossibleTypes($this->mention))->toBeSame($eager->resolvePossibleTypes($this->mention));

        $eager = new EagerResolution([$this->query, $this->mutation]);

        $called = 0;
        $descriptor = $eager->getDescriptor();
        $typeLoader = function($name) use (&$called) {
            $called++;
            $prop = \lcfirst($name);
            return $this->{$prop};
        };

        $lazy = new LazyResolution($descriptor, $typeLoader);

        expect($lazy->resolveType('Query'))->toBeSame($eager->resolveType('Query'));
        expect($called)->toBeSame(1);
        expect($lazy->resolveType('Mutation'))->toBeSame($eager->resolveType('Mutation'));
        expect($called)->toBeSame(2);
        expect($lazy->resolveType('User'))->toBeSame($eager->resolveType('User'));
        expect($called)->toBeSame(3);
        expect($lazy->resolveType('User'))->toBeSame($eager->resolveType('User'));
        expect($called)->toBeSame(3);
        expect($lazy->resolveType('Node'))->toBeSame($eager->resolveType('Node'));
        expect($lazy->resolveType('Node'))->toBeSame($eager->resolveType('Node'));
        expect($called)->toBeSame(4);
        expect($lazy->resolveType('Content'))->toBeSame($eager->resolveType('Content'));
        expect($lazy->resolveType('Comment'))->toBeSame($eager->resolveType('Comment'));
        expect($lazy->resolveType('Mention'))->toBeSame($eager->resolveType('Mention'));
        expect($lazy->resolveType('BlogStory'))->toBeSame($eager->resolveType('BlogStory'));
        expect($lazy->resolveType('Category'))->toBeSame($eager->resolveType('Category'));
        expect($lazy->resolveType('PostStoryMutation'))->toBeSame($eager->resolveType('PostStoryMutation'));
        expect($lazy->resolveType('PostStoryMutationInput'))->toBeSame($eager->resolveType('PostStoryMutationInput'));
        expect($lazy->resolveType('PostCommentMutation'))->toBeSame($eager->resolveType('PostCommentMutation'));
        expect($lazy->resolveType('PostCommentMutationInput'))->toBeSame($eager->resolveType('PostCommentMutationInput'));
        expect($called)->toBeSame(13);

        expect($lazy->resolvePossibleTypes($this->content))->toBePHPEqual($eager->resolvePossibleTypes($this->content));
        expect($lazy->resolvePossibleTypes($this->node))->toBePHPEqual($eager->resolvePossibleTypes($this->node));
        expect($lazy->resolvePossibleTypes($this->mention))->toBePHPEqual($eager->resolvePossibleTypes($this->mention));

        $called = 0;
        $eager = new EagerResolution([$this->video]);
        $lazy = new LazyResolution($eager->getDescriptor(), $typeLoader);

        expect($lazy->resolveType('VideoMetadata'))->toBePHPEqual($eager->resolveType('VideoMetadata'));
        expect($lazy->resolveType('Video'))->toBePHPEqual($eager->resolveType('Video'));
        expect($called)->toBePHPEqual(2);

        expect($lazy->resolvePossibleTypes($this->content))->toBePHPEqual($eager->resolvePossibleTypes($this->content));
        expect($lazy->resolvePossibleTypes($this->node))->toBePHPEqual($eager->resolvePossibleTypes($this->node));
        expect($lazy->resolvePossibleTypes($this->mention))->toBePHPEqual($eager->resolvePossibleTypes($this->mention));
    }

    private function createLazy(){

        $descriptor = [
            'version' => '1.0',
            'typeMap' => [
                'null' => 1,
                'int' => 1
            ],
            'possibleTypeMap' => [
                'a' => [
                    'null' => 1,
                ],
                'b' => [
                    'int' => 1
                ]
            ]
        ];

        $invalidTypeLoader = function($name) {
            switch ($name) {
                case 'null':
                    return null;
                case 'int':
                    return 7;
            }
        };

        $lazy = new LazyResolution($descriptor, $invalidTypeLoader);
        $value = $lazy->resolveType('null');
        expect($value)->toBePHPEqual(null);

        return $lazy;
    }

    public function testLazyThrowsOnInvalidLoadedType():void
    {
        $lazy = $this->createLazy();
        $this->setExpectedException(InvariantViolation::class, "Lazy Type Resolution Error: Expecting GraphQL Type instance, but got integer");
        $lazy->resolveType('int');
    }

    public function testLazyThrowsOnInvalidLoadedPossibleType():void
    {
        $tmp = new InterfaceType(['name' => 'a', 'fields' => []]);
        $lazy = $this->createLazy();
        $this->setExpectedException(InvariantViolation::class, 'Lazy Type Resolution Error: Implementation null of interface a is expected to be instance of ObjectType, but got NULL');
        $lazy->resolvePossibleTypes($tmp);
    }

    public function testLazyThrowsOnInvalidLoadedPossibleTypeWithInteger():void
    {
        $tmp = new InterfaceType(['name' => 'b', 'fields' => []]);
        $lazy = $this->createLazy();
        $this->setExpectedException(InvariantViolation::class, 'Lazy Type Resolution Error: Expecting GraphQL Type instance, but got integer');
        $lazy->resolvePossibleTypes($tmp);
    }
}
