<?hh //strict
//decl
namespace GraphQL\Tests\Language;

use GraphQL\Language\AST\DocumentNode;
use function Facebook\FBExpect\expect;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;

class PrinterTest extends \Facebook\HackTest\HackTest
{
    /**
     * @it does not alter ast
     */
    public function testDoesntAlterAST():void
    {
        $kitchenSink = \file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $ast = Parser::parse($kitchenSink);

        $astCopy = $ast->cloneDeep();
        expect($ast)->toBePHPEqual($astCopy);

        Printer::doPrint($ast);
        expect($ast)->toBePHPEqual($astCopy);
    }

    /**
     * @it prints minimal ast
     */
    public function testPrintsMinimalAst():void
    {
        $ast = new FieldNode(new NameNode('foo'));
        expect(Printer::doPrint($ast))->toBePHPEqual('foo');
    }

    /**
     * @it produces helpful error messages
     */
    public function testProducesHelpfulErrorMessages():void
    {
        $badAst1 = new \ArrayObject(['random' => 'Data']);
        $this->setExpectedException(\Exception::class, 'Invalid AST Node: {"random":"Data"}');
        Printer::doPrint($badAst1);
    }

    /**
     * @it correctly prints non-query operations without name
     */
    public function testCorrectlyPrintsOpsWithoutName():void
    {
        $queryAstShorthanded = Parser::parse('query { id, name }');

        $expected = '{
  id
  name
}
';
        expect(Printer::doPrint($queryAstShorthanded))->toBePHPEqual($expected);

        $mutationAst = Parser::parse('mutation { id, name }');
        $expected = 'mutation {
  id
  name
}
';
        expect(Printer::doPrint($mutationAst))->toBePHPEqual($expected);

        $queryAstWithArtifacts = Parser::parse(
            'query ($foo: TestType) @testDirective { id, name }'
        );
        $expected = 'query ($foo: TestType) @testDirective {
  id
  name
}
';
        expect(Printer::doPrint($queryAstWithArtifacts))->toBePHPEqual($expected);

        $mutationAstWithArtifacts = Parser::parse(
            'mutation ($foo: TestType) @testDirective { id, name }'
        );
        $expected = 'mutation ($foo: TestType) @testDirective {
  id
  name
}
';
        expect(Printer::doPrint($mutationAstWithArtifacts))->toBePHPEqual($expected);
    }

    /**
     * @it prints kitchen sink
     */
    public function testPrintsKitchenSink():void
    {
        $kitchenSink = \file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $ast = Parser::parse($kitchenSink);

        $printed = Printer::doPrint($ast);

        $expected = <<<'EOT'
query queryName($foo: ComplexType, $site: Site = MOBILE) {
  whoever123is: node(id: [123, 456]) {
    id
    ... on User @defer {
      field2 {
        id
        alias: field1(first: 10, after: $foo) @include(if: $foo) {
          id
          ...frag
        }
      }
    }
    ... @skip(unless: $foo) {
      id
    }
    ... {
      id
    }
  }
}

mutation likeStory {
  like(story: 123) @defer {
    story {
      id
    }
  }
}

subscription StoryLikeSubscription($input: StoryLikeSubscribeInput) {
  storyLikeSubscribe(input: $input) {
    story {
      likers {
        count
      }
      likeSentence {
        text
      }
    }
  }
}

fragment frag on Friend {
  foo(size: $size, bar: $b, obj: {key: "value"})
}

{
  unnamed(truthy: true, falsey: false, nullish: null)
  query
}

EOT;
        expect($printed)->toBePHPEqual($expected);
    }
}
