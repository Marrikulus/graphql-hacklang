<?hh //strict
//decl
namespace GraphQL\Tests\Server;

use GraphQL\Server\Helper;
use function Facebook\FBExpect\expect;
use GraphQL\Server\OperationParams;

class RequestValidationTest extends \Facebook\HackTest\HackTest
{
    public function testSimpleRequestShouldValidate():void
    {
        $query = '{my q}';
        $variables = ['a' => 'b', 'c' => 'd'];
        $operation = 'op';

        $parsedBody = OperationParams::create([
            'query' => $query,
            'variables' => $variables,
            'operation' => $operation,
        ]);

        $this->assertValid($parsedBody);
    }

    public function testRequestWithQueryIdShouldValidate():void
    {
        $queryId = 'some-query-id';
        $variables = ['a' => 'b', 'c' => 'd'];
        $operation = 'op';

        $parsedBody = OperationParams::create([
            'queryId' => $queryId,
            'variables' => $variables,
            'operation' => $operation,
        ]);

        $this->assertValid($parsedBody);
    }

    public function testRequiresQueryOrQueryId():void
    {
        $parsedBody = OperationParams::create([
            'variables' => ['foo' => 'bar'],
            'operation' => 'op',
        ]);

        $this->assertInputError(
            $parsedBody,
            'GraphQL Request must include at least one of those two parameters: "query" or "queryId"'
        );
    }

    public function testFailsWhenBothQueryAndQueryIdArePresent():void
    {
        $parsedBody = OperationParams::create([
            'query' => '{my query}',
            'queryId' => 'my-query-id',
        ]);

        $this->assertInputError(
            $parsedBody,
            'GraphQL Request parameters "query" and "queryId" are mutually exclusive'
        );
    }

    public function testFailsWhenQueryParameterIsNotString():void
    {
        $parsedBody = OperationParams::create([
            'query' => ['t' => '{my query}']
        ]);

        $this->assertInputError(
            $parsedBody,
            'GraphQL Request parameter "query" must be string, but got object with first key: "t"'
        );
    }

    public function testFailsWhenQueryIdParameterIsNotString():void
    {
        $parsedBody = OperationParams::create([
            'queryId' => ['t' => '{my query}']
        ]);

        $this->assertInputError(
            $parsedBody,
            'GraphQL Request parameter "queryId" must be string, but got object with first key: "t"'
        );
    }

    public function testFailsWhenOperationParameterIsNotString():void
    {
        $parsedBody = OperationParams::create([
            'query' => '{my query}',
            'operation' => []
        ]);

        $this->assertInputError(
            $parsedBody,
            'GraphQL Request parameter "operation" must be string, but got array(0)'
        );
    }

    /**
     * @see https://github.com/webonyx/graphql-php/issues/156
     */
    public function testIgnoresNullAndEmptyStringVariables():void
    {
        $query = '{my q}';
        $parsedBody = OperationParams::create([
            'query' => $query,
            'variables' => null
        ]);
        $this->assertValid($parsedBody);

        $variables = "";
        $parsedBody = OperationParams::create([
            'query' => $query,
            'variables' => $variables
        ]);
        $this->assertValid($parsedBody);
    }

    public function testFailsWhenVariablesParameterIsNotObject():void
    {
        $parsedBody = OperationParams::create([
            'query' => '{my query}',
            'variables' => 0
        ]);

        $this->assertInputError(
            $parsedBody,
            'GraphQL Request parameter "variables" must be object or JSON string parsed to object, but got 0'
        );
    }

    private function assertValid(OperationParams $parsedRequest):void
    {
        $helper = new Helper();
        $errors = $helper->validateOperationParams($parsedRequest);

        if (\array_key_exists(0, $errors))
        {
            throw $errors[0];
        }
    }

    private function assertInputError(OperationParams $parsedRequest, string $expectedMessage):void
    {
        $helper = new Helper();
        $errors = $helper->validateOperationParams($parsedRequest);


        if (\array_key_exists(0, $errors))
        {
            expect($errors[0]->getMessage())->toBePHPEqual($expectedMessage);
        }
        else
        {
            self::fail('Expected error not returned');
        }
    }
}
