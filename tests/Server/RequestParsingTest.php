<?hh //strict
//decl
namespace GraphQL\Tests\Server;

use GraphQL\Error\Error;
use function Facebook\FBExpect\expect;
use GraphQL\Error\InvariantViolation;
use GraphQL\Server\Helper;
use GraphQL\Server\OperationParams;
use GraphQL\Server\RequestError;
use GraphQL\Tests\Server\Psr7\PsrRequestStub;
use GraphQL\Tests\Server\Psr7\PsrStreamStub;

class RequestParsingTest extends \Facebook\HackTest\HackTest
{
    public function testParsesGraphqlRequest():void
    {
        $query = '{my query}';
        $parsed = [
            'raw' => $this->parseRawRequest('application/graphql', $query),
            'psr' => $this->parsePsrRequest('application/graphql', $query)
        ];

        foreach ($parsed as $source => $parsedBody) {
            $this->assertValidOperationParams($parsedBody, $query, null, null, null, $source);
            expect($parsedBody->isReadOnly())->toBeFalse($source);
        }
    }

    public function testParsesUrlencodedRequest():void
    {
        $query = '{my query}';
        $variables = ['test' => 1, 'test2' => 2];
        $operation = 'op';

        $post = [
            'query' => $query,
            'variables' => $variables,
            'operation' => $operation
        ];
        $parsed = [
            'raw' => $this->parseRawFormUrlencodedRequest($post),
            'psr' => $this->parsePsrFormUrlEncodedRequest($post)
        ];

        foreach ($parsed as $method => $parsedBody) {
            $this->assertValidOperationParams($parsedBody, $query, null, $variables, $operation, $method);
            expect($parsedBody->isReadOnly())->toBeFalse($method);
        }
    }

    public function testParsesGetRequest():void
    {
        $query = '{my query}';
        $variables = ['test' => 1, 'test2' => 2];
        $operation = 'op';

        $get = [
            'query' => $query,
            'variables' => $variables,
            'operation' => $operation
        ];
        $parsed = [
            'raw' => $this->parseRawGetRequest($get),
            'psr' => $this->parsePsrGetRequest($get)
        ];

        foreach ($parsed as $method => $parsedBody) {
            $this->assertValidOperationParams($parsedBody, $query, null, $variables, $operation, $method);
            expect($parsedBody->isReadonly())->toBeTrue($method);
        }
    }

    public function testParsesJSONRequest():void
    {
        $query = '{my query}';
        $variables = ['test' => 1, 'test2' => 2];
        $operation = 'op';

        $body = [
            'query' => $query,
            'variables' => $variables,
            'operation' => $operation
        ];
        $parsed = [
            'raw' => $this->parseRawRequest('application/json', \json_encode($body)),
            'psr' => $this->parsePsrRequest('application/json', \json_encode($body))
        ];
        foreach ($parsed as $method => $parsedBody) {
            $this->assertValidOperationParams($parsedBody, $query, null, $variables, $operation, $method);
            expect($parsedBody->isReadOnly())->toBeFalse($method);
        }
    }

    public function testParsesVariablesAsJSON():void
    {
        $query = '{my query}';
        $variables = ['test' => 1, 'test2' => 2];
        $operation = 'op';

        $body = [
            'query' => $query,
            'variables' => \json_encode($variables),
            'operation' => $operation
        ];
        $parsed = [
            'raw' => $this->parseRawRequest('application/json', \json_encode($body)),
            'psr' => $this->parsePsrRequest('application/json', \json_encode($body))
        ];
        foreach ($parsed as $method => $parsedBody) {
            $this->assertValidOperationParams($parsedBody, $query, null, $variables, $operation, $method);
            expect($parsedBody->isReadOnly())->toBeFalse($method);
        }
    }

    public function testIgnoresInvalidVariablesJson():void
    {
        $query = '{my query}';
        $variables = '"some invalid json';
        $operation = 'op';

        $body = [
            'query' => $query,
            'variables' => $variables,
            'operation' => $operation
        ];
        $parsed = [
            'raw' => $this->parseRawRequest('application/json', \json_encode($body)),
            'psr' => $this->parsePsrRequest('application/json', \json_encode($body)),
        ];
        foreach ($parsed as $method => $parsedBody) {
            $this->assertValidOperationParams($parsedBody, $query, null, $variables, $operation, $method);
            expect($parsedBody->isReadOnly())->toBeFalse($method);
        }
    }

    public function testParsesBatchJSONRequest():void
    {
        $body = [
            [
                'query' => '{my query}',
                'variables' => ['test' => 1, 'test2' => 2],
                'operation' => 'op'
            ],
            [
                'queryId' => 'my-query-id',
                'variables' => ['test' => 1, 'test2' => 2],
                'operation' => 'op2'
            ],
        ];
        $parsed = [
            'raw' => $this->parseRawRequest('application/json', \json_encode($body)),
            'psr' => $this->parsePsrRequest('application/json', \json_encode($body))
        ];
        foreach ($parsed as $method => $parsedBody) {
            expect($parsedBody)->toBeType('array',$method);
            expect(\count($parsedBody))->toBeSame(2, $method);
            $this->assertValidOperationParams($parsedBody[0], $body[0]['query'], null, $body[0]['variables'], $body[0]['operation'], $method);
            $this->assertValidOperationParams($parsedBody[1], null, $body[1]['queryId'], $body[1]['variables'], $body[1]['operation'], $method);
        }
    }

    public function testFailsParsingInvalidRawJsonRequestRaw():void
    {
        $body = 'not really{} a json';

        $this->setExpectedException(RequestError::class, 'Could not parse JSON: Syntax error');
            $this->parseRawRequest('application/json', $body);
        }

    public function testFailsParsingInvalidRawJsonRequestPsr():void
    {
        $body = 'not really{} a json';

        $this->setExpectedException(InvariantViolation::class, 'PSR-7 request is expected to provide parsed body for "application/json" requests but got null');
            $this->parsePsrRequest('application/json', $body);
    }

    public function testFailsParsingNonPreParsedPsrRequest():void
    {
        try {
            $this->parsePsrRequest('application/json', \json_encode(null));
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            // Expecting parsing exception to be thrown somewhere else:
            expect($e->getMessage())
                ->toBePHPEqual('PSR-7 request is expected to provide parsed body for "application/json" requests but got null');
        }
    }

    // There is no equivalent for psr request, because it should throw

    public function testFailsParsingNonArrayOrObjectJsonRequestRaw():void
    {
        $body = '"str"';

        $this->setExpectedException(RequestError::class, 'GraphQL Server expects JSON object or array, but got "str"');
            $this->parseRawRequest('application/json', $body);
        }

    public function testFailsParsingNonArrayOrObjectJsonRequestPsr():void
    {
        $body = '"str"';

        $this->setExpectedException(RequestError::class, 'GraphQL Server expects JSON object or array, but got "str"');
            $this->parsePsrRequest('application/json', $body);
        }

    public function testFailsParsingInvalidContentTypeRaw():void
    {
        $contentType = 'not-supported-content-type';
        $body = 'test';

        $this->setExpectedException(RequestError::class, 'Unexpected content type: "not-supported-content-type"');
        $this->parseRawRequest($contentType, $body);
    }

    public function testFailsParsingInvalidContentTypePsr():void
    {
        $contentType = 'not-supported-content-type';
        $body = 'test';

        $this->setExpectedException(RequestError::class, 'Unexpected content type: "not-supported-content-type"');
            $this->parseRawRequest($contentType, $body);
        }

    public function testFailsWithMissingContentTypeRaw():void
    {
        $this->setExpectedException(RequestError::class, 'Missing "Content-Type" header');
            $this->parseRawRequest(null, 'test');
        }

    public function testFailsWithMissingContentTypePsr():void
    {
        $this->setExpectedException(RequestError::class, 'Missing "Content-Type" header');
            $this->parsePsrRequest(null, 'test');
    }

    public function testFailsOnMethodsOtherThanPostOrGetRaw():void
    {
        $this->setExpectedException(RequestError::class, 'HTTP Method "PUT" is not supported');
        $this->parseRawRequest('application/json', \json_encode([]), "PUT");
    }

    public function testFailsOnMethodsOtherThanPostOrGetPsr():void
    {
        $this->setExpectedException(RequestError::class, 'HTTP Method "PUT" is not supported');
        $this->parsePsrRequest('application/json', \json_encode([]), "PUT");
    }

    /**
     * @param string $contentType
     * @param string $content
     * @param $method
     *
     * @return OperationParams|OperationParams[]
     */
    private function parseRawRequest($contentType, $content, $method = 'POST')
    {
        $_SERVER['CONTENT_TYPE'] = $contentType;
        $_SERVER['REQUEST_METHOD'] = $method;

        $helper = new Helper();
        return $helper->parseHttpRequest(function() use ($content) {
            return $content;
        });
    }

    /**
     * @param string $contentType
     * @param string $content
     * @param $method
     *
     * @return OperationParams|OperationParams[]
     */
    private function parsePsrRequest($contentType, $content, $method = 'POST')
    {
        $psrRequestBody = new PsrStreamStub();
        $psrRequestBody->content = $content;

        $psrRequest = new PsrRequestStub();
        $psrRequest->headers['content-type'] = [$contentType];
        $psrRequest->method = $method;
        $psrRequest->body = $psrRequestBody;

        if ($contentType === 'application/json') {
            $parsedBody = \json_decode($content, true);
            $parsedBody = $parsedBody === false ? null : $parsedBody;
        } else {
            $parsedBody = null;
        }

        $psrRequest->parsedBody = $parsedBody;

        $helper = new Helper();
        return $helper->parsePsrRequest($psrRequest);
    }

    /**
     * @param array $postValue
     * @return OperationParams|OperationParams[]
     */
    private function parseRawFormUrlencodedRequest($postValue)
    {
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $postValue;

        $helper = new Helper();
        return $helper->parseHttpRequest(function() {
            throw new InvariantViolation("Shouldn't read from php://input for urlencoded request");
        });
    }

    /**
     * @param $postValue
     * @return array|Helper
     */
    private function parsePsrFormUrlEncodedRequest($postValue)
    {
        $psrRequest = new PsrRequestStub();
        $psrRequest->headers['content-type'] = ['application/x-www-form-urlencoded'];
        $psrRequest->method = 'POST';
        $psrRequest->parsedBody = $postValue;

        $helper = new Helper();
        return $helper->parsePsrRequest($psrRequest);
    }

    /**
     * @param $getValue
     * @return OperationParams
     */
    private function parseRawGetRequest($getValue)
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = $getValue;

        $helper = new Helper();
        return $helper->parseHttpRequest(function() {
            throw new InvariantViolation("Shouldn't read from php://input for urlencoded request");
        });
    }

    /**
     * @param $getValue
     * @return array|Helper
     */
    private function parsePsrGetRequest($getValue)
    {
        $psrRequest = new PsrRequestStub();
        $psrRequest->method = 'GET';
        $psrRequest->queryParams = $getValue;

        $helper = new Helper();
        return $helper->parsePsrRequest($psrRequest);
    }

    /**
     * @param OperationParams $params
     * @param string $query
     * @param string $queryId
     * @param array $variables
     * @param string $operation
     */
    private function assertValidOperationParams($params, $query, $queryId = null, $variables = null, $operation = null, string $message = ''):void
    {
        expect($params)->toBeInstanceOf(OperationParams::class, $message);

        expect($params->query)->toBeSame($query, $message);
        expect($params->queryId)->toBeSame($queryId, $message);
        expect($params->variables)->toBeSame($variables, $message);
        expect($params->operation)->toBeSame($operation, $message);
    }
}
