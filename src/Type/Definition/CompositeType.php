<?hh //strict
namespace GraphQL\Type\Definition;

/*
export type GraphQLCompositeType =
GraphQLObjectType |
GraphQLInterfaceType |
GraphQLUnionType;
*/
interface CompositeType
{
	require extends GraphQlType;
}
