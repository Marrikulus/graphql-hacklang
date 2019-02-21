<?hh //decl
namespace GraphQL\Tests\Executor;

use GraphQL\Type\Definition\ScalarType;
use GraphQL\Language\AST\Node;

class Dog
{
    public $name;
    public $woofs;
    public function __construct($name, $woofs)
    {
        $this->name = $name;
        $this->woofs = $woofs;
    }
}

class Cat
{
    public $name;
    public $meows;
    public function __construct($name, $meows)
    {
        $this->name = $name;
        $this->meows = $meows;
    }
}

class Human
{
    public $name;
    public function __construct($name)
    {
        $this->name = $name;
    }
}

class Person
{
    public $name;
    public $pets;
    public $friends;

    public function __construct($name, $pets = null, $friends = null)
    {
        $this->name = $name;
        $this->pets = $pets;
        $this->friends = $friends;
    }
}

/* HH_FIXME[4236]*/
class ComplexScalar extends ScalarType
{
    public static function create():ComplexScalar
    {
        return new self();
    }

    public $name = 'ComplexScalar';

    public function serialize($value)
    {
        if ($value === 'DeserializedValue') {
            return 'SerializedValue';
        }
        return null;
    }

    public function parseValue($value):mixed
    {
        if ($value === 'SerializedValue') {
            return 'DeserializedValue';
        }
        return null;
    }

    public function parseLiteral(Node $valueNode):mixed
    {
        if ($valueNode->value === 'SerializedValue') {
            return 'DeserializedValue';
        }
        return null;
    }
}

class Special
{
    public $value;

    public function __construct($value)
    {
        $this->value = $value;
    }
}

class NotSpecial
{
    public $value;

    public function __construct($value)
    {
        $this->value = $value;
    }
}

class Adder
{
    public $num;

    public $test;

    public function __construct($num)
    {
        $this->num = $num;

        $this->test = function($source, $args, $context)  {
            return $this->num + $args['addend1'] + $context['addend2'];
        };
    }
}
