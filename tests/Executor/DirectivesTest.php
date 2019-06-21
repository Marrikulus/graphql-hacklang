<?hh //strict
//decl
namespace GraphQL\Tests\Executor;

use GraphQL\Executor\Executor;
use function Facebook\FBExpect\expect;
use GraphQL\Language\Parser;
use GraphQL\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;

class DirectivesTest extends \Facebook\HackTest\HackTest
{
    // Describe: Execute: handles directives

    /**
     * @describe works without directives
     * @it basic query works
     */
    public function testWorksWithoutDirectives():void
    {
        expect($this->executeTestQuery('{ a, b }'))->toBePHPEqual(['data' => ['a' => 'a', 'b' => 'b']]);
    }

    /**
     * @describe works on scalars
     */
    public function testWorksOnScalars():void
    {
        // if true includes scalar
        expect($this->executeTestQuery('{ a, b @include(if: true) }'))->toBePHPEqual(['data' => ['a' => 'a', 'b' => 'b']]);

        // if false omits on scalar
        expect($this->executeTestQuery('{ a, b @include(if: false) }'))->toBePHPEqual(['data' => ['a' => 'a']]);

        // unless false includes scalar
        expect($this->executeTestQuery('{ a, b @skip(if: false) }'))->toBePHPEqual(['data' => ['a' => 'a', 'b' => 'b']]);

        // unless true omits scalar
        expect($this->executeTestQuery('{ a, b @skip(if: true) }'))->toBePHPEqual(['data' => ['a' => 'a']]);
    }

    /**
     * @describe works on fragment spreads
     */
    public function testWorksOnFragmentSpreads():void
    {
        // if false omits fragment spread
        $q = '
        query Q {
          a
          ...Frag @include(if: false)
        }
        fragment Frag on TestType {
          b
        }
        ';
        expect($this->executeTestQuery($q))->toBePHPEqual(['data' => ['a' => 'a']]);

        // if true includes fragment spread
        $q = '
        query Q {
          a
          ...Frag @include(if: true)
        }
        fragment Frag on TestType {
          b
        }
        ';
        expect($this->executeTestQuery($q))->toBePHPEqual(['data' => ['a' => 'a', 'b' => 'b']]);

        // unless false includes fragment spread
        $q = '
        query Q {
          a
          ...Frag @skip(if: false)
        }
        fragment Frag on TestType {
          b
        }
        ';
        expect($this->executeTestQuery($q))->toBePHPEqual(['data' => ['a' => 'a', 'b' => 'b']]);

        // unless true omits fragment spread
        $q = '
        query Q {
          a
          ...Frag @skip(if: true)
        }
        fragment Frag on TestType {
          b
        }
        ';
        expect($this->executeTestQuery($q))->toBePHPEqual(['data' => ['a' => 'a']]);
    }

    /**
     * @describe works on inline fragment
     */
    public function testWorksOnInlineFragment():void
    {
        // if false omits inline fragment
        $q = '
        query Q {
          a
          ... on TestType @include(if: false) {
            b
          }
        }
        ';
        expect($this->executeTestQuery($q))->toBePHPEqual(['data' => ['a' => 'a']]);

        // if true includes inline fragment
        $q = '
        query Q {
          a
          ... on TestType @include(if: true) {
            b
          }
        }
        ';
        expect($this->executeTestQuery($q))->toBePHPEqual(['data' => ['a' => 'a', 'b' => 'b']]);

        // unless false includes inline fragment
        $q = '
        query Q {
          a
          ... on TestType @skip(if: false) {
            b
          }
        }
        ';
        expect($this->executeTestQuery($q))->toBePHPEqual(['data' => ['a' => 'a', 'b' => 'b']]);

        // unless true includes inline fragment
        $q = '
        query Q {
          a
          ... on TestType @skip(if: true) {
            b
          }
        }
        ';
        expect($this->executeTestQuery($q))->toBePHPEqual(['data' => ['a' => 'a']]);
    }

    /**
     * @describe works on anonymous inline fragment
     */
    public function testWorksOnAnonymousInlineFragment():void
    {
        // if false omits anonymous inline fragment
        $q = '
        query Q {
          a
          ... @include(if: false) {
            b
          }
        }
        ';
        expect($this->executeTestQuery($q))->toBePHPEqual(['data' => ['a' => 'a']]);

        // if true includes anonymous inline fragment
        $q = '
        query Q {
          a
          ... @include(if: true) {
            b
          }
        }
        ';
        expect($this->executeTestQuery($q))->toBePHPEqual(['data' => ['a' => 'a', 'b' => 'b']]);

        // unless false includes anonymous inline fragment
        $q = '
        query Q {
          a
          ... @skip(if: false) {
            b
          }
        }
        ';
        expect($this->executeTestQuery($q))->toBePHPEqual(['data' => ['a' => 'a', 'b' => 'b']]);

        // unless true includes anonymous inline fragment
        $q = '
        query Q {
          a
          ... @skip(if: true) {
            b
          }
        }
        ';
        expect($this->executeTestQuery($q))->toBePHPEqual(['data' => ['a' => 'a']]);
    }

    /**
     * @describe works with skip and include directives
     */
    public function testWorksWithSkipAndIncludeDirectives():void
    {
        // include and no skip
        expect($this->executeTestQuery('{ a, b @include(if: true) @skip(if: false) }'))
            ->toBePHPEqual(['data' => ['a' => 'a', 'b' => 'b']]);

        // include and skip
        expect($this->executeTestQuery('{ a, b @include(if: true) @skip(if: true) }'))
            ->toBePHPEqual(['data' => ['a' => 'a']]);

        // no include or skip
        expect($this->executeTestQuery('{ a, b @include(if: false) @skip(if: false) }'))
            ->toBePHPEqual(['data' => ['a' => 'a']]);
    }




    private static $schema;

    private static $data;

    private static function getSchema()
    {
        if (!self::$schema) {
            self::$schema = new Schema([
                'query' => new ObjectType([
                    'name' => 'TestType',
                    'fields' => [
                        'a' => ['type' => GraphQlType::string()],
                        'b' => ['type' => GraphQlType::string()]
                    ]
                ])
            ]);
        }
        return self::$schema;
    }

    private static function getData()
    {
        return self::$data ?: (self::$data = [
            'a' => 'a',
            'b' => 'b'
        ]);
    }

    private function executeTestQuery(string $doc)
    {
        return Executor::execute(self::getSchema(), Parser::parse($doc), self::getData())->toArray();
    }
}
