<?hh // strict
namespace GraphQL\Language\AST;

interface HasSelectionSet
{
	require extends Node;
    /**
     * export type DefinitionNode = OperationDefinitionNode
     *                        | FragmentDefinitionNode
     */

    public function getSelectionSet():SelectionSetNode;
}
