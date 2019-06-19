<?hh //strict
//partial
namespace GraphQL\Tests\Validator;

use GraphQL\Validator\Rules\QueryDepth;

class QueryDepthTest extends AbstractQuerySecurityTest
{
    /**
     * @param $max
     * @param $count
     *
     * @return string
     */
    protected function getErrorMessage(int $max, int $count):string
    {
        return QueryDepth::maxQueryDepthErrorMessage($max, $count);
    }

    /**
     * @param $maxDepth
     *
     * @return QueryDepth
     */
    protected function getRule(int $maxDepth):QueryDepth
    {
        return new QueryDepth($maxDepth);
    }

    /**
     * @param $queryDepth
     * @param int   $maxQueryDepth
     * @param array $expectedErrors
     * @dataProvider queryDataProvider
     */
    public function testSimpleQueries(int $queryDepth, int $maxQueryDepth = 7, array<array<string,mixed>> $expectedErrors = []):void
    {
        $this->assertDocumentValidator($this->buildRecursiveQuery($queryDepth), $maxQueryDepth, $expectedErrors);
    }

    /**
     * @param $queryDepth
     * @param int   $maxQueryDepth
     * @param array $expectedErrors
     * @dataProvider queryDataProvider
     */
    public function testFragmentQueries(int $queryDepth, int $maxQueryDepth = 7, array<array<string,mixed>> $expectedErrors = []):void
    {
        $this->assertDocumentValidator($this->buildRecursiveUsingFragmentQuery($queryDepth), $maxQueryDepth, $expectedErrors);
    }

    /**
     * @param $queryDepth
     * @param int   $maxQueryDepth
     * @param array $expectedErrors
     * @dataProvider queryDataProvider
     */
    public function testInlineFragmentQueries(int $queryDepth, int $maxQueryDepth = 7, array<array<string,mixed>> $expectedErrors = []):void
    {
        $this->assertDocumentValidator($this->buildRecursiveUsingInlineFragmentQuery($queryDepth), $maxQueryDepth, $expectedErrors);
    }

    public function testComplexityIntrospectionQuery():void
    {
        $this->assertIntrospectionQuery(11);
    }

    public function testIntrospectionTypeMetaFieldQuery():void
    {
        $this->assertIntrospectionTypeMetaFieldQuery(1);
    }

    public function testTypeNameMetaFieldQuery():void
    {
        $this->assertTypeNameMetaFieldQuery(1);
    }

    public function queryDataProvider():array<array<mixed>>
    {
        return [
            [1], // Valid because depth under default limit (7)
            [2],
            [3],
            [4],
            [5],
            [6],
            [7],
            [8, 9], // Valid because depth under new limit (9)
            [10, 0], // Valid because 0 depth disable limit
            [
                10,
                8,
                [$this->createFormattedError(8, 10)],
            ], // failed because depth over limit (8)
            [
                20,
                15,
                [$this->createFormattedError(15, 20)],
            ], // failed because depth over limit (15)
        ];
    }

    private function buildRecursiveQuery(int $depth):string
    {
        $query = \sprintf('query MyQuery { human%s }', $this->buildRecursiveQueryPart($depth));

        return $query;
    }

    private function buildRecursiveUsingFragmentQuery(int $depth):string
    {
        $query = \sprintf(
            'query MyQuery { human { ...F1 } } fragment F1 on Human %s',
            $this->buildRecursiveQueryPart($depth)
        );

        return $query;
    }

    private function buildRecursiveUsingInlineFragmentQuery(int $depth):string
    {
        $query = \sprintf(
            'query MyQuery { human { ...on Human %s } }',
            $this->buildRecursiveQueryPart($depth)
        );

        return $query;
    }

    private function buildRecursiveQueryPart(int $depth):string
    {
        $templates = [
            'human' => ' { firstName%s } ',
            'dog' => ' dogs { name%s } ',
        ];

        $part = $templates['human'];

        for ($i = 1; $i <= $depth; ++$i) {
            $key = ($i % 2 == 1) ? 'human' : 'dog';
            $template = $templates[$key];

             /* HH_FIXME[4027]*/
            /* HH_FIXME[4110]*/
            $part = \sprintf($part, ('human' == $key ? ' owner ' : '').$template);
        }
        $part = \str_replace('%s', '', $part);

        return $part;
    }
}
