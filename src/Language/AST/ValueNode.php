<?hh // strict
namespace GraphQL\Language\AST;

/**
export type ValueNode = VariableNode
| NullValueNode
| IntValueNode
| FloatValueNode
| StringValueNode
| BooleanValueNode
| EnumValueNode
| ListValueNode
| ObjectValueNode
 */
interface ValueNode<T>
{
	require extends Node;
	public function getValue():T;
}
