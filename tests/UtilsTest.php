<?hh //strict
//decl
namespace GraphQL\Tests;

use GraphQL\Utils\Utils;

class UtilsTest extends \Facebook\HackTest\HackTest
{
    public function testAssignThrowsExceptionOnMissingRequiredKey():void
    {
        $object = new \stdClass();
        $object->requiredKey = 'value';

        $this->setExpectedException(\InvalidArgumentException::class, 'Key requiredKey is expected to be set and not to be null');
        Utils::assign($object, [], ['requiredKey']);
    }
}
