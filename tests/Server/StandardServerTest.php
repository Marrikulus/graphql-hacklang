<?hh //strict
//decl
namespace GraphQL\Tests\Server;

use GraphQL\Executor\ExecutionResult;
use function Facebook\FBExpect\expect;
use GraphQL\Server\Helper;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use GraphQL\Tests\Server\Psr7\PsrRequestStub;
use GraphQL\Tests\Server\Psr7\PsrStreamStub;

class StandardServerTest extends TestCase
{
    /**
     * @var ServerConfig
     */
    private $config;

    public async function beforeEachTestAsync(): Awaitable<void>
    {
        $schema = $this->buildSchema();
        $this->config = ServerConfig::create()
            ->setSchema($schema);
    }

    public function testSimpleRequestExecutionWithOutsideParsing():void
    {
        $body = \json_encode([
            'query' => '{f1}'
        ]);

        $parsedBody = $this->parseRawRequest('application/json', $body);
        $server = new StandardServer($this->config);

        $result = $server->executeRequest($parsedBody);
        $expected = [
            'data' => [
                'f1' => 'f1',
            ]
        ];

        expect($result->toArray(true))->toBePHPEqual($expected);
    }

    public function testSimplePsrRequestExecution():void
    {
        $body = [
            'query' => '{f1}'
        ];

        $expected = [
            'data' => [
                'f1' => 'f1'
            ]
        ];

        $request = $this->preparePsrRequest('application/json', $body);
        $this->assertPsrRequestEquals($expected, $request);
    }

    private function executePsrRequest($psrRequest)
    {
        $server = new StandardServer($this->config);
        $result = $server->executePsrRequest($psrRequest);
        expect($result)->toBeInstanceOf(ExecutionResult::class);
        return $result;
    }

    private function assertPsrRequestEquals($expected, $request)
    {
        $result = $this->executePsrRequest($request);
        expect($result->toArray(1))->toInclude($expected);
        return $result;
    }

    private function preparePsrRequest($contentType, $parsedBody)
    {
        $psrRequest = new PsrRequestStub();
        $psrRequest->headers['content-type'] = [$contentType];
        $psrRequest->method = 'POST';
        $psrRequest->parsedBody = $parsedBody;
        return $psrRequest;
    }

    private function parseRawRequest($contentType, $content, $method = 'POST')
    {
        $_SERVER['CONTENT_TYPE'] = $contentType;
        $_SERVER['REQUEST_METHOD'] = $method;

        $helper = new Helper();
        return $helper->parseHttpRequest(function() use ($content) {
            return $content;
        });
    }
}
