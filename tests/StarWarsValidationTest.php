<?hh //partial
//decl
namespace GraphQL\Tests;

use GraphQL\Language\Parser;
use function Facebook\FBExpect\expect;
use GraphQL\Validator\DocumentValidator;

class StarWarsValidationTest extends \Facebook\HackTest\HackTest
{
    // Star Wars Validation Tests
    // Basic Queries

    /**
     * @it Validates a complex but valid query
     */
    public function testValidatesAComplexButValidQuery():void
    {
        $query = '
        query NestedQueryWithFragment {
          hero {
            ...NameAndAppearances
            friends {
              ...NameAndAppearances
              friends {
                ...NameAndAppearances
              }
            }
          }
        }

        fragment NameAndAppearances on Character {
          name
          appearsIn
        }
      ';
        $errors = $this->validationErrors($query);
        expect(empty($errors))->toBePHPEqual(true);
    }

    /**
     * @it Notes that non-existent fields are invalid
     */
    public function testThatNonExistentFieldsAreInvalid():void
    {
        $query = '
        query HeroSpaceshipQuery {
          hero {
            favoriteSpaceship
          }
        }
        ';
        $errors = $this->validationErrors($query);
        expect(empty($errors))->toBePHPEqual(false);
    }

    /**
     * @it Requires fields on objects
     */
    public function testRequiresFieldsOnObjects():void
    {
        $query = '
        query HeroNoFieldsQuery {
          hero
        }
        ';

        $errors = $this->validationErrors($query);
        expect(empty($errors))->toBePHPEqual(false);
    }

    /**
     * @it Disallows fields on scalars
     */
    public function testDisallowsFieldsOnScalars():void
    {
      $query = '
        query HeroFieldsOnScalarQuery {
          hero {
            name {
              firstCharacterOfName
            }
          }
        }
        ';
        $errors = $this->validationErrors($query);
        expect(empty($errors))->toBePHPEqual(false);
    }

    /**
     * @it Disallows object fields on interfaces
     */
    public function testDisallowsObjectFieldsOnInterfaces():void
    {
        $query = '
        query DroidFieldOnCharacter {
          hero {
            name
            primaryFunction
          }
        }
        ';
        $errors = $this->validationErrors($query);
        expect(empty($errors))->toBePHPEqual(false);
    }

    /**
     * @it Allows object fields in fragments
     */
    public function testAllowsObjectFieldsInFragments():void
    {
        $query = '
        query DroidFieldInFragment {
          hero {
            name
            ...DroidFields
          }
        }

        fragment DroidFields on Droid {
          primaryFunction
        }
        ';
        $errors = $this->validationErrors($query);
        expect(empty($errors))->toBePHPEqual(true);
    }

    /**
     * @it Allows object fields in inline fragments
     */
    public function testAllowsObjectFieldsInInlineFragments():void
    {
        $query = '
        query DroidFieldInFragment {
          hero {
            name
            ... on Droid {
              primaryFunction
            }
          }
        }
        ';
        $errors = $this->validationErrors($query);
        expect(empty($errors))->toBePHPEqual(true);
    }

    /**
     * Helper function to test a query and the expected response.
     */
    private function validationErrors(string $query):array<Error>
    {
        $ast = Parser::parse($query);
        /* HH_FIXME[4110]*/
        return DocumentValidator::validate(StarWarsSchema::build(), $ast);
    }
}
