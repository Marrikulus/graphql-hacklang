<?hh //strict
namespace GraphQL\Tests\Server;

use GraphQL\Executor\ExecutionResult;
use function Facebook\FBExpect\expect;
use GraphQL\Server\Helper;
use GraphQL\Tests\Server\Psr7\PsrStreamStub;
use GraphQL\Tests\Server\Psr7\PsrResponseStub;

class PsrResponseTest extends \Facebook\HackTest\HackTest
{
    // public function testConvertsResultToPsrResponse():void
    // {
    //     $result = new ExecutionResult(['key' => 'value']);
    //     $stream = new PsrStreamStub();
    //     $psrResponse = new PsrResponseStub();

    //     $helper = new Helper();

    //     /** @var PsrResponseStub $resp */
    //     $resp = $helper->toPsrResponse($result, $psrResponse, $stream);
    //     expect($resp->body->content)->toBeSame(\json_encode($result));
    //     expect($resp->headers)->toBeSame(['Content-Type' => ['application/json']]);
    // }
}
