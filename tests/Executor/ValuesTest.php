<?hh //strict
//decl
namespace GraphQL\Tests\Executor;

use GraphQL\Executor\Values;
use function Facebook\FBExpect\expect;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;
use GraphQL\Type\Schema;

class ValuesTest extends \Facebook\HackTest\HackTest {

  public function testGetIDVariableValues():void
  {
      $this->expectInputVariablesMatchOutputVariables(['idInput' => '123456789']);
      expect(self::runTestCase(['idInput' => 123456789]))->toBePHPEqual(
          ['idInput' => '123456789'],
          'Integer ID was not converted to string'
      );
  }

  public function testGetBooleanVariableValues():void
  {
      $this->expectInputVariablesMatchOutputVariables(['boolInput' => true]);
      $this->expectInputVariablesMatchOutputVariables(['boolInput' => false]);
  }

  public function testGetIntVariableValues():void
  {
      $this->expectInputVariablesMatchOutputVariables(['intInput' => -1]);
      $this->expectInputVariablesMatchOutputVariables(['intInput' => 0]);
      $this->expectInputVariablesMatchOutputVariables(['intInput' => 1]);

      // Test the int size limit
      $this->expectInputVariablesMatchOutputVariables(['intInput' => 2147483647]);
      $this->expectInputVariablesMatchOutputVariables(['intInput' => -2147483648]);
  }

  public function testGetStringVariableValues():void
  {
      $this->expectInputVariablesMatchOutputVariables(['stringInput' => 'meow']);
      $this->expectInputVariablesMatchOutputVariables(['stringInput' => '']);
      $this->expectInputVariablesMatchOutputVariables(['stringInput' => '1']);
      $this->expectInputVariablesMatchOutputVariables(['stringInput' => '0']);
      $this->expectInputVariablesMatchOutputVariables(['stringInput' => 'false']);
      $this->expectInputVariablesMatchOutputVariables(['stringInput' => '1.2']);
  }

  public function testGetFloatVariableValues():void
  {
      $this->expectInputVariablesMatchOutputVariables(['floatInput' => 1.2]);
      $this->expectInputVariablesMatchOutputVariables(['floatInput' => 1.0]);
      $this->expectInputVariablesMatchOutputVariables(['floatInput' => 1]);
      $this->expectInputVariablesMatchOutputVariables(['floatInput' => 0]);
      $this->expectInputVariablesMatchOutputVariables(['floatInput' => 1e3]);
  }

  public function testBooleanForIDVariableThrowsError():void
  {
      $this->expectGraphQLError(['idInput' => true]);
  }

  public function testFloatForIDVariableThrowsError():void
  {
      $this->expectGraphQLError(['idInput' => 1.0]);
  }

  public function testStringForBooleanVariableThrowsError():void
  {
      $this->expectGraphQLError(['boolInput' => 'true']);
  }

  public function testIntForBooleanVariableThrowsError():void
  {
      $this->expectGraphQLError(['boolInput' => 1]);
  }

  public function testFloatForBooleanVariableThrowsError():void
  {
      $this->expectGraphQLError(['boolInput' => 1.0]);
  }

  public function testBooleanForIntVariableThrowsError():void
  {
      $this->expectGraphQLError(['intInput' => true]);
  }

  public function testStringForIntVariableThrowsError():void
  {
      $this->expectGraphQLError(['intInput' => 'true']);
  }

  public function testFloatForIntVariableThrowsError():void
  {
      $this->expectGraphQLError(['intInput' => 1.0]);
  }

  public function testPositiveBigIntForIntVariableThrowsError():void
  {
      $this->expectGraphQLError(['intInput' => 2147483648]);
  }

  public function testNegativeBigIntForIntVariableThrowsError():void
  {
      $this->expectGraphQLError(['intInput' => -2147483649]);
  }

  public function testBooleanForStringVariableThrowsError():void
  {
      $this->expectGraphQLError(['stringInput' => true]);
  }

  public function testIntForStringVariableThrowsError():void
  {
      $this->expectGraphQLError(['stringInput' => 1]);
  }

  public function testFloatForStringVariableThrowsError():void
  {
      $this->expectGraphQLError(['stringInput' => 1.0]);
  }

  public function testBooleanForFloatVariableThrowsError():void
  {
      $this->expectGraphQLError(['floatInput' => true]);
  }

  public function testStringForFloatVariableThrowsError():void
  {
      $this->expectGraphQLError(['floatInput' => '1.0']);
  }

  // Helpers for running test cases and making assertions

  private function expectInputVariablesMatchOutputVariables($variables)
  {
      expect(self::runTestCase($variables))->toBePHPEqual(
          $variables,
          'Output variables did not match input variables' . \PHP_EOL . \var_export($variables, true) . \PHP_EOL
      );
  }

  private function expectGraphQLError($variables)
  {
      $this->setExpectedException(\GraphQL\Error\Error::class);
      self::runTestCase($variables);
  }

  private static $schema;

  private static function getSchema()
  {
      if (!self::$schema) {
          self::$schema = new Schema([
              'query' => new ObjectType([
                  'name' => 'Query',
                  'fields' => [
                      'test' => [
                          'type' => GraphQlType::boolean(),
                          'args' => [
                              'idInput' => GraphQlType::id(),
                              'boolInput' => GraphQlType::boolean(),
                              'intInput' => GraphQlType::int(),
                              'stringInput' => GraphQlType::string(),
                              'floatInput' => GraphQlType::float()
                          ]
                      ],
                  ]
              ])
          ]);
      }
      return self::$schema;
  }

  private static function getVariableDefinitionNodes()
  {
      $idInputDefinition = new VariableDefinitionNode(
          new VariableNode(new NameNode('idInput')),
          new NamedTypeNode(new NameNode('ID'))
      );
      $boolInputDefinition = new VariableDefinitionNode(
          new VariableNode(new NameNode('boolInput')),
          new NamedTypeNode(new NameNode('Boolean'))
      );
      $intInputDefinition = new VariableDefinitionNode(
          new VariableNode(new NameNode('intInput')),
          new NamedTypeNode(new NameNode('Int'))
      );
      $stringInputDefintion = new VariableDefinitionNode(
          new VariableNode(new NameNode('stringInput')),
          new NamedTypeNode(new NameNode('String'))
      );
      $floatInputDefinition = new VariableDefinitionNode(
          new VariableNode(new NameNode('floatInput')),
          new NamedTypeNode(new NameNode('Float'))
      );
      return [$idInputDefinition, $boolInputDefinition, $intInputDefinition, $stringInputDefintion, $floatInputDefinition];
  }

  private function runTestCase($variables)
  {
      return Values::getVariableValues(self::getSchema(), self::getVariableDefinitionNodes(), $variables);
  }
}