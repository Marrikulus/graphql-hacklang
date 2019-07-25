<?hh //strict
//decl
namespace GraphQL\Tests\Language;

use GraphQL\Error\InvariantViolation;
use function Facebook\FBExpect\expect;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Language\SourceLocation;
use GraphQL\Error\SyntaxError;
use GraphQL\Utils\Utils;

class ParserTest extends \Facebook\HackTest\HackTest
{

    public function parseProvidesUsefulErrors():array<array<mixed>>
    {
        return [
            ['{', "Syntax Error GraphQL (1:2) Expected Name, found <EOF>\n\n1: {\n    ^\n", [1], [new SourceLocation(1, 2)]],
            ['{ ...MissingOn }
fragment MissingOn Type
', "Syntax Error GraphQL (2:20) Expected \"on\", found Name \"Type\"\n\n1: { ...MissingOn }\n2: fragment MissingOn Type\n                      ^\n3: \n",],
            ['{ field: {} }', "Syntax Error GraphQL (1:10) Expected Name, found {\n\n1: { field: {} }\n            ^\n"],
            ['notanoperation Foo { field }', "Syntax Error GraphQL (1:1) Unexpected Name \"notanoperation\"\n\n1: notanoperation Foo { field }\n   ^\n"],
            ['...', "Syntax Error GraphQL (1:1) Unexpected ...\n\n1: ...\n   ^\n"],
        ];
    }

    /**
     * @it parse provides useful errors
     */
    <<DataProvider('parseProvidesUsefulErrors')>>
    public function testParseProvidesUsefulErrors(string $str, string $expectedMessage, ?array<int> $expectedPositions = null, ?array<SourceLocation> $expectedLocations = null):void
    {
        try {
            Parser::parse($str);
            self::fail('Expected exception not thrown');
        } catch (SyntaxError $e) {
            expect($e->getMessage())->toBePHPEqual($expectedMessage);

            if ($expectedPositions !== null) {
                expect($e->getPositions())->toBePHPEqual($expectedPositions);
            }

            if ($expectedLocations !== null) {
                expect($e->getLocations())->toBePHPEqual($expectedLocations);
            }
        }
    }

    /**
     * @it parse provides useful error when using source
     */
    public function testParseProvidesUsefulErrorWhenUsingSource():void
    {
        $this->setExpectedException(SyntaxError::class, "Syntax Error MyQuery.graphql (1:6) Expected {, found <EOF>\n\n1: query\n        ^\n");
        Parser::parseSource(new Source('query', 'MyQuery.graphql'));
    }

    /**
     * @it parses variable inline values
     */
    public function testParsesVariableInlineValues():void
    {
        // Following line should not throw:
        Parser::parseSource(new Source('{ field(complex: { a: { b: [ $var ] } }) }'));
    }

    /**
     * @it parses constant default values
     */
    public function testParsesConstantDefaultValues():void
    {
        $this->setExpectedException(SyntaxError::class, "Syntax Error GraphQL (1:37) Unexpected $\n\n" . '1: query Foo($x: Complex = { a: { b: [ $var ] } }) { field }' . "\n                                       ^\n");
        Parser::parse('query Foo($x: Complex = { a: { b: [ $var ] } }) { field }');
    }

    /**
     * @it does not accept fragments spread of "on"
     */
    public function testDoesNotAcceptFragmentsNamedOn():void
    {
        $this->setExpectedException('GraphQL\Error\SyntaxError', 'Syntax Error GraphQL (1:10) Unexpected Name "on"');
        Parser::parse('fragment on on on { on }');
    }

    /**
     * @it does not accept fragments spread of "on"
     */
    public function testDoesNotAcceptFragmentSpreadOfOn():void
    {
        $this->setExpectedException('GraphQL\Error\SyntaxError', 'Syntax Error GraphQL (1:9) Expected Name, found }');
        Parser::parse('{ ...on }');
    }

    /**
     * @it parses multi-byte characters
     */
    public function testParsesMultiByteCharacters():void
    {
        // Note: \u0A0A could be naively interpretted as two line-feed chars.

        $char = Utils::chr(0x0A0A);
        $query = <<<HEREDOC
        # This comment has a $char multi-byte character.
        { field(arg: "Has a $char multi-byte character.") }
HEREDOC;

        $result = Parser::parse($query, true);

        $expected = new SelectionSetNode(
            [
                new FieldNode(
                    new NameNode('field', null),
                    null,
                    [
                        new ArgumentNode(
                            new NameNode('arg', null),
                            new StringValueNode("Has a $char multi-byte character.", null),
                            null
                        )
                    ],
                    [],
                    null,
                    null
                )
            ],
            null
        );

        expect($result->definitions[0]->selectionSet)->toBePHPEqual($expected);
    }

    /**
     * @it parses kitchen sink
     */
    public function testParsesKitchenSink():void
    {
        // Following should not throw:
        $kitchenSink = \file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $result = Parser::parse($kitchenSink);
        expect($result)->toNotBeEmpty();
    }

    /**
     * allows non-keywords anywhere a Name is allowed
     */
    public function testAllowsNonKeywordsAnywhereANameIsAllowed():void
    {
        $nonKeywords = [
            'on',
            'fragment',
            'query',
            'mutation',
            'subscription',
            'true',
            'false'
        ];
        foreach ($nonKeywords as $keyword) {
            $fragmentName = $keyword;
            if ($keyword === 'on') {
                $fragmentName = 'a';
            }

            // Expected not to throw:
            $result = Parser::parse("query $keyword {
  ... $fragmentName
  ... on $keyword { field }
}
fragment $fragmentName on Type {
  $keyword($keyword: \$$keyword) @$keyword($keyword: $keyword)
}
");
            expect($result)->toNotBeEmpty();
        }
    }

    /**
     * @it parses anonymous mutation operations
     */
    public function testParsessAnonymousMutationOperations():void
    {
        // Should not throw:
        Parser::parse('
          mutation {
            mutationField
          }
        ');
    }

    /**
     * @it parses anonymous subscription operations
     */
    public function testParsesAnonymousSubscriptionOperations():void
    {
        // Should not throw:
        Parser::parse('
          subscription {
            subscriptionField
          }
        ');
    }

    /**
     * @it parses named mutation operations
     */
    public function testParsesNamedMutationOperations():void
    {
        // Should not throw:
        Parser::parse('
          mutation Foo {
            mutationField
          }
        ');
    }

    /**
     * @it parses named subscription operations
     */
    public function testParsesNamedSubscriptionOperations():void
    {
        Parser::parse('
          subscription Foo {
            subscriptionField
          }
        ');
    }

    /**
     * @it creates ast
     */
    public function testParseCreatesAst():void
    {
        $source = new Source('{
  node(id: 4) {
    id,
    name
  }
}
');
        $result = Parser::parseSource($source);

        $loc = function($start, $end) use ($source) {
            return [
                'start' => $start,
                'end' => $end
            ];
        };

        $expected = [
            'kind' => NodeKind::DOCUMENT,
            'loc' => $loc(0, 41),
            'definitions' => [
                [
                    'kind' => NodeKind::OPERATION_DEFINITION,
                    'loc' => $loc(0, 40),
                    'operation' => 'query',
                    'name' => null,
                    'variableDefinitions' => null,
                    'directives' => [],
                    'selectionSet' => [
                        'kind' => NodeKind::SELECTION_SET,
                        'loc' => $loc(0, 40),
                        'selections' => [
                            [
                                'kind' => NodeKind::FIELD,
                                'loc' => $loc(4, 38),
                                'alias' => null,
                                'name' => [
                                    'kind' => NodeKind::NAME,
                                    'loc' => $loc(4, 8),
                                    'value' => 'node'
                                ],
                                'arguments' => [
                                    [
                                        'kind' => NodeKind::ARGUMENT,
                                        'name' => [
                                            'kind' => NodeKind::NAME,
                                            'loc' => $loc(9, 11),
                                            'value' => 'id'
                                        ],
                                        'value' => [
                                            'kind' => NodeKind::INT,
                                            'loc' => $loc(13, 14),
                                            'value' => '4'
                                        ],
                                        'loc' => $loc(9, 14)
                                    ]
                                ],
                                'directives' => [],
                                'selectionSet' => [
                                    'kind' => NodeKind::SELECTION_SET,
                                    'loc' => $loc(16, 38),
                                    'selections' => [
                                        [
                                            'kind' => NodeKind::FIELD,
                                            'loc' => $loc(22, 24),
                                            'alias' => null,
                                            'name' => [
                                                'kind' => NodeKind::NAME,
                                                'loc' => $loc(22, 24),
                                                'value' => 'id'
                                            ],
                                            'arguments' => [],
                                            'directives' => [],
                                            'selectionSet' => null
                                        ],
                                        [
                                            'kind' => NodeKind::FIELD,
                                            'loc' => $loc(30, 34),
                                            'alias' => null,
                                            'name' => [
                                                'kind' => NodeKind::NAME,
                                                'loc' => $loc(30, 34),
                                                'value' => 'name'
                                            ],
                                            'arguments' => [],
                                            'directives' => [],
                                            'selectionSet' => null
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        expect($this->nodeToArray($result))->toBePHPEqual($expected);
    }

    /**
     * @it allows parsing without source location information
     */
    public function testAllowsParsingWithoutSourceLocationInformation():void
    {
        $source = new Source('{ id }');
        $result = Parser::parseSource($source, true);

        expect($result->loc)->toBePHPEqual(null);
    }

    /**
     * @it contains location information that only stringifys start/end
     */
    public function testConvertToArray():void
    {
        $source = new Source('{ id }');
        $result = Parser::parseSource($source);
        expect(TestUtils::locationToArray($result->loc))->toBePHPEqual(['start' => 0, 'end' => '6']);
    }

    /**
     * @it contains references to source
     */
    public function testContainsReferencesToSource():void
    {
        $source = new Source('{ id }');
        $result = Parser::parseSource($source);
        expect($result->loc->source)->toBePHPEqual($source);
    }

    /**
     * @it contains references to start and end tokens
     */
    public function testContainsReferencesToStartAndEndTokens():void
    {
        $source = new Source('{ id }');
        $result = Parser::parseSource($source);
        expect($result->loc->startToken->kind)->toBePHPEqual('<SOF>');
        expect($result->loc->endToken->kind)->toBePHPEqual('<EOF>');
    }

    // Describe: parseValue

    /**
     * @it parses null value
     */
    public function testParsesNullValues():void
    {
        expect($this->nodeToArray(Parser::parseValue(new Source('null'))))->toBePHPEqual([
            'kind' => NodeKind::NULL,
            'loc' => ['start' => 0, 'end' => 4]
        ]);
    }

    /**
     * @it parses list values
     */
    public function testParsesListValues():void
    {
        expect($this->nodeToArray(Parser::parseValue(new Source('[123 "abc"]'))))->toBePHPEqual([
            'kind' => NodeKind::LST,
            'loc' => ['start' => 0, 'end' => 11],
            'values' => [
                [
                    'kind' => NodeKind::INT,
                    'loc' => ['start' => 1, 'end' => 4],
                    'value' => '123'
                ],
                [
                    'kind' => NodeKind::STRING,
                    'loc' => ['start' => 5, 'end' => 10],
                    'value' => 'abc'
                ]
            ]
        ]);
    }

    // Describe: parseType

    /**
     * @it parses well known types
     */
    public function testParsesWellKnownTypes():void
    {
        expect($this->nodeToArray(Parser::parseType(new Source('String'))))->toBePHPEqual([
            'kind' => NodeKind::NAMED_TYPE,
            'loc' => ['start' => 0, 'end' => 6],
            'name' => [
                'kind' => NodeKind::NAME,
                'loc' => ['start' => 0, 'end' => 6],
                'value' => 'String'
            ]
        ]);
    }

    /**
     * @it parses custom types
     */
    public function testParsesCustomTypes():void
    {
        expect($this->nodeToArray(Parser::parseType(new Source('MyType'))))->toBePHPEqual([
            'kind' => NodeKind::NAMED_TYPE,
            'loc' => ['start' => 0, 'end' => 6],
            'name' => [
                'kind' => NodeKind::NAME,
                'loc' => ['start' => 0, 'end' => 6],
                'value' => 'MyType'
            ]
        ]);
    }

    /**
     * @it parses list types
     */
    public function testParsesListTypes():void
    {
        expect($this->nodeToArray(Parser::parseType(new Source('[MyType]'))))->toBePHPEqual([
            'kind' => NodeKind::LIST_TYPE,
            'loc' => ['start' => 0, 'end' => 8],
            'type' => [
                'kind' => NodeKind::NAMED_TYPE,
                'loc' => ['start' => 1, 'end' => 7],
                'name' => [
                    'kind' => NodeKind::NAME,
                    'loc' => ['start' => 1, 'end' => 7],
                    'value' => 'MyType'
                ]
            ]
        ]);
    }

    /**
     * @it parses non-null types
     */
    public function testParsesNonNullTypes():void
    {
        expect($this->nodeToArray(Parser::parseType(new Source('MyType!'))))->toBePHPEqual([
            'kind' => NodeKind::NON_NULL_TYPE,
            'loc' => ['start' => 0, 'end' => 7],
            'type' => [
                'kind' => NodeKind::NAMED_TYPE,
                'loc' => ['start' => 0, 'end' => 6],
                'name' => [
                    'kind' => NodeKind::NAME,
                    'loc' => ['start' => 0, 'end' => 6],
                    'value' => 'MyType'
                ]
            ]
        ]);
    }

    /**
     * @it parses nested types
     */
    public function testParsesNestedTypes():void
    {
        expect($this->nodeToArray(Parser::parseType(new Source('[MyType!]'))))->toBePHPEqual([
            'kind' => NodeKind::LIST_TYPE,
            'loc' => ['start' => 0, 'end' => 9],
            'type' => [
                'kind' => NodeKind::NON_NULL_TYPE,
                'loc' => ['start' => 1, 'end' => 8],
                'type' => [
                    'kind' => NodeKind::NAMED_TYPE,
                    'loc' => ['start' => 1, 'end' => 7],
                    'name' => [
                        'kind' => NodeKind::NAME,
                        'loc' => ['start' => 1, 'end' => 7],
                        'value' => 'MyType'
                    ]
                ]
            ]
        ]);
    }

    /**
     * @param Node $node
     * @return array
     */
    public function nodeToArray(Node $node):array<string, mixed>
    {
        return TestUtils::nodeToArray($node);
    }
}
