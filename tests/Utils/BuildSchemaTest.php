<?hh //strict
namespace GraphQL\Tests\Utils;

use GraphQL\GraphQL;
use function Facebook\FBExpect\expect;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaPrinter;
use GraphQL\Type\Definition\Directive;

class BuildSchemaTest extends \Facebook\HackTest\HackTest
{
    // Describe: Schema Builder

    private function cycleOutput(string $body):string
    {
        $ast = Parser::parse($body);
        $schema = BuildSchema::buildAST($ast);
        return "\n" . SchemaPrinter::doPrint($schema);
    }

    /**
     * @it can use built schema for limited execution
     */
    public function testUseBuiltSchemaForLimitedExecution():void
    {
        $schema = BuildSchema::buildAST(Parser::parse('
            schema { query: Query }
            type Query {
                str: String
            }
        '));
        
        $result = GraphQL::execute($schema, '{ str }', ['str' => 123]);
        expect(['str' => 123])->toBePHPEqual($result['data']);
    }

    /**
     * @it can build a schema directly from the source
     */
    public function testBuildSchemaDirectlyFromSource():void
    {
        $schema = BuildSchema::build("
            schema { query: Query }
            type Query {
                add(x: Int, y: Int): Int
            }
        ");

        $result = GraphQL::execute(
            $schema,
            '{ add(x: 34, y: 55) }',
            [
                'add' => function ($root, $args) {
                    return $args['x'] + $args['y'];
                }
            ]
        );
        expect(['data' => ['add' => 89]])->toBePHPEqual($result);
    }

    /**
     * @it Simple Type
     */
    public function testSimpleType():void
    {
        $body = '
schema {
  query: HelloScalars
}

type HelloScalars {
  str: String
  int: Int
  float: Float
  id: ID
  bool: Boolean
}
';
        $output = $this->cycleOutput($body);
        expect($body)->toBePHPEqual($output);
    }

    /**
     * @it With directives
     */
    public function testWithDirectives():void
    {
        $body = '
schema {
  query: Hello
}

directive @foo(arg: Int) on FIELD

type Hello {
  str: String
}
';
        $output = $this->cycleOutput($body);
        expect($body)->toBePHPEqual($output);
    }

    /**
     * @it Supports descriptions
     */
    public function testSupportsDescriptions():void
    {
        $body = '
schema {
  query: Hello
}

# This is a directive
directive @foo(
  # It has an argument
  arg: Int
) on FIELD

# With an enum
enum Color {
  RED

  # Not a creative color
  GREEN
  BLUE
}

# What a great type
type Hello {
  # And a field to boot
  str: String
}
';
        $output = $this->cycleOutput($body);
        expect($output)->toBePHPEqual($body);
    }

    /**
     * @it Maintains @skip & @include
     */
    public function testMaintainsSkipAndInclude():void
    {
        $body = '
schema {
  query: Hello
}

type Hello {
  str: String
}
';
        $schema = BuildSchema::buildAST(Parser::parse($body));
        expect(3)->toBePHPEqual(\count($schema->getDirectives()));
        expect(Directive::skipDirective())->toBePHPEqual($schema->getDirective('skip'));
        expect(Directive::includeDirective())->toBePHPEqual($schema->getDirective('include'));
        expect(Directive::deprecatedDirective())->toBePHPEqual($schema->getDirective('deprecated'));
    }

    /**
     * @it Overriding directives excludes specified
     */
    public function testOverridingDirectivesExcludesSpecified():void
    {
        $body = '
schema {
  query: Hello
}

directive @skip on FIELD
directive @include on FIELD
directive @deprecated on FIELD_DEFINITION

type Hello {
  str: String
}
    ';
        $schema = BuildSchema::buildAST(Parser::parse($body));
        expect(3)->toBePHPEqual(\count($schema->getDirectives()));
        expect(Directive::skipDirective())->toNotBePHPEqual($schema->getDirective('skip'));
        expect(Directive::includeDirective())->toNotBePHPEqual($schema->getDirective('include'));
        expect(Directive::deprecatedDirective())->toNotBePHPEqual($schema->getDirective('deprecated'));
    }

    /**
     * @it Type modifiers
     */
    public function testTypeModifiers():void
    {
        $body = '
schema {
  query: HelloScalars
}

type HelloScalars {
  nonNullStr: String!
  listOfStrs: [String]
  listOfNonNullStrs: [String!]
  nonNullListOfStrs: [String]!
  nonNullListOfNonNullStrs: [String!]!
}
';
        $output = $this->cycleOutput($body);
        expect($body)->toBePHPEqual($output);
    }

    /**
     * @it Recursive type
     */
    public function testRecursiveType():void
    {
        $body = '
schema {
  query: Recurse
}

type Recurse {
  str: String
  recurse: Recurse
}
';
        $output = $this->cycleOutput($body);
        expect($body)->toBePHPEqual($output);
    }

    /**
     * @it Two types circular
     */
    public function testTwoTypesCircular():void
    {
        $body = '
schema {
  query: TypeOne
}

type TypeOne {
  str: String
  typeTwo: TypeTwo
}

type TypeTwo {
  str: String
  typeOne: TypeOne
}
';
        $output = $this->cycleOutput($body);
        expect($body)->toBePHPEqual($output);
    }

    /**
     * @it Single argument field
     */
    public function testSingleArgumentField():void
    {
        $body = '
schema {
  query: Hello
}

type Hello {
  str(int: Int): String
  floatToStr(float: Float): String
  idToStr(id: ID): String
  booleanToStr(bool: Boolean): String
  strToStr(bool: String): String
}
';
        $output = $this->cycleOutput($body);
        expect($body)->toBePHPEqual($output);
    }

    /**
     * @it Simple type with multiple arguments
     */
    public function testSimpleTypeWithMultipleArguments():void
    {
        $body = '
schema {
  query: Hello
}

type Hello {
  str(int: Int, bool: Boolean): String
}
';
        $output = $this->cycleOutput($body);
        expect($body)->toBePHPEqual($output);
    }

    /**
     * @it Simple type with interface
     */
    public function testSimpleTypeWithInterface():void
    {
        $body = '
schema {
  query: Hello
}

type Hello implements WorldInterface {
  str: String
}

interface WorldInterface {
  str: String
}
';
        $output = $this->cycleOutput($body);
        expect($body)->toBePHPEqual($output);
    }

    /**
     * @it Simple output enum
     */
    public function testSimpleOutputEnum():void
    {
        $body = '
schema {
  query: OutputEnumRoot
}

enum Hello {
  WORLD
}

type OutputEnumRoot {
  hello: Hello
}
';
        $output = $this->cycleOutput($body);
        expect($body)->toBePHPEqual($output);
    }

    /**
     * @it Multiple value enum
     */
    public function testMultipleValueEnum():void
    {
        $body = '
schema {
  query: OutputEnumRoot
}

enum Hello {
  WO
  RLD
}

type OutputEnumRoot {
  hello: Hello
}
';
        $output = $this->cycleOutput($body);
        expect($body)->toBePHPEqual($output);
    }

    /**
     * @it Simple Union
     */
    public function testSimpleUnion():void
    {
        $body = '
schema {
  query: Root
}

union Hello = World

type Root {
  hello: Hello
}

type World {
  str: String
}
';
        $output = $this->cycleOutput($body);
        expect($body)->toBePHPEqual($output);
    }

    /**
     * @it Multiple Union
     */
    public function testMultipleUnion():void
    {
        $body = '
schema {
  query: Root
}

union Hello = WorldOne | WorldTwo

type Root {
  hello: Hello
}

type WorldOne {
  str: String
}

type WorldTwo {
  str: String
}
';
        $output = $this->cycleOutput($body);
        expect($body)->toBePHPEqual($output);
    }

    /**
     * @it CustomScalar
     */
    public function testCustomScalar():void
    {
        $body = '
schema {
  query: Root
}

scalar CustomScalar

type Root {
  customScalar: CustomScalar
}
';
        $output = $this->cycleOutput($body);
        expect($body)->toBePHPEqual($output);
    }

    /**
     * @it CustomScalar
     */
    public function testInputObject():void
    {
        $body = '
schema {
  query: Root
}

input Input {
  int: Int
}

type Root {
  field(in: Input): String
}
';
        $output = $this->cycleOutput($body);
        expect($body)->toBePHPEqual($output);
    }

    /**
     * @it Simple argument field with default
     */
    public function testSimpleArgumentFieldWithDefault():void
    {
        $body = '
schema {
  query: Hello
}

type Hello {
  str(int: Int = 2): String
}
';
        $output = $this->cycleOutput($body);
        expect($body)->toBePHPEqual($output);
    }

    /**
     * @it Simple type with mutation
     */
    public function testSimpleTypeWithMutation():void
    {
        $body = '
schema {
  query: HelloScalars
  mutation: Mutation
}

type HelloScalars {
  str: String
  int: Int
  bool: Boolean
}

type Mutation {
  addHelloScalars(str: String, int: Int, bool: Boolean): HelloScalars
}
';
        $output = $this->cycleOutput($body);
        expect($body)->toBePHPEqual($output);
    }

    /**
     * @it Simple type with subscription
     */
    public function testSimpleTypeWithSubscription():void
    {
        $body = '
schema {
  query: HelloScalars
  subscription: Subscription
}

type HelloScalars {
  str: String
  int: Int
  bool: Boolean
}

type Subscription {
  subscribeHelloScalars(str: String, int: Int, bool: Boolean): HelloScalars
}
';
        $output = $this->cycleOutput($body);
        expect($body)->toBePHPEqual($output);
    }

    /**
     * @it Unreferenced type implementing referenced interface
     */
    public function testUnreferencedTypeImplementingReferencedInterface():void
    {
        $body = '
type Concrete implements Iface {
  key: String
}

interface Iface {
  key: String
}

type Query {
  iface: Iface
}
';
        $output = $this->cycleOutput($body);
        expect($body)->toBePHPEqual($output);
    }

    /**
     * @it Unreferenced type implementing referenced union
     */
    public function testUnreferencedTypeImplementingReferencedUnion():void
    {
        $body = '
type Concrete {
  key: String
}

type Query {
  union: Union
}

union Union = Concrete
';
        $output = $this->cycleOutput($body);
        expect($body)->toBePHPEqual($output);
    }

    /**
     * @it Supports @deprecated
     */
    public function testSupportsDeprecated():void
    {
        $body = '
enum MyEnum {
  VALUE
  OLD_VALUE @deprecated
  OTHER_VALUE @deprecated(reason: "Terrible reasons")
}

type Query {
  field1: String @deprecated
  field2: Int @deprecated(reason: "Because I said so")
  enum: MyEnum
}
';
        $output = $this->cycleOutput($body);
        expect($body)->toBePHPEqual($output);

        $ast = Parser::parse($body);
        $schema = BuildSchema::buildAST($ast);

        /** @var EnumType $myEnum */
        $myEnum = $schema->getType('MyEnum');

        $value = $myEnum->getValue('VALUE');
        expect($value->isDeprecated())->toBeFalse();

        $oldValue = $myEnum->getValue('OLD_VALUE');
        expect($oldValue->isDeprecated())->toBeTrue();
        expect($oldValue->deprecationReason)->toBePHPEqual('No longer supported');

        $otherValue = $myEnum->getValue('OTHER_VALUE');
        expect($otherValue->isDeprecated())->toBeTrue();
        expect($otherValue->deprecationReason)->toBePHPEqual('Terrible reasons');

        $rootFields = $schema->getType('Query')->getFields();
        expect(true)->toBePHPEqual($rootFields['field1']->isDeprecated());
        expect('No longer supported')->toBePHPEqual($rootFields['field1']->deprecationReason);

        expect(true)->toBePHPEqual($rootFields['field2']->isDeprecated());
        expect('Because I said so')->toBePHPEqual($rootFields['field2']->deprecationReason);
    }

    /**
     * @it Correctly assign AST nodes
     */
    public function testCorrectlyAssignASTNodes():void
    {

        $schema = BuildSchema::build('
      schema {
        query: Query
      }

      type Query {
        testField(testArg: TestInput): TestUnion
      }

      input TestInput {
        testInputField: TestEnum
      }

      enum TestEnum {
        TEST_VALUE
      }

      union TestUnion = TestType

      interface TestInterface {
        interfaceField: String
      }

      type TestType implements TestInterface {
        interfaceField: String
      }

      directive @test(arg: Int) on FIELD
    ');
        /** @var ObjectType $query */
        $query = $schema->getType('Query');
        $testInput = $schema->getType('TestInput');
        $testEnum = $schema->getType('TestEnum');
        $testUnion = $schema->getType('TestUnion');
        $testInterface = $schema->getType('TestInterface');
        $testType = $schema->getType('TestType');
        $testDirective = $schema->getDirective('test');

        $restoredIDL = SchemaPrinter::doPrint(BuildSchema::build(
            Printer::doPrint($schema->getAstNode()) . "\n" .
            Printer::doPrint($query->astNode) . "\n" .
            Printer::doPrint($testInput->astNode) . "\n" .
            Printer::doPrint($testEnum->astNode) . "\n" .
            Printer::doPrint($testUnion->astNode) . "\n" .
            Printer::doPrint($testInterface->astNode) . "\n" .
            Printer::doPrint($testType->astNode) . "\n" .
            Printer::doPrint($testDirective->astNode)
        ));

        expect(SchemaPrinter::doPrint($schema))->toBePHPEqual($restoredIDL);

        $testField = $query->getField('testField');
        expect(Printer::doPrint($testField->astNode))->toBePHPEqual('testField(testArg: TestInput): TestUnion');
        expect(Printer::doPrint($testField->args[0]->astNode))->toBePHPEqual('testArg: TestInput');
        expect(Printer::doPrint($testInput->getField('testInputField')->astNode))->toBePHPEqual('testInputField: TestEnum');
        expect(Printer::doPrint($testEnum->getValue('TEST_VALUE')->astNode))->toBePHPEqual('TEST_VALUE');
        expect(Printer::doPrint($testInterface->getField('interfaceField')->astNode))->toBePHPEqual('interfaceField: String');
        expect(Printer::doPrint($testType->getField('interfaceField')->astNode))->toBePHPEqual('interfaceField: String');
        expect(Printer::doPrint($testDirective->args[0]->astNode))->toBePHPEqual('arg: Int');
    }

    // Describe: Failures

    /**
     * @it Requires a schema definition or Query type
     */
    public function testRequiresSchemaDefinitionOrQueryType():void
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Must provide schema definition with query type or a type named Query.');
        $body = '
type Hello {
  bar: Bar
}
';
        $doc = Parser::parse($body);
        BuildSchema::buildAST($doc);
    }

    /**
     * @it Allows only a single schema definition
     */
    public function testAllowsOnlySingleSchemaDefinition():void
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Must provide only one schema definition.');
        $body = '
schema {
  query: Hello
}

schema {
  query: Hello
}

type Hello {
  bar: Bar
}
';
        $doc = Parser::parse($body);
        BuildSchema::buildAST($doc);
    }

    /**
     * @it Requires a query type
     */
    public function testRequiresQueryType():void
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Must provide schema definition with query type or a type named Query.');
        $body = '
schema {
  mutation: Hello
}

type Hello {
  bar: Bar
}
';
        $doc = Parser::parse($body);
        BuildSchema::buildAST($doc);
    }

    /**
     * @it Allows only a single query type
     */
    public function testAllowsOnlySingleQueryType():void
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Must provide only one query type in schema.');
        $body = '
schema {
  query: Hello
  query: Yellow
}

type Hello {
  bar: Bar
}

type Yellow {
  isColor: Boolean
}
';
        $doc = Parser::parse($body);
        BuildSchema::buildAST($doc);
    }

    /**
     * @it Allows only a single mutation type
     */
    public function testAllowsOnlySingleMutationType():void
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Must provide only one mutation type in schema.');
        $body = '
schema {
  query: Hello
  mutation: Hello
  mutation: Yellow
}

type Hello {
  bar: Bar
}

type Yellow {
  isColor: Boolean
}
';
        $doc = Parser::parse($body);
        BuildSchema::buildAST($doc);
    }

    /**
     * @it Allows only a single subscription type
     */
    public function testAllowsOnlySingleSubscriptionType():void
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Must provide only one subscription type in schema.');
        $body = '
schema {
  query: Hello
  subscription: Hello
  subscription: Yellow
}

type Hello {
  bar: Bar
}

type Yellow {
  isColor: Boolean
}
';
        $doc = Parser::parse($body);
        BuildSchema::buildAST($doc);
    }

    /**
     * @it Unknown type referenced
     */
    public function testUnknownTypeReferenced():void
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Type "Bar" not found in document.');
        $body = '
schema {
  query: Hello
}

type Hello {
  bar: Bar
}
';
        $doc = Parser::parse($body);
        $schema = BuildSchema::buildAST($doc);
        $schema->getTypeMap();
    }

    /**
     * @it Unknown type in interface list
     */
    public function testUnknownTypeInInterfaceList():void
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Type "Bar" not found in document.');
        $body = '
schema {
  query: Hello
}

type Hello implements Bar { }
';
        $doc = Parser::parse($body);
        $schema = BuildSchema::buildAST($doc);
        $schema->getTypeMap();
    }

    /**
     * @it Unknown type in union list
     */
    public function testUnknownTypeInUnionList():void
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Type "Bar" not found in document.');
        $body = '
schema {
  query: Hello
}

union TestUnion = Bar
type Hello { testUnion: TestUnion }
';
        $doc = Parser::parse($body);
        $schema = BuildSchema::buildAST($doc);
        $schema->getTypeMap();
    }

    /**
     * @it Unknown query type
     */
    public function testUnknownQueryType():void
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Specified query type "Wat" not found in document.');
        $body = '
schema {
  query: Wat
}

type Hello {
  str: String
}
';
        $doc = Parser::parse($body);
        BuildSchema::buildAST($doc);
    }

    /**
     * @it Unknown mutation type
     */
    public function testUnknownMutationType():void
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Specified mutation type "Wat" not found in document.');
        $body = '
schema {
  query: Hello
  mutation: Wat
}

type Hello {
  str: String
}
';
        $doc = Parser::parse($body);
        BuildSchema::buildAST($doc);
    }

    /**
     * @it Unknown subscription type
     */
    public function testUnknownSubscriptionType():void
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Specified subscription type "Awesome" not found in document.');
        $body = '
schema {
  query: Hello
  mutation: Wat
  subscription: Awesome
}

type Hello {
  str: String
}

type Wat {
  str: String
}
';
        $doc = Parser::parse($body);
        BuildSchema::buildAST($doc);
    }

    /**
     * @it Does not consider operation names
     */
    public function testDoesNotConsiderOperationNames():void
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Specified query type "Foo" not found in document.');
        $body = '
schema {
  query: Foo
}

query Foo { field }
';
        $doc = Parser::parse($body);
        BuildSchema::buildAST($doc);
    }

    /**
     * @it Does not consider fragment names
     */
    public function testDoesNotConsiderFragmentNames():void
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Specified query type "Foo" not found in document.');
        $body = '
schema {
  query: Foo
}

fragment Foo on Type { field }
';
        $doc = Parser::parse($body);
        BuildSchema::buildAST($doc);
    }

    /**
     * @it Forbids duplicate type definitions
     */
    public function testForbidsDuplicateTypeDefinitions():void
    {
        $body = '
schema {
  query: Repeated
}

type Repeated {
  id: Int
}

type Repeated {
  id: String
}
';
        $doc = Parser::parse($body);

        $this->setExpectedException('GraphQL\Error\Error', 'Type "Repeated" was defined more than once.');
        BuildSchema::buildAST($doc);
    }

    public function testSupportsTypeConfigDecorator():void
    {
        $body = '
schema {
  query: Query
}

type Query {
  str: String
  color: Color
  hello: Hello
}

enum Color {
  RED
  GREEN
  BLUE
}

interface Hello {
  world: String
}
';
        $doc = Parser::parse($body);

        $decorated = [];
        $calls = [];

        /* HH_FIXME[2087]*/
        $typeConfigDecorator = function($defaultConfig, $node, $allNodesMap) use (&$decorated, &$calls) {
            $decorated[] = $defaultConfig['name'];
            $calls[] = [$defaultConfig, $node, $allNodesMap];
            return ['description' => 'My description of ' . $node->name->value] + $defaultConfig;
        };

        $schema = BuildSchema::buildAST($doc, $typeConfigDecorator);
        $schema->getTypeMap();
        expect($decorated)->toBePHPEqual(['Query', 'Color', 'Hello']);

        list($defaultConfig, $node, $allNodesMap) = $calls[0];
        expect($node)->toBeInstanceOf(ObjectTypeDefinitionNode::class);
        expect($defaultConfig['name'])->toBePHPEqual('Query');
        expect($defaultConfig['fields'])->toBeInstanceOf(\Closure::class);
        expect($defaultConfig['interfaces'])->toBeInstanceOf(\Closure::class);
        expect($defaultConfig)->toContainKey('description');
        expect(\count($defaultConfig))->toBeSame(5);
        expect(['Query', 'Color', 'Hello'])->toBePHPEqual(\array_keys($allNodesMap));
        expect($schema->getType('Query')->description)->toBePHPEqual('My description of Query');


        list($defaultConfig, $node, $allNodesMap) = $calls[1];
        expect($node)->toBeInstanceOf(EnumTypeDefinitionNode::class);
        expect($defaultConfig['name'])->toBePHPEqual('Color');
        $enumValue = [
            'description' => '',
            'deprecationReason' => ''
        ];

        expect($defaultConfig['values'])->toInclude([
            'RED' => $enumValue,
            'GREEN' => $enumValue,
            'BLUE' => $enumValue,
        ]);
        expect(\count($defaultConfig))->toBeSame(4); // 3 + astNode

        expect(['Query', 'Color', 'Hello'])->toBePHPEqual(\array_keys($allNodesMap));
        expect($schema->getType('Color')->description)->toBePHPEqual('My description of Color');

        list($defaultConfig, $node, $allNodesMap) = $calls[2];
        expect($node)->toBeInstanceOf(InterfaceTypeDefinitionNode::class);
        expect($defaultConfig['name'])->toBePHPEqual('Hello');
        expect($defaultConfig['fields'])->toBeInstanceOf(\Closure::class);
        expect($defaultConfig['resolveType'])->toBeInstanceOf(\Closure::class);
        expect($defaultConfig)->toContainKey('description');
        expect(\count($defaultConfig))->toBeSame(5);
        expect(['Query', 'Color', 'Hello'])->toBePHPEqual(\array_keys($allNodesMap));
        expect($schema->getType('Hello')->description)->toBePHPEqual('My description of Hello');
    }

    public function testCreatesTypesLazily():void
    {
        $body = '
schema {
  query: Query
}

type Query {
  str: String
  color: Color
  hello: Hello
}

enum Color {
  RED
  GREEN
  BLUE
}

interface Hello {
  world: String
}

type World implements Hello {
  world: String
}
';
        $doc = Parser::parse($body);
        $created = [];

        $typeConfigDecorator = function($config, $node) use (&$created) {
            $created[] = $node->name->value;
            return $config;
        };

        $schema = BuildSchema::buildAST($doc, $typeConfigDecorator);
        expect($created)->toBePHPEqual(['Query']);

        $schema->getType('Color');
        expect($created)->toBePHPEqual(['Query', 'Color']);

        $schema->getType('Hello');
        expect($created)->toBePHPEqual(['Query', 'Color', 'Hello']);

        $types = $schema->getTypeMap();
        expect($created)->toBePHPEqual(['Query', 'Color', 'Hello', 'World']);
        expect($types)->toContainKey('Query');
        expect($types)->toContainKey('Color');
        expect($types)->toContainKey('Hello');
        expect($types)->toContainKey('World');
    }

    public function testScalarDescription():void
    {
        $schemaDef = '
# An ISO-8601 encoded UTC date string.
scalar Date

type Query {
    now: Date
    test: String
}
';
        $q = '
{
  __type(name: "Date") {
    name
    description
  }
  strType: __type(name: "String") {
    name
    description
  }
}
';
        $schema = BuildSchema::build($schemaDef);
        $result = GraphQL::executeQuery($schema, $q)->toArray();
        $expected = ['data' => [
            '__type' => [
                'name' => 'Date',
                'description' => 'An ISO-8601 encoded UTC date string.'
            ],
            'strType' => [
                'name' => 'String',
                'description' => 'The `String` scalar type represents textual data, represented as UTF-8' . "\n" .
                    'character sequences. The String type is most often used by GraphQL to'. "\n" .
                    'represent free-form human-readable text.'
            ]
        ]];
        expect($result)->toBePHPEqual($expected);
    }
}
