<?hh //partial
namespace GraphQL\Utils;

use GraphQL\Type\Schema;
use GraphQL\Type\Definition\AbstractType;
use GraphQL\Type\Definition\CompositeType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NoNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;

class TypeComparators
{
    /**
     * Provided two types, return true if the types are equal (invariant).
     *
     * @param GraphQlType $typeA
     * @param GraphQlType $typeB
     * @return bool
     */
    public static function isEqualType(GraphQlType $typeA, GraphQlType $typeB):bool
    {
        // Equivalent types are equal.
        if ($typeA === $typeB) {
            return true;
        }

        // If either type is non-null, the other must also be non-null.
        if ($typeA instanceof NoNull && $typeB instanceof NoNull) {
            return self::isEqualType($typeA->getWrappedType(), $typeB->getWrappedType());
        }

        // If either type is a list, the other must also be a list.
        if ($typeA instanceof ListOfType && $typeB instanceof ListOfType) {
            return self::isEqualType($typeA->getWrappedType(), $typeB->getWrappedType());
        }

        // Otherwise the types are not equal.
        return false;
    }

    /**
     * Provided a type and a super type, return true if the first type is either
     * equal or a subset of the second super type (covariant).
     *
     * @param Schema $schema
     * @param GraphQlType $maybeSubType
     * @param GraphQlType $superType
     * @return bool
     */
    public static function isTypeSubTypeOf(Schema $schema, $maybeSubType, $superType):bool
    {
        // Equivalent type is a valid subtype
        if ($maybeSubType === $superType) {
            return true;
        }

        // If superType is non-null, maybeSubType must also be nullable.
        if ($superType instanceof NoNull) {
            if ($maybeSubType instanceof NoNull) {
                return self::isTypeSubTypeOf($schema, $maybeSubType->getWrappedType(), $superType->getWrappedType());
            }
            return false;
        } else if ($maybeSubType instanceof NoNull) {
            // If superType is nullable, maybeSubType may be non-null.
            return self::isTypeSubTypeOf($schema, $maybeSubType->getWrappedType(), $superType);
        }

        // If superType type is a list, maybeSubType type must also be a list.
        if ($superType instanceof ListOfType) {
            if ($maybeSubType instanceof ListOfType) {
                return self::isTypeSubTypeOf($schema, $maybeSubType->getWrappedType(), $superType->getWrappedType());
            }
            return false;
        } else if ($maybeSubType instanceof ListOfType) {
            // If superType is not a list, maybeSubType must also be not a list.
            return false;
        }

        // If superType type is an abstract type, maybeSubType type may be a currently
        // possible object type.
        if (GraphQlType::isAbstractType($superType) && $maybeSubType instanceof ObjectType && $schema->isPossibleType($superType, $maybeSubType)) {
            return true;
        }

        // Otherwise, the child type is not a valid subtype of the parent type.
        return false;
    }

    /**
     * Provided two composite types, determine if they "overlap". Two composite
     * types overlap when the Sets of possible concrete types for each intersect.
     *
     * This is often used to determine if a fragment of a given type could possibly
     * be visited in a context of another type.
     *
     * This function is commutative.
     *
     * @param Schema $schema
     * @param CompositeType $typeA
     * @param CompositeType $typeB
     * @return bool
     */
    public static function doTypesOverlap(Schema $schema, $typeA, $typeB):bool
    {
        // Equivalent types overlap
        if ($typeA === $typeB) {
            return true;
        }

        if ($typeA instanceof AbstractType) {
            if ($typeB instanceof AbstractType) {
                // If both types are abstract, then determine if there is any intersection
                // between possible concrete types of each.
                foreach ($schema->getPossibleTypes($typeA) as $type) {
                    if ($schema->isPossibleType($typeB, $type)) {
                        return true;
                    }
                }
                return false;
            }

            /** @var $typeB ObjectType */
            // Determine if the latter type is a possible concrete type of the former.
            return $schema->isPossibleType($typeA, $typeB);
        }

        if ($typeB instanceof AbstractType) {
            /** @var $typeA ObjectType */
            // Determine if the former type is a possible concrete type of the latter.
            return $schema->isPossibleType($typeB, $typeA);
        }

        // Otherwise the types do not overlap.
        return false;
    }
}
