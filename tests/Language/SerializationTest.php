<?hh //strict
//decl
namespace GraphQL\Tests;

use GraphQL\Language\AST\Location;
use function Facebook\FBExpect\expect;
use GraphQL\Language\AST\Node;
//use GraphQL\Language\AST\NodeList;
use GraphQL\Language\Parser;
use GraphQL\Utils\AST;

class SerializationTest extends \Facebook\HackTest\HackTest
{
    public function testSerializesAst():void
    {
        $kitchenSink = \file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $ast = Parser::parse($kitchenSink);
        $expectedAst = \json_decode(\file_get_contents(__DIR__ . '/kitchen-sink.ast'), true);
        expect($ast->toArray(true))->toBePHPEqual($expectedAst);
    }

    public function testUnserializesAst():void
    {
        $kitchenSink = \file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $serializedAst = \json_decode(\file_get_contents(__DIR__ . '/kitchen-sink.ast'), true);
        $actualAst = AST::fromArray($serializedAst);
        $parsedAst = Parser::parse($kitchenSink);
        $this->assertNodesAreEqual($parsedAst, $actualAst);
    }

    public function testSerializeSupportsNoLocationOption():void
    {
        $kitchenSink = \file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $ast = Parser::parse($kitchenSink, true);
        $expectedAst = \json_decode(\file_get_contents(__DIR__ . '/kitchen-sink-noloc.ast'), true);
        expect($ast->toArray(true))->toBePHPEqual($expectedAst);
    }

    public function testUnserializeSupportsNoLocationOption():void
    {
        $kitchenSink = \file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $serializedAst = \json_decode(\file_get_contents(__DIR__ . '/kitchen-sink-noloc.ast'), true);
        $actualAst = AST::fromArray($serializedAst);
        $parsedAst = Parser::parse($kitchenSink, true);
        $this->assertNodesAreEqual($parsedAst, $actualAst);
    }

    /**
     * Compares two nodes by actually iterating over all NodeLists, properly comparing locations (ignoring tokens), etc
     *
     * @param $expected
     * @param $actual
     * @param array $path
     */
    private function assertNodesAreEqual(Node $expected, Node $actual, array<string> $path = []):void
    {
        $err = "Mismatch at AST path: " . \implode(', ', $path);

        expect($actual)->toBeInstanceOf(Node::class, $err);
        expect(\get_class($actual))->toBePHPEqual(\get_class($expected), $err);

        $expectedVars = \get_object_vars($expected);
        $actualVars = \get_object_vars($actual);
        expect(\count($actualVars))->toBeSame(\count($expectedVars), $err);
        expect(\array_keys($actualVars))->toBePHPEqual(\array_keys($expectedVars), $err);

        foreach ($expectedVars as $name => $expectedValue)
        {
            $actualValue = $actualVars[$name];
            $tmpPath = $path;
            $tmpPath[] = $name;
            $err = "Mismatch at AST path: " . \implode(', ', $tmpPath);

            if ($expectedValue instanceof Node)
            {
                $this->assertNodesAreEqual($expectedValue, $actualValue, $tmpPath);
            }
            //else if ($expectedValue instanceof NodeList)
            //{
            //    expect(\count($actualValue))->toBePHPEqual(\count($expectedValue), $err);
            //    expect($actualValue)->toBeInstanceOf(NodeList::class, $err);
            //    foreach ($expectedValue as $index => $listNode)
            //    {
            //        $tmpPath2 = $tmpPath;
            //        $tmpPath2 [] = $index;
            //        $this->assertNodesAreEqual($listNode, $actualValue[$index], $tmpPath2);
            //    }
            //}
            else if ($expectedValue instanceof Location)
            {
                expect($actualValue)->toBeInstanceOf(Location::class, $err);
                expect($actualValue->start)->toBeSame($expectedValue->start, $err);
                expect($actualValue->end)->toBeSame($expectedValue->end, $err);
            }
            else
            {
                expect($actualValue)->toBePHPEqual($expectedValue, $err);
            }
        }
    }
}
