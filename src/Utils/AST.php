<?hh //decl
namespace GraphQL\Utils;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\Location;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\IDType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\LeafType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NoNull;
use GraphQL\Type\Definition\GraphQlType;
use GraphQL\Type\Schema;
use GraphQL\Utils\Utils;

/**
 * Various utilities dealing with AST
 */
class AST
{
    /**
     * Convert representation of AST as an associative array to instance of GraphQL\Language\AST\Node.
     *
     * For example:
     *
     * ```php
     * AST::fromArray([
     *     'kind' => 'ListValue',
     *     'values' => [
     *         ['kind' => 'StringValue', 'value' => 'my str'],
     *         ['kind' => 'StringValue', 'value' => 'my other str']
     *     ],
     *     'loc' => ['start' => 21, 'end' => 25]
     * ]);
     * ```
     *
     * Will produce instance of `ListValueNode` where `values` prop is a lazily-evaluated `NodeList`
     * returning instances of `StringValueNode` on access.
     *
     * This is a reverse operation for AST::toArray($node)
     *
     * @api
     * @param array $node
     * @return Node
     */
    public static function fromArray(array<string, mixed> $node):Node
    {
        if (!\array_key_exists('kind', $node))
        {
            throw new InvariantViolation("Unexpected node structure: " . Utils::printSafeJson($node));
        }
        if (!\array_key_exists($node['kind'], NodeKind::$classMap))
        {
            throw new InvariantViolation("Unexpected node structure: " . Utils::printSafeJson($node));
        }

        $kind = (string)$node['kind'];
        $class = NodeKind::$classMap[$kind];
        $instance = new $class([]);

        if (\array_key_exists('loc', $node) &&
            $node['loc'] !== null &&
            \array_key_exists('start', $node['loc']) &&
            \array_key_exists('end', $node['loc']))
        {
            $instance->loc = Location::create($node['loc']['start'], $node['loc']['end']);
        }


        foreach ($node as $key => $value) {
            if ('loc' === $key || 'kind' === $key) {
                continue ;
            }
            if (\is_array($value))
            {
                if (\array_key_exists(0, $value) || empty($value))
                {
                    $value = new NodeList($value);
                }
                else
                {
                    $value = AST::fromArray($value);
                }
            }
            $instance->{$key} = $value;
        }
        return $instance;
    }

    /**
     * Convert AST node to serializable array
     *
     * @api
     * @param Node $node
     * @return array
     */
    public static function toArray(Node $node):array<string, mixed>
    {
        return $node->toArray(true);
    }

    /**
     * Produces a GraphQL Value AST given a PHP value.
     *
     * Optionally, a GraphQL type may be provided, which will be used to
     * disambiguate between value primitives.
     *
     * | PHP Value     | GraphQL Value        |
     * | ------------- | -------------------- |
     * | Object        | Input Object         |
     * | Assoc Array   | Input Object         |
     * | Array         | List                 |
     * | Boolean       | Boolean              |
     * | String        | String / Enum Value  |
     * | Int           | Int                  |
     * | Float         | Int / Float          |
     * | Mixed         | Enum Value           |
     * | null          | NullValue            |
     *
     * @api
     * @param $value
     * @param InputType $type
     * @return ObjectValueNode|ListValueNode|BooleanValueNode|IntValueNode|FloatValueNode|EnumValueNode|StringValueNode|NullValueNode
     */
    public static function astFromValue(mixed $value, InputType $type):?Node
    {
        if ($type instanceof NoNull) {
            $astValue = AST::astFromValue($value, $type->getWrappedType());
            if ($astValue instanceof NullValueNode) {
                return null;
            }
            return $astValue;
        }

        if ($value === null) {
            return new NullValueNode();
        }

        // Convert PHP array to GraphQL list. If the GraphQLType is a list, but
        // the value is not an array, convert the value using the list's item type.
        if ($type instanceof ListOfType) {
            $itemType = $type->getWrappedType();
            if (is_array($value) || ($value instanceof \Traversable)) {
                $valuesNodes = [];
                foreach ($value as $item) {
                    $itemNode = AST::astFromValue($item, $itemType);
                    if ($itemNode) {
                        $valuesNodes[] = $itemNode;
                    }
                }
                return new ListValueNode($valuesNodes);
            }
            return AST::astFromValue($value, $itemType);
        }

        // Populate the fields of the input object by creating ASTs from each value
        // in the PHP object according to the fields in the input type.
        if ($type instanceof InputObjectType) {
            $isArray = is_array($value);
            $isArrayLike = $isArray || $value instanceof \ArrayAccess;
            if ($value === null || (!$isArrayLike && !is_object($value))) {
                return null;
            }
            $fields = $type->getFields();
            $fieldNodes = [];
            foreach ($fields as $fieldName => $field) {
                if ($isArrayLike) {
                    $fieldValue = isset($value[$fieldName]) ? $value[$fieldName] : null;
                } else {
                    $fieldValue = isset($value->{$fieldName}) ? $value->{$fieldName} : null;
                }

                // Have to check additionally if key exists, since we differentiate between
                // "no key" and "value is null":
                if (null !== $fieldValue) {
                    $fieldExists = true;
                } else if ($isArray) {
                    $fieldExists = \array_key_exists($fieldName, $value);
                } else if ($isArrayLike) {
                    /** @var \ArrayAccess $value */
                    $fieldExists = $value->offsetExists($fieldName);
                } else {
                    $fieldExists = \property_exists($value, $fieldName);
                }

                if ($fieldExists) {
                    $fieldNode = AST::astFromValue($fieldValue, $field->getType());

                    if ($fieldNode)
                    {
                        $fieldNodes[] = new ObjectFieldNode( new NameNode($fieldName), $fieldNode);
                    }
                }
            }
            return new ObjectValueNode($fieldNodes);
        }

        // Since value is an internally represented value, it must be serialized
        // to an externally represented value before converting into an AST.
        if ($type instanceof LeafType) {
            $serialized = $type->serialize($value);
        } else {
            throw new InvariantViolation("Must provide Input Type, cannot use: " . Utils::printSafe($type));
        }

        if (null === $serialized) {
            return null;
        }

        // Others serialize based on their corresponding PHP scalar types.
        if ($serialized is bool)
        {
            return new BooleanValueNode($serialized);
        }
        if ($serialized is int)
        {
            return new IntValueNode((string)$serialized);
        }
        if ($serialized is float)
        {
            if ((int) $serialized == $serialized)
            {
                return new IntValueNode((string)$serialized);
            }
            return new FloatValueNode((string)$serialized);
        }
        if ($serialized is string)
        {
            // Enum types use Enum literals.
            if ($type instanceof EnumType)
            {
                return new EnumValueNode($serialized);
            }

            // ID types can use Int literals.
            $asInt = (int) $serialized;
            if ($type instanceof IDType && (string) $asInt === $serialized)
            {
                return new IntValueNode($serialized);
            }

            // Use json_encode, which uses the same string encoding as GraphQL,
            // then remove the quotes.
            return new StringValueNode(\substr(\json_encode($serialized), 1, -1));
        }

        throw new InvariantViolation('Cannot convert value to AST: ' . Utils::printSafe($serialized));
    }

    /**
     * Produces a PHP value given a GraphQL Value AST.
     *
     * A GraphQL type must be provided, which will be used to interpret different
     * GraphQL Value literals.
     *
     * Returns `null` when the value could not be validly coerced according to
     * the provided type.
     *
     * | GraphQL Value        | PHP Value     |
     * | -------------------- | ------------- |
     * | Input Object         | Assoc Array   |
     * | List                 | Array         |
     * | Boolean              | Boolean       |
     * | String               | String        |
     * | Int / Float          | Int / Float   |
     * | Enum Value           | Mixed         |
     * | Null Value           | null          |
     *
     * @api
     * @param $valueNode
     * @param InputType $type
     * @param null $variables
     * @return array|null|\stdClass
     * @throws \Exception
     */
    public static function valueFromAST(?Node $valueNode, GraphQlType $type, $variables = null):mixed
    {
        $undefined = Utils::undefined();

        if (!$valueNode) {
            // When there is no AST, then there is also no value.
            // Importantly, this is different from returning the GraphQL null value.
            return $undefined;
        }

        if ($type instanceof NoNull) {
            if ($valueNode instanceof NullValueNode) {
                // Invalid: intentionally return no value.
                return $undefined;
            }
            return AST::valueFromAST($valueNode, $type->getWrappedType(), $variables);
        }

        if ($valueNode instanceof NullValueNode) {
            // This is explicitly returning the value null.
            return null;
        }

        if ($valueNode instanceof VariableNode) {
            $variableName = $valueNode->name->value;

            if (!$variables || !\array_key_exists($variableName, $variables)) {
                // No valid return value.
                return $undefined;
            }
            // Note: we're not doing any checking that this variable is correct. We're
            // assuming that this query has been validated and the variable usage here
            // is of the correct type.
            return $variables[$variableName];
        }

        if ($type instanceof ListOfType) {
            $itemType = $type->getWrappedType();

            if ($valueNode instanceof ListValueNode) {
                $coercedValues = [];
                $itemNodes = $valueNode->values;
                foreach ($itemNodes as $itemNode) {
                    if (AST::isMissingVariable($itemNode, $variables)) {
                        // If an array contains a missing variable, it is either coerced to
                        // null or if the item type is non-null, it considered invalid.
                        if ($itemType instanceof NoNull) {
                            // Invalid: intentionally return no value.
                            return $undefined;
                        }
                        $coercedValues[] = null;
                    } else {
                        $itemValue = AST::valueFromAST($itemNode, $itemType, $variables);
                        if ($undefined === $itemValue) {
                            // Invalid: intentionally return no value.
                            return $undefined;
                        }
                        $coercedValues[] = $itemValue;
                    }
                }
                return $coercedValues;
            }
            $coercedValue = AST::valueFromAST($valueNode, $itemType, $variables);
            if ($undefined === $coercedValue) {
                // Invalid: intentionally return no value.
                return $undefined;
            }
            return [$coercedValue];
        }

        if ($type instanceof InputObjectType) {
            if (!$valueNode instanceof ObjectValueNode) {
                // Invalid: intentionally return no value.
                return $undefined;
            }

            $coercedObj = [];
            $fields = $type->getFields();
            $fieldNodes = Utils::keyMap($valueNode->fields, function($field) {return $field->name->value;});
            foreach ($fields as $field) {
                /** @var ValueNode $fieldNode */
                $fieldName = $field->name;
                $fieldNode = isset($fieldNodes[$fieldName]) ? $fieldNodes[$fieldName] : null;

                if (!$fieldNode || AST::isMissingVariable($fieldNode->value, $variables)) {
                    if ($field->defaultValueExists()) {
                        $coercedObj[$fieldName] = $field->defaultValue;
                    } else if ($field->getType() instanceof NoNull) {
                        // Invalid: intentionally return no value.
                        return $undefined;
                    }
                    continue ;
                }

                $fieldValue = AST::valueFromAST($fieldNode ? $fieldNode->value : null, $field->getType(), $variables);

                if ($undefined === $fieldValue) {
                    // Invalid: intentionally return no value.
                    return $undefined;
                }
                $coercedObj[$fieldName] = $fieldValue;
            }
            return $coercedObj;
        }

        if ($type instanceof LeafType)
        {
            $parsed = $type->parseLiteral($valueNode);

            if (null === $parsed && !$type->isValidLiteral($valueNode))
            {
                // Invalid values represent a failure to parse correctly, in which case
                // no value is returned.
                return $undefined;
            }

            return $parsed;
        }

        throw new InvariantViolation('Must be input type');
    }

    /**
     * Returns type definition for given AST Type node
     *
     * @api
     * @param Schema $schema
     * @param NamedTypeNode|ListTypeNode|NonNullTypeNode $inputTypeNode
     * @return Type
     * @throws InvariantViolation
     */
    public static function typeFromAST(Schema $schema, Node $inputTypeNode):?GraphQlType
    {
        if ($inputTypeNode instanceof ListTypeNode)
        {
            $innerType = AST::typeFromAST($schema, $inputTypeNode->type);
            return $innerType ? new ListOfType($innerType) : null;
        }
        if ($inputTypeNode instanceof NonNullTypeNode)
        {
            $innerType = AST::typeFromAST($schema, $inputTypeNode->type);
            return $innerType ? new NoNull($innerType) : null;
        }

        Utils::invariant($inputTypeNode !== null && $inputTypeNode instanceof NamedTypeNode, 'Must be a named type');
        invariant($inputTypeNode !== null && $inputTypeNode instanceof NamedTypeNode, 'Must be a named type');
        return $schema->getType($inputTypeNode->name->value);
    }

    /**
     * Returns true if the provided valueNode is a variable which is not defined
     * in the set of variables.
     * @param $valueNode
     * @param $variables
     * @return bool
     */
    private static function isMissingVariable(Node $valueNode, ?array<string, mixed> $variables):bool
    {
        return $valueNode instanceof VariableNode &&
        ($variables === null || !\array_key_exists($valueNode->name->value, $variables));
    }

    /**
     * Returns operation type ("query", "mutation" or "subscription") given a document and operation name
     *
     * @api
     * @param DocumentNode $document
     * @param string $operationName
     * @return string|null
     */
    public static function getOperation(DocumentNode $document, ?string $operationName = null):?string
    {
        if ($document->definitions)
        {
            foreach ($document->definitions as $def)
            {
                if ($def instanceof OperationDefinitionNode)
                {
                    $nameNode = $def->name;
                    if ($operationName === null || ($nameNode !== null && $nameNode->value === $operationName))
                    {
                        return $def->operation;
                    }
                }
            }
        }
        return null;
    }
}
