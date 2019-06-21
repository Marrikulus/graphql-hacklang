<?hh //strict
//decl
namespace GraphQL\Tests;

use GraphQL\Language\Token;
use function Facebook\FBExpect\expect;

class TokenTest extends \Facebook\HackTest\HackTest
{
    public function testReturnTokenOnArray():void
    {
        $token = new Token('Kind', 1, 10, 3, 5);
        $expected = [
            'kind' => 'Kind',
            'value' => null,
            'line' => 3,
            'column' => 5
        ];

        expect($token->toArray())->toBePHPEqual($expected);
    }
}
