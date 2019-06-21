<?hh //strict
//decl
namespace GraphQL\Tests;

use GraphQL\Error\Error;
use function Facebook\FBExpect\expect;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Language\SourceLocation;

class ErrorTest extends \Facebook\HackTest\HackTest
{
    /**
     * @it uses the stack of an original error
     */
    public function testUsesTheStackOfAnOriginalError():void
    {
        $prev = new \Exception("Original");
        $err = new Error('msg', null, null, null, null, $prev);

        expect($prev)->toBeSame($err->getPrevious());
    }

    /**
     * @it converts nodes to positions and locations
     */
    public function testConvertsNodesToPositionsAndLocations():void
    {
        $source = new Source('{
      field
    }');
        $ast = Parser::parseSource($source);
        $fieldNode = $ast->definitions[0]->selectionSet->selections[0];
        $e = new Error('msg', [ $fieldNode ]);

        expect($e->nodes)->toBePHPEqual([$fieldNode]);
        expect($e->getSource())->toBePHPEqual($source);
        expect($e->getPositions())->toBePHPEqual([8]);
        expect($e->getLocations())->toBePHPEqual([new SourceLocation(2, 7)]);
    }

    /**
     * @it converts node with loc.start === 0 to positions and locations
     */
    public function testConvertsNodeWithStart0ToPositionsAndLocations()
    {
        $source = new Source('{
      field
    }');
        $ast = Parser::parseSource($source);
        $operationNode = $ast->definitions[0];
        $e = new Error('msg', [ $operationNode ]);

        expect($e->nodes)->toBePHPEqual([$operationNode]);
        expect($e->getSource())->toBePHPEqual($source);
        expect($e->getPositions())->toBePHPEqual([0]);
        expect($e->getLocations())->toBePHPEqual([new SourceLocation(1, 1)]);
    }

    /**
     * @it converts source and positions to locations
     */
    public function testConvertsSourceAndPositionsToLocations():void
    {
        $source = new Source('{
      field
    }');
        $e = new Error('msg', null, $source, [ 10 ]);

        expect($e->nodes)->toBePHPEqual(null);
        expect($e->getSource())->toBePHPEqual($source);
        expect($e->getPositions())->toBePHPEqual([10]);
        expect($e->getLocations())->toBePHPEqual([new SourceLocation(2, 9)]);
    }

    /**
     * @it serializes to include message
     */
    public function testSerializesToIncludeMessage():void
    {
        $e = new Error('msg');
        expect($e->toSerializableArray())->toBePHPEqual(['message' => 'msg']);
    }

    /**
     * @it serializes to include message and locations
     */
    public function testSerializesToIncludeMessageAndLocations():void
    {
        $node = Parser::parse('{ field }')->definitions[0]->selectionSet->selections[0];
        $e = new Error('msg', [ $node ]);

        expect($e->toSerializableArray())
            ->toBePHPEqual(['message' => 'msg', 'locations' => [['line' => 1, 'column' => 3]]]);
    }

    /**
     * @it serializes to include path
     */
    public function testSerializesToIncludePath():void
    {
        $e = new Error(
            'msg',
            null,
            null,
            null,
            [ 'path', 3, 'to', 'field' ]
        );

        expect($e->path)->toBePHPEqual([ 'path', 3, 'to', 'field' ]);
        expect($e->toSerializableArray())->toBePHPEqual(['message' => 'msg', 'path' => [ 'path', 3, 'to', 'field' ]]);
    }
}
