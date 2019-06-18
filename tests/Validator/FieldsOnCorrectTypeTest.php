<?hh //strict
//decl
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\FieldsOnCorrectType;

class FieldsOnCorrectTypeTest extends TestCase
{
    // Validate: Fields on correct type

    /**
     * @it Object field selection
     */
    public function testObjectFieldSelection():void
    {
        $this->expectPassesRule(new FieldsOnCorrectType(), '
      fragment objectFieldSelection on Dog {
        __typename
        name
      }
        ');
    }

    /**
     * @it Aliased object field selection
     */
    public function testAliasedObjectFieldSelection():void
    {
        $this->expectPassesRule(new FieldsOnCorrectType(), '
      fragment aliasedObjectFieldSelection on Dog {
        tn : __typename
        otherName : name
      }
        ');
    }

    /**
     * @it Interface field selection
     */
    public function testInterfaceFieldSelection():void
    {
        $this->expectPassesRule(new FieldsOnCorrectType(), '
      fragment interfaceFieldSelection on Pet {
        __typename
        name
      }
        ');
    }

    /**
     * @it Aliased interface field selection
     */
    public function testAliasedInterfaceFieldSelection():void
    {
        $this->expectPassesRule(new FieldsOnCorrectType(), '
      fragment interfaceFieldSelection on Pet {
        otherName : name
      }
        ');
    }

    /**
     * @it Lying alias selection
     */
    public function testLyingAliasSelection():void
    {
        $this->expectPassesRule(new FieldsOnCorrectType(), '
      fragment lyingAliasSelection on Dog {
        name : nickname
      }
        ');
    }

    /**
     * @it Ignores fields on unknown type
     */
    public function testIgnoresFieldsOnUnknownType():void
    {
        $this->expectPassesRule(new FieldsOnCorrectType(), '
      fragment unknownSelection on UnknownType {
        unknownField
      }
        ');
    }

    /**
     * @it reports errors when type is known again
     */
    public function testReportsErrorsWhenTypeIsKnownAgain():void
    {
        $this->expectFailsRule(new FieldsOnCorrectType(), '
      fragment typeKnownAgain on Pet {
        unknown_pet_field {
          ... on Cat {
            unknown_cat_field
          }
        }
      }',
            [ $this->undefinedField('unknown_pet_field', 'Pet', [], 3, 9),
                $this->undefinedField('unknown_cat_field', 'Cat', [], 5, 13) ]
        );
    }

    /**
     * @it Field not defined on fragment
     */
    public function testFieldNotDefinedOnFragment():void
    {
        $this->expectFailsRule(new FieldsOnCorrectType(), '
      fragment fieldNotDefined on Dog {
        meowVolume
      }',
            [$this->undefinedField('meowVolume', 'Dog', [], 3, 9)]
        );
    }

    /**
     * @it Ignores deeply unknown field
     */
    public function testIgnoresDeeplyUnknownField():void
    {
        $this->expectFailsRule(new FieldsOnCorrectType(), '
      fragment deepFieldNotDefined on Dog {
        unknown_field {
          deeper_unknown_field
        }
      }',
            [$this->undefinedField('unknown_field', 'Dog', [], 3, 9)]
        );
    }

    /**
     * @it Sub-field not defined
     */
    public function testSubFieldNotDefined():void
    {
        $this->expectFailsRule(new FieldsOnCorrectType(), '
      fragment subFieldNotDefined on Human {
        pets {
          unknown_field
        }
      }',
            [$this->undefinedField('unknown_field', 'Pet', [], 4, 11)]
        );
    }

    /**
     * @it Field not defined on inline fragment
     */
    public function testFieldNotDefinedOnInlineFragment():void
    {
        $this->expectFailsRule(new FieldsOnCorrectType(), '
      fragment fieldNotDefined on Pet {
        ... on Dog {
          meowVolume
        }
      }',
            [$this->undefinedField('meowVolume', 'Dog', [], 4, 11)]
        );
    }

    /**
     * @it Aliased field target not defined
     */
    public function testAliasedFieldTargetNotDefined():void
    {
        $this->expectFailsRule(new FieldsOnCorrectType(), '
      fragment aliasedFieldTargetNotDefined on Dog {
        volume : mooVolume
      }',
            [$this->undefinedField('mooVolume', 'Dog', [], 3, 9)]
        );
    }

    /**
     * @it Aliased lying field target not defined
     */
    public function testAliasedLyingFieldTargetNotDefined():void
    {
        $this->expectFailsRule(new FieldsOnCorrectType(), '
      fragment aliasedLyingFieldTargetNotDefined on Dog {
        barkVolume : kawVolume
      }',
            [$this->undefinedField('kawVolume', 'Dog', [], 3, 9)]
        );
    }

    /**
     * @it Not defined on interface
     */
    public function testNotDefinedOnInterface():void
    {
        $this->expectFailsRule(new FieldsOnCorrectType(), '
      fragment notDefinedOnInterface on Pet {
        tailLength
      }',
            [$this->undefinedField('tailLength', 'Pet', [], 3, 9)]
        );
    }

    /**
     * @it Defined on implementors but not on interface
     */
    public function testDefinedOnImplmentorsButNotOnInterface():void
    {
        $this->expectFailsRule(new FieldsOnCorrectType(), '
      fragment definedOnImplementorsButNotInterface on Pet {
        nickname
      }',
            //[$this->undefinedField('nickname', 'Pet', [ 'Cat', 'Dog' ], 3, 9)]
            [$this->undefinedField('nickname', 'Pet', [ ], 3, 9)]
        );
    }

    /**
     * @it Meta field selection on union
     */
    public function testMetaFieldSelectionOnUnion():void
    {
        $this->expectPassesRule(new FieldsOnCorrectType(), '
      fragment directFieldSelectionOnUnion on CatOrDog {
        __typename
      }'
        );
    }

    /**
     * @it Direct field selection on union
     */
    public function testDirectFieldSelectionOnUnion():void
    {
        $this->expectFailsRule(new FieldsOnCorrectType(), '
      fragment directFieldSelectionOnUnion on CatOrDog {
        directField
      }',
            [$this->undefinedField('directField', 'CatOrDog', [], 3, 9)]
        );
    }

    /**
     * @it Defined on implementors queried on union
     */
    public function testDefinedOnImplementorsQueriedOnUnion():void
    {
        $this->expectFailsRule(new FieldsOnCorrectType(), '
      fragment definedOnImplementorsQueriedOnUnion on CatOrDog {
        name
      }',
            //[$this->undefinedField('name', 'CatOrDog', [ 'Being', 'Pet', 'Canine', 'Cat', 'Dog' ], 3, 9)]
            [$this->undefinedField('name', 'CatOrDog', [ ], 3, 9)]
        );
    }

    /**
     * @it valid field in inline fragment
     */
    public function testValidFieldInInlineFragment():void
    {
        $this->expectPassesRule(new FieldsOnCorrectType(), '
      fragment objectFieldSelection on Pet {
        ... on Dog {
          name
        }
      }
        ');
    }

    // Describe: Fields on correct type error message

    /**
     * @it Works with no suggestions
     */
    public function testWorksWithNoSuggestions():void
    {
        $this->assertEquals('Cannot query field "T" on type "f".', FieldsOnCorrectType::undefinedFieldMessage('T', 'f', []));
    }

    /**
     * @it Works with no small numbers of suggestions
     */
    public function testWorksWithNoSmallNumbersOfSuggestions():void
    {
        $expected = 'Cannot query field "T" on type "f". ' .
            'However, this field exists on "A", "B". ' .
            'Perhaps you meant to use an inline fragment?';

        $this->assertEquals($expected, FieldsOnCorrectType::undefinedFieldMessage('T', 'f', [ 'A', 'B' ]));
    }

    /**
     * @it Works with lots of suggestions
     */
    public function testWorksWithLotsOfSuggestions():void
    {
        $expected = 'Cannot query field "T" on type "f". ' .
            'However, this field exists on "A", "B", "C", "D", "E", ' .
            'and 1 other types. ' .
            'Perhaps you meant to use an inline fragment?';

        $this->assertEquals($expected, FieldsOnCorrectType::undefinedFieldMessage('T', 'f', [ 'A', 'B', 'C', 'D', 'E', 'F' ]));
    }

    private function undefinedField($field, $type, $suggestions, $line, $column)
    {
        return FormattedError::create(
            FieldsOnCorrectType::undefinedFieldMessage($field, $type, $suggestions),
            [new SourceLocation($line, $column)]
        );
    }
}
