<?hh // strict
namespace GraphQL\Language\AST;

interface HasDirectives
{
	require extends Node;
    /**
     * export type HasDirectives = EnumTypeDefinitionNode
	 * 						| EnumValueDefinitionNode
	 * 						| FieldDefinitionNode
	 * 						| FieldNode
	 * 						| FragmentDefinitionNode
	 * 						| FragmentSpreadNode
	 * 						| InlineFragmentNode
	 * 						| InputObjectTypeDefinitionNode
	 * 						| InputValueDefinitionNode
	 * 						| InterfaceTypeDefinitionNode
	 * 						| ObjectTypeDefinitionNode
	 * 						| OperationDefinitionNode
	 * 						| ScalarTypeDefinitionNode
	 * 						| SchemaDefinitionNode
	 * 						| UnionTypeDefinitionNode
     */

    public function getDirectives():array<DirectiveNode>;
}
