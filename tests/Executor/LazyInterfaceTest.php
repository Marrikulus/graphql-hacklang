<?hh //strict
//decl
/**
 * @author: Ivo Meißner
 * Date: 03.05.16
 * Time: 13:14
 */
namespace GraphQL\Tests\Executor;

use GraphQL\Executor\Executor;
use function Facebook\FBExpect\expect;
use GraphQL\Language\Parser;
use GraphQL\Schema;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;

class LazyInterfaceTest extends \Facebook\HackTest\HackTest
{
    /**
     * @var Schema
     */
    protected $schema;

    /**
     * @var InterfaceType
     */
    protected $lazyInterface;

    /**
     * @var ObjectType
     */
    protected $testObject;

    /**
     * Setup schema
     */
    public async function beforeEachTestAsync(): Awaitable<void>
    {
        $query = new ObjectType([
            'name' => 'query',
            'fields' => function () {
                return [
                    'lazyInterface' => [
                        'type' => $this->getLazyInterfaceType(),
                        'resolve' => function() {
                            return [];
                        }
                    ]
                ];
            }
        ]);

        $this->schema = new Schema(['query' => $query, 'types' => [$this->getTestObjectType()]]);
    }

    /**
     * Returns the LazyInterface
     *
     * @return InterfaceType
     */
    protected function getLazyInterfaceType()
    {
        if (!$this->lazyInterface) {
            $this->lazyInterface = new InterfaceType([
                'name' => 'LazyInterface',
                'fields' => [
                    'a' => GraphQlType::string()
                ],
                'resolveType' => function() {
                    return $this->getTestObjectType();
                },
            ]);
        }

        return $this->lazyInterface;
    }

    /**
     * Returns the test ObjectType
     * @return ObjectType
     */
    protected function getTestObjectType()
    {
        if (!$this->testObject) {
            $this->testObject = new ObjectType([
                'name' => 'TestObject',
                'fields' => [
                    'name' => [
                        'type' => GraphQlType::string(),
                        'resolve' => function() {
                            return 'testname';
                        }
                    ]
                ],
                'interfaces' => [$this->getLazyInterfaceType()]
            ]);
        }

        return $this->testObject;
    }

    /**
     * Handles execution of a lazily created interface
     */
    public function testReturnsFragmentsWithLazyCreatedInterface():void
    {
        $request = '
        {
            lazyInterface {
                ... on TestObject {
                    name
                }
            }
        }
        ';

        $expected = [
            'data' => [
                'lazyInterface' => [
                    'name' => 'testname'
                ]
            ]
        ];

        expect(Executor::execute($this->schema, Parser::parse($request))->toArray())->toBePHPEqual($expected);
    }
}
