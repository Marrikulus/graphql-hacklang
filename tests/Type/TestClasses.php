<?hh //strict
//decl
namespace GraphQL\Tests\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;

class MyCustomType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'fields' => [
                'a' => GraphQlType::string()
            ]
        ];
        parent::__construct($config);
    }
}

// Note: named OtherCustom vs OtherCustomType intentionally
class OtherCustom extends ObjectType
{
    public function __construct()
    {
        $config = [
            'fields' => [
                'b' => GraphQlType::string()
            ]
        ];
        parent::__construct($config);
    }
}
