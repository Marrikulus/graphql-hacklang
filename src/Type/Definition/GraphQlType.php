<?hh //partial
namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\Node;

/**
 * Registry of standard GraphQL types
 * and a base class for all other types.
 *
 * @package GraphQL\Type\Definition
 */
abstract class GraphQlType implements \JsonSerializable
{
    const STRING = 'String';
    const INT = 'Int';
    const BOOLEAN = 'Boolean';
    const FLOAT = 'Float';
    const ID = 'ID';

    /**
     * @var array
     */
    private static ?array<string, GraphQlType> $internalTypes;

    /**
     * @var string
     */
    public string $name;

    /**
     * @var string|null
     */
    public ?string $description;

    /**
     * @var TypeDefinitionNode|null
     */
    public ?Node $astNode;

    /**
     * @var array
     */
    public $config;

    /**
     * @api
     * @return IDType
     */
    /* HH_FIXME[4110]*/
    public static function id():IDType
    {
        return self::getInternalType(self::ID);
    }

    /**
     * @api
     * @return StringType
     */
    /* HH_FIXME[4110]*/
    public static function string():StringType
    {
        return self::getInternalType(self::STRING);
    }

    /**
     * @api
     * @return BooleanType
     */
    /* HH_FIXME[4110]*/
    public static function boolean():BooleanType
    {
        return self::getInternalType(self::BOOLEAN);
    }

    /**
     * @api
     * @return IntType
     */
    /* HH_FIXME[4110]*/
    public static function int():IntType
    {
        return self::getInternalType(self::INT);
    }

    /**
     * @api
     * @return FloatType
     */
    /* HH_FIXME[4110]*/
    public static function float():FloatType
    {
        return self::getInternalType(self::FLOAT);
    }

    /**
     * @api
     * @param ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType|ListOfType|NoNull $wrappedType
     * @return ListOfType
     */
    public static function listOf($wrappedType):ListOfType
    {
        return new ListOfType($wrappedType);
    }

    /**
     * @api
     * @param ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType|ListOfType $wrappedType
     * @return NoNull
     */
    public static function nonNull($wrappedType):NoNull
    {
        return new NoNull($wrappedType);
    }

    /**
     * @param $name
     * @return array|IDType|StringType|FloatType|IntType|BooleanType
     */
    private static function getInternalType(string $name):GraphQlType
    {
        $internalTypes = self::getInternalTypes();
        return $internalTypes[$name];
    }

    /**
     * @return Type[]
     */
    public static function getInternalTypes():array<string, GraphQlType>
    {
        if (null === self::$internalTypes)
        {
            self::$internalTypes = [
                self::ID => new IDType(),
                self::STRING => new StringType(),
                self::FLOAT => new FloatType(),
                self::INT => new IntType(),
                self::BOOLEAN => new BooleanType()
            ];
        }
        return self::$internalTypes;
    }

    /**
     * @api
     * @param Type $type
     * @return bool
     */
    public static function isInputType(?GraphQlType $type):bool
    {
        $nakedType = self::getNamedType($type);
        return $nakedType instanceof InputType;
    }

    /**
     * @api
     * @param Type $type
     * @return bool
     */
    public static function isOutputType(?GraphQlType $type):bool
    {
        $nakedType = self::getNamedType($type);
        return $nakedType instanceof OutputType;
    }

    /**
     * @api
     * @param $type
     * @return bool
     */
    public static function isLeafType(?GraphQlType $type):bool
    {
        return $type instanceof LeafType;
    }

    /**
     * @api
     * @param Type $type
     * @return bool
     */
    public static function isCompositeType(?GraphQlType $type):bool
    {
        return $type instanceof CompositeType;
    }

    /**
     * @api
     * @param Type $type
     * @return bool
     */
    public static function isAbstractType(GraphQlType $type):bool
    {
        return $type instanceof AbstractType;
    }

    /**
     * @api
     * @param Type $type
     * @return ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType|ListOfType
     */
    public static function getNullableType($type)
    {
        return $type instanceof NoNull ? $type->getWrappedType() : $type;
    }

    /**
     * @api
     * @param Type $type
     * @return ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType
     */
    public static function getNamedType(?GraphQlType $type)
    {
        if (null === $type) {
            return null;
        }
        while ($type instanceof WrappingType) {
            $type = $type->getWrappedType();
        }

        return $type;
    }

    /**
     * @return null|string
     */
    protected function tryInferName():?string
    {
        if ($this->name) {
            return $this->name;
        }

        // If class is extended - infer name from className
        // QueryType -> Type
        // SomeOtherType -> SomeOther
        $tmp = new \ReflectionClass($this);
        $name = $tmp->getShortName();

        if ($tmp->getNamespaceName() !== __NAMESPACE__) {
            return \preg_replace('~Type$~', '', $name);
        }
        return null;
    }

    /**
     * @throws InvariantViolation
     */
    public function assertValid():void
    {
    }

    /**
     * @return string
     */
    public function toString():string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function jsonSerialize():string
    {
        return $this->toString();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->toString();
        } catch (\Exception $e) {
            echo $e;
        }
    }
}
