<?hh //strict
//decl
namespace GraphQL\Tests\Language;

use GraphQL\Language\AST\DocumentNode;
use function Facebook\FBExpect\expect;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Language\Visitor;
use GraphQL\Tests\Validator\TestCase;
use GraphQL\Type\Definition\GraphQlType;
use GraphQL\Utils\TypeInfo;

class VisitorTest extends \Facebook\HackTest\HackTest
{
    /**
     * @it allows editing a node both on enter and on leave
     */
    public function testAllowsEditingNodeOnEnterAndOnLeave():void
    {
        $ast = Parser::parse('{ a, b, c { a, b, c } }', true);

        $selectionSet = null;
        $editedAst = Visitor::visit($ast, [
            NodeKind::OPERATION_DEFINITION => [
                /* HH_FIXME[2087]*/
                'enter' => function(OperationDefinitionNode $node) use (&$selectionSet) {
                    $selectionSet = $node->selectionSet;

                    $newNode = clone $node;
                    $newNode->selectionSet = new SelectionSetNode([]);
                    $newNode->didEnter = true;
                    return $newNode;
                },
                /* HH_FIXME[2087]*/
                'leave' => function(OperationDefinitionNode $node) use (&$selectionSet) {
                    $newNode = clone $node;
                    $newNode->selectionSet = $selectionSet;
                    $newNode->didLeave = true;
                    return $newNode;
                }
            ]
        ]);

        expect($editedAst)->toNotBePHPEqual($ast);

        $expected = $ast->cloneDeep();
        $expected->definitions[0]->didEnter = true;
        $expected->definitions[0]->didLeave = true;

        expect($editedAst)->toBePHPEqual($expected);
    }

    /**
     * @it allows editing the root node on enter and on leave
     */
    public function testAllowsEditingRootNodeOnEnterAndLeave():void
    {
        $ast = Parser::parse('{ a, b, c { a, b, c } }', true);
        $definitions = $ast->definitions;

        $editedAst = Visitor::visit($ast, [
            NodeKind::DOCUMENT => [
                'enter' => function (DocumentNode $node) {
                    $tmp = clone $node;
                    $tmp->definitions = [];
                    $tmp->didEnter = true;
                    return $tmp;
                },
                'leave' => function(DocumentNode $node) use ($definitions) {
                    $tmp = clone $node;
                    $node->definitions = $definitions;
                    $node->didLeave = true;
                }
            ]
        ]);

        expect($editedAst)->toNotBePHPEqual($ast);

        $tmp = $ast->cloneDeep();
        $tmp->didEnter = true;
        $tmp->didLeave = true;

        expect($editedAst)->toBePHPEqual($tmp);
    }

    /**
     * @it allows for editing on enter
     */
    public function testAllowsForEditingOnEnter():void
    {
        $ast = Parser::parse('{ a, b, c { a, b, c } }', true);
        $editedAst = Visitor::visit($ast, [
            'enter' => function($node) {
                if ($node instanceof FieldNode && $node->name->value === 'b') {
                    return Visitor::removeNode();
                }
            }
        ]);

        expect($ast)->toBePHPEqual(Parser::parse('{ a, b, c { a, b, c } }', true));
        expect($editedAst)->toBePHPEqual(Parser::parse('{ a,    c { a,    c } }', true));
    }

    /**
     * @it allows for editing on leave
     */
    public function testAllowsForEditingOnLeave():void
    {
        $ast = Parser::parse('{ a, b, c { a, b, c } }', true);
        $editedAst = Visitor::visit($ast, [
            'leave' => function($node) {
                if ($node instanceof FieldNode && $node->name->value === 'b') {
                    return Visitor::removeNode();
                }
            }
        ]);

        expect($ast)->toBePHPEqual(Parser::parse('{ a, b, c { a, b, c } }', true));

        expect($editedAst)->toBePHPEqual(Parser::parse('{ a,    c { a,    c } }', true));
    }

    /**
     * @it visits edited node
     */
    public function testVisitsEditedNode():void
    {
        $addedField = new FieldNode(new NameNode('__typename'));

        $didVisitAddedField = false;

        $ast = Parser::parse('{ a { x } }');

        Visitor::visit($ast, [
            /* HH_FIXME[2087]*/
            'enter' => function($node) use ($addedField, &$didVisitAddedField) {
                if ($node instanceof FieldNode && $node->name->value === 'a') {
                    return new FieldNode(
                        new NameNode("foo"),
                        null,
                        [],
                        [],
                        new SelectionSetNode(array_merge([$addedField], $node->selectionSet->selections))
                    );
                }
                if ($node === $addedField) {
                    $didVisitAddedField = true;
                }
            }
        ]);

        expect($didVisitAddedField)->toBeTrue();
    }

    /**
     * @it allows skipping a sub-tree
     */
    public function testAllowsSkippingASubTree():void
    {
        $visited = [];
        $ast = Parser::parse('{ a, b { x }, c }');

        Visitor::visit($ast, [
            /* HH_FIXME[2087]*/
            'enter' => function(Node $node) use (&$visited) {
                $visited[] = ['enter', $node->kind, isset($node->value) ? $node->value : null];
                if ($node instanceof FieldNode && $node->name->value === 'b') {
                    return Visitor::skipNode();
                }
            },
            /* HH_FIXME[2087]*/
            'leave' => function (Node $node) use (&$visited) {
                $visited[] = ['leave', $node->kind, isset($node->value) ? $node->value : null];
            }
        ]);

        $expected = [
            [ 'enter', 'Document', null ],
            [ 'enter', 'OperationDefinition', null ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'a' ],
            [ 'leave', 'Name', 'a' ],
            [ 'leave', 'Field', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'c' ],
            [ 'leave', 'Name', 'c' ],
            [ 'leave', 'Field', null ],
            [ 'leave', 'SelectionSet', null ],
            [ 'leave', 'OperationDefinition', null ],
            [ 'leave', 'Document', null ]
        ];

        expect($visited)->toBePHPEqual($expected);
    }

    /**
     * @it allows early exit while visiting
     */
    public function testAllowsEarlyExitWhileVisiting():void
    {
        $visited = [];
        $ast = Parser::parse('{ a, b { x }, c }');

        Visitor::visit($ast, [
            /* HH_FIXME[2087]*/
            'enter' => function(Node $node) use (&$visited) {
                $visited[] = ['enter', $node->kind, isset($node->value) ? $node->value : null];
                if ($node instanceof NameNode && $node->value === 'x') {
                    return Visitor::stop();
                }
            },
            /* HH_FIXME[2087]*/
            'leave' => function(Node $node) use (&$visited) {
                $visited[] = ['leave', $node->kind, isset($node->value) ? $node->value : null];
            }
        ]);

        $expected = [
            [ 'enter', 'Document', null ],
            [ 'enter', 'OperationDefinition', null ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'a' ],
            [ 'leave', 'Name', 'a' ],
            [ 'leave', 'Field', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'b' ],
            [ 'leave', 'Name', 'b' ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'x' ]
        ];

        expect($visited)->toBePHPEqual($expected);
    }

    /**
     * @it allows early exit while leaving
     */
    public function testAllowsEarlyExitWhileLeaving():void
    {
        $visited = [];

        $ast = Parser::parse('{ a, b { x }, c }');
        Visitor::visit($ast, [
            /* HH_FIXME[2087]*/
            'enter' => function($node) use (&$visited) {
                $visited[] = ['enter', $node->kind, isset($node->value) ? $node->value : null];
            },
            /* HH_FIXME[2087]*/
            'leave' => function($node) use (&$visited) {
                $visited[] = ['leave', $node->kind, isset($node->value) ? $node->value : null];

                if ($node->kind === NodeKind::NAME && $node->value === 'x') {
                    return Visitor::stop();
                }
            }
        ]);

        expect([
            [ 'enter', 'Document', null ],
            [ 'enter', 'OperationDefinition', null ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'a' ],
            [ 'leave', 'Name', 'a' ],
            [ 'leave', 'Field', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'b' ],
            [ 'leave', 'Name', 'b' ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'x' ],
            [ 'leave', 'Name', 'x' ]
        ])->toBePHPEqual($visited);
    }

    /**
     * @it allows a named functions visitor API
     */
    public function testAllowsANamedFunctionsVisitorAPI():void
    {
        $visited = [];
        $ast = Parser::parse('{ a, b { x }, c }');

        Visitor::visit($ast, [
            /* HH_FIXME[2087]*/
            NodeKind::NAME => function(NameNode $node) use (&$visited) {
                $visited[] = ['enter', $node->kind, $node->value];
            },
            NodeKind::SELECTION_SET => [
                /* HH_FIXME[2087]*/
                'enter' => function(SelectionSetNode $node) use (&$visited) {
                    $visited[] = ['enter', $node->kind, null];
                },
                /* HH_FIXME[2087]*/
                'leave' => function(SelectionSetNode $node) use (&$visited) {
                    $visited[] = ['leave', $node->kind, null];
                }
            ]
        ]);

        $expected = [
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Name', 'a' ],
            [ 'enter', 'Name', 'b' ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Name', 'x' ],
            [ 'leave', 'SelectionSet', null ],
            [ 'enter', 'Name', 'c' ],
            [ 'leave', 'SelectionSet', null ],
        ];

        expect($visited)->toBePHPEqual($expected);
    }

    /**
     * @it visits kitchen sink
     */
    public function testVisitsKitchenSink():void
    {
        $kitchenSink = \file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $ast = Parser::parse($kitchenSink);

        $visited = [];
        Visitor::visit($ast, [
            /* HH_FIXME[2087]*/
            'enter' => function(Node $node, $key, $parent) use (&$visited) {
                $r = ['enter', $node->kind, $key, $parent instanceof Node ? $parent->kind : null];
                $visited[] = $r;
            },
            /* HH_FIXME[2087]*/
            'leave' => function(Node $node, $key, $parent) use (&$visited) {
                $r = ['leave', $node->kind, $key, $parent instanceof Node ? $parent->kind : null];
                $visited[] = $r;
            }
        ]);

        $expected = [
            [ 'enter', 'Document', null, null ],
            [ 'enter', 'OperationDefinition', 0, null ],
            [ 'enter', 'Name', 'name', 'OperationDefinition' ],
            [ 'leave', 'Name', 'name', 'OperationDefinition' ],
            [ 'enter', 'VariableDefinition', 0, null ],
            [ 'enter', 'Variable', 'variable', 'VariableDefinition' ],
            [ 'enter', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Variable', 'variable', 'VariableDefinition' ],
            [ 'enter', 'NamedType', 'type', 'VariableDefinition' ],
            [ 'enter', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'NamedType', 'type', 'VariableDefinition' ],
            [ 'leave', 'VariableDefinition', 0, null ],
            [ 'enter', 'VariableDefinition', 1, null ],
            [ 'enter', 'Variable', 'variable', 'VariableDefinition' ],
            [ 'enter', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Variable', 'variable', 'VariableDefinition' ],
            [ 'enter', 'NamedType', 'type', 'VariableDefinition' ],
            [ 'enter', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'NamedType', 'type', 'VariableDefinition' ],
            [ 'enter', 'EnumValue', 'defaultValue', 'VariableDefinition' ],
            [ 'leave', 'EnumValue', 'defaultValue', 'VariableDefinition' ],
            [ 'leave', 'VariableDefinition', 1, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'OperationDefinition' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'alias', 'Field' ],
            [ 'leave', 'Name', 'alias', 'Field' ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'Argument', 0, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'ListValue', 'value', 'Argument' ],
            [ 'enter', 'IntValue', 0, null ],
            [ 'leave', 'IntValue', 0, null ],
            [ 'enter', 'IntValue', 1, null ],
            [ 'leave', 'IntValue', 1, null ],
            [ 'leave', 'ListValue', 'value', 'Argument' ],
            [ 'leave', 'Argument', 0, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'enter', 'InlineFragment', 1, null ],
            [ 'enter', 'NamedType', 'typeCondition', 'InlineFragment' ],
            [ 'enter', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'NamedType', 'typeCondition', 'InlineFragment' ],
            [ 'enter', 'Directive', 0, null ],
            [ 'enter', 'Name', 'name', 'Directive' ],
            [ 'leave', 'Name', 'name', 'Directive' ],
            [ 'leave', 'Directive', 0, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'InlineFragment' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'enter', 'Field', 1, null ],
            [ 'enter', 'Name', 'alias', 'Field' ],
            [ 'leave', 'Name', 'alias', 'Field' ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'Argument', 0, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'IntValue', 'value', 'Argument' ],
            [ 'leave', 'IntValue', 'value', 'Argument' ],
            [ 'leave', 'Argument', 0, null ],
            [ 'enter', 'Argument', 1, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'Variable', 'value', 'Argument' ],
            [ 'enter', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Variable', 'value', 'Argument' ],
            [ 'leave', 'Argument', 1, null ],
            [ 'enter', 'Directive', 0, null ],
            [ 'enter', 'Name', 'name', 'Directive' ],
            [ 'leave', 'Name', 'name', 'Directive' ],
            [ 'enter', 'Argument', 0, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'Variable', 'value', 'Argument' ],
            [ 'enter', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Variable', 'value', 'Argument' ],
            [ 'leave', 'Argument', 0, null ],
            [ 'leave', 'Directive', 0, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'enter', 'FragmentSpread', 1, null ],
            [ 'enter', 'Name', 'name', 'FragmentSpread' ],
            [ 'leave', 'Name', 'name', 'FragmentSpread' ],
            [ 'leave', 'FragmentSpread', 1, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'leave', 'Field', 1, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'InlineFragment' ],
            [ 'leave', 'InlineFragment', 1, null ],
            [ 'enter', 'InlineFragment', 2, null ],
            [ 'enter', 'Directive', 0, null ],
            [ 'enter', 'Name', 'name', 'Directive' ],
            [ 'leave', 'Name', 'name', 'Directive' ],
            [ 'enter', 'Argument', 0, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'Variable', 'value', 'Argument' ],
            [ 'enter', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Variable', 'value', 'Argument' ],
            [ 'leave', 'Argument', 0, null ],
            [ 'leave', 'Directive', 0, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'InlineFragment' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'InlineFragment' ],
            [ 'leave', 'InlineFragment', 2, null ],
            [ 'enter', 'InlineFragment', 3, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'InlineFragment' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'InlineFragment' ],
            [ 'leave', 'InlineFragment', 3, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'OperationDefinition' ],
            [ 'leave', 'OperationDefinition', 0, null ],
            [ 'enter', 'OperationDefinition', 1, null ],
            [ 'enter', 'Name', 'name', 'OperationDefinition' ],
            [ 'leave', 'Name', 'name', 'OperationDefinition' ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'OperationDefinition' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'Argument', 0, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'IntValue', 'value', 'Argument' ],
            [ 'leave', 'IntValue', 'value', 'Argument' ],
            [ 'leave', 'Argument', 0, null ],
            [ 'enter', 'Directive', 0, null ],
            [ 'enter', 'Name', 'name', 'Directive' ],
            [ 'leave', 'Name', 'name', 'Directive' ],
            [ 'leave', 'Directive', 0, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'OperationDefinition' ],
            [ 'leave', 'OperationDefinition', 1, null ],
            [ 'enter', 'OperationDefinition', 2, null ],
            [ 'enter', 'Name', 'name', 'OperationDefinition' ],
            [ 'leave', 'Name', 'name', 'OperationDefinition' ],
            [ 'enter', 'VariableDefinition', 0, null ],
            [ 'enter', 'Variable', 'variable', 'VariableDefinition' ],
            [ 'enter', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Variable', 'variable', 'VariableDefinition' ],
            [ 'enter', 'NamedType', 'type', 'VariableDefinition' ],
            [ 'enter', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'NamedType', 'type', 'VariableDefinition' ],
            [ 'leave', 'VariableDefinition', 0, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'OperationDefinition' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'Argument', 0, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'Variable', 'value', 'Argument' ],
            [ 'enter', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Variable', 'value', 'Argument' ],
            [ 'leave', 'Argument', 0, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'enter', 'Field', 1, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'leave', 'Field', 1, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'OperationDefinition' ],
            [ 'leave', 'OperationDefinition', 2, null ],
            [ 'enter', 'FragmentDefinition', 3, null ],
            [ 'enter', 'Name', 'name', 'FragmentDefinition' ],
            [ 'leave', 'Name', 'name', 'FragmentDefinition' ],
            [ 'enter', 'NamedType', 'typeCondition', 'FragmentDefinition' ],
            [ 'enter', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'NamedType', 'typeCondition', 'FragmentDefinition' ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'FragmentDefinition' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'Argument', 0, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'Variable', 'value', 'Argument' ],
            [ 'enter', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Variable', 'value', 'Argument' ],
            [ 'leave', 'Argument', 0, null ],
            [ 'enter', 'Argument', 1, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'Variable', 'value', 'Argument' ],
            [ 'enter', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Variable', 'value', 'Argument' ],
            [ 'leave', 'Argument', 1, null ],
            [ 'enter', 'Argument', 2, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'ObjectValue', 'value', 'Argument' ],
            [ 'enter', 'ObjectField', 0, null ],
            [ 'enter', 'Name', 'name', 'ObjectField' ],
            [ 'leave', 'Name', 'name', 'ObjectField' ],
            [ 'enter', 'StringValue', 'value', 'ObjectField' ],
            [ 'leave', 'StringValue', 'value', 'ObjectField' ],
            [ 'leave', 'ObjectField', 0, null ],
            [ 'leave', 'ObjectValue', 'value', 'Argument' ],
            [ 'leave', 'Argument', 2, null ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'FragmentDefinition' ],
            [ 'leave', 'FragmentDefinition', 3, null ],
            [ 'enter', 'OperationDefinition', 4, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'OperationDefinition' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'Argument', 0, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'BooleanValue', 'value', 'Argument' ],
            [ 'leave', 'BooleanValue', 'value', 'Argument' ],
            [ 'leave', 'Argument', 0, null ],
            [ 'enter', 'Argument', 1, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'BooleanValue', 'value', 'Argument' ],
            [ 'leave', 'BooleanValue', 'value', 'Argument' ],
            [ 'leave', 'Argument', 1, null ],
            [ 'enter', 'Argument', 2, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'NullValue', 'value', 'Argument' ],
            [ 'leave', 'NullValue', 'value', 'Argument' ],
            [ 'leave', 'Argument', 2, null ],
            [ 'leave', 'Field', 0, null ],
            [ 'enter', 'Field', 1, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'leave', 'Field', 1, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'OperationDefinition' ],
            [ 'leave', 'OperationDefinition', 4, null ],
            [ 'leave', 'Document', null, null ]
        ];

        expect($visited)->toBePHPEqual($expected);
    }

    // Describe: visitInParallel
    // Note: nearly identical to the above test of the same test but using visitInParallel.

    /**
     * @it allows skipping a sub-tree
     */
    public function testAllowsSkippingSubTree():void
    {
        $visited = [];

        $ast = Parser::parse('{ a, b { x }, c }');
        Visitor::visit($ast, Visitor::visitInParallel([
            [
                /* HH_FIXME[2087]*/
                'enter' => function($node) use (&$visited) {
                    $visited[] = [ 'enter', $node->kind, isset($node->value) ?  $node->value : null];

                    if ($node->kind === 'Field' && isset($node->name->value) && $node->name->value === 'b') {
                        return Visitor::skipNode();
                    }
                },
                /* HH_FIXME[2087]*/
                'leave' => function($node) use (&$visited) {
                    $visited[] = ['leave', $node->kind, isset($node->value) ? $node->value : null];
                }
            ]
        ]));

        expect($visited)->toBePHPEqual([
            [ 'enter', 'Document', null ],
            [ 'enter', 'OperationDefinition', null ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'a' ],
            [ 'leave', 'Name', 'a' ],
            [ 'leave', 'Field', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'c' ],
            [ 'leave', 'Name', 'c' ],
            [ 'leave', 'Field', null ],
            [ 'leave', 'SelectionSet', null ],
            [ 'leave', 'OperationDefinition', null ],
            [ 'leave', 'Document', null ],
        ]);
    }

    /**
     * @it allows skipping different sub-trees
     */
    public function testAllowsSkippingDifferentSubTrees():void
    {
        $visited = [];

        $ast = Parser::parse('{ a { x }, b { y} }');
        Visitor::visit($ast, Visitor::visitInParallel([
        [
            /* HH_FIXME[2087]*/
            'enter' => function($node) use (&$visited) {
                $visited[] = ['no-a', 'enter', $node->kind, isset($node->value) ? $node->value : null];
                if ($node->kind === 'Field' && isset($node->name->value) && $node->name->value === 'a') {
                    return Visitor::skipNode();
                }
            },
            /* HH_FIXME[2087]*/
            'leave' => function($node) use (&$visited) {
                $visited[] = [ 'no-a', 'leave', $node->kind, isset($node->value) ? $node->value : null ];
            }
        ],
        [
            /* HH_FIXME[2087]*/
            'enter' => function($node) use (&$visited) {
                $visited[] = ['no-b', 'enter', $node->kind, isset($node->value) ? $node->value : null];
                if ($node->kind === 'Field' && isset($node->name->value) && $node->name->value === 'b') {
                    return Visitor::skipNode();
                }
            },
            /* HH_FIXME[2087]*/
            'leave' => function($node) use (&$visited) {
                $visited[] = ['no-b', 'leave', $node->kind, isset($node->value) ? $node->value : null];
            }
        ]
        ]));

        expect($visited)->toBePHPEqual([
            [ 'no-a', 'enter', 'Document', null ],
            [ 'no-b', 'enter', 'Document', null ],
            [ 'no-a', 'enter', 'OperationDefinition', null ],
            [ 'no-b', 'enter', 'OperationDefinition', null ],
            [ 'no-a', 'enter', 'SelectionSet', null ],
            [ 'no-b', 'enter', 'SelectionSet', null ],
            [ 'no-a', 'enter', 'Field', null ],
            [ 'no-b', 'enter', 'Field', null ],
            [ 'no-b', 'enter', 'Name', 'a' ],
            [ 'no-b', 'leave', 'Name', 'a' ],
            [ 'no-b', 'enter', 'SelectionSet', null ],
            [ 'no-b', 'enter', 'Field', null ],
            [ 'no-b', 'enter', 'Name', 'x' ],
            [ 'no-b', 'leave', 'Name', 'x' ],
            [ 'no-b', 'leave', 'Field', null ],
            [ 'no-b', 'leave', 'SelectionSet', null ],
            [ 'no-b', 'leave', 'Field', null ],
            [ 'no-a', 'enter', 'Field', null ],
            [ 'no-b', 'enter', 'Field', null ],
            [ 'no-a', 'enter', 'Name', 'b' ],
            [ 'no-a', 'leave', 'Name', 'b' ],
            [ 'no-a', 'enter', 'SelectionSet', null ],
            [ 'no-a', 'enter', 'Field', null ],
            [ 'no-a', 'enter', 'Name', 'y' ],
            [ 'no-a', 'leave', 'Name', 'y' ],
            [ 'no-a', 'leave', 'Field', null ],
            [ 'no-a', 'leave', 'SelectionSet', null ],
            [ 'no-a', 'leave', 'Field', null ],
            [ 'no-a', 'leave', 'SelectionSet', null ],
            [ 'no-b', 'leave', 'SelectionSet', null ],
            [ 'no-a', 'leave', 'OperationDefinition', null ],
            [ 'no-b', 'leave', 'OperationDefinition', null ],
            [ 'no-a', 'leave', 'Document', null ],
            [ 'no-b', 'leave', 'Document', null ],
        ]);
    }

    /**
     * @it allows early exit while visiting
     */
    public function testAllowsEarlyExitWhileVisiting2():void
    {
        $visited = [];

        $ast = Parser::parse('{ a, b { x }, c }');
        Visitor::visit($ast, Visitor::visitInParallel([ [
            /* HH_FIXME[2087]*/
            'enter' => function($node) use (&$visited) {
                $value = isset($node->value) ? $node->value : null;
                $visited[] = ['enter', $node->kind, $value];
                if ($node->kind === 'Name' && $value === 'x') {
                    return Visitor::stop();
                }
            },
            /* HH_FIXME[2087]*/
            'leave' => function($node) use (&$visited) {
                $visited[] = ['leave', $node->kind, isset($node->value) ? $node->value : null];
            }
        ] ]));

        expect($visited)->toBePHPEqual([
            [ 'enter', 'Document', null ],
            [ 'enter', 'OperationDefinition', null ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'a' ],
            [ 'leave', 'Name', 'a' ],
            [ 'leave', 'Field', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'b' ],
            [ 'leave', 'Name', 'b' ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'x' ]
        ]);
    }

    /**
     * @it allows early exit from different points
     */
    public function testAllowsEarlyExitFromDifferentPoints():void
    {
        $visited = [];

        $ast = Parser::parse('{ a { y }, b { x } }');
        Visitor::visit($ast, Visitor::visitInParallel([
        [
            /* HH_FIXME[2087]*/
            'enter' => function($node) use (&$visited) {
                $value = isset($node->value) ? $node->value : null;
                $visited[] = ['break-a', 'enter', $node->kind, $value];
                if ($node->kind === 'Name' && $value === 'a') {
                    return Visitor::stop();
                }
            },
            /* HH_FIXME[2087]*/
            'leave' => function($node) use (&$visited) {
                $visited[] = [ 'break-a', 'leave', $node->kind, isset($node->value) ? $node->value : null ];
            }
        ],
        [
            /* HH_FIXME[2087]*/
            'enter' => function($node) use (&$visited) {
                $value = isset($node->value) ? $node->value : null;
                $visited[] = ['break-b', 'enter', $node->kind, $value];
                if ($node->kind === 'Name' && $value === 'b') {
                    return Visitor::stop();
                }
            },
            /* HH_FIXME[2087]*/
            'leave' => function($node) use (&$visited) {
                $visited[] = ['break-b', 'leave', $node->kind, isset($node->value) ? $node->value : null];
            }
        ],
        ]));

        expect($visited)->toBePHPEqual([
            [ 'break-a', 'enter', 'Document', null ],
            [ 'break-b', 'enter', 'Document', null ],
            [ 'break-a', 'enter', 'OperationDefinition', null ],
            [ 'break-b', 'enter', 'OperationDefinition', null ],
            [ 'break-a', 'enter', 'SelectionSet', null ],
            [ 'break-b', 'enter', 'SelectionSet', null ],
            [ 'break-a', 'enter', 'Field', null ],
            [ 'break-b', 'enter', 'Field', null ],
            [ 'break-a', 'enter', 'Name', 'a' ],
            [ 'break-b', 'enter', 'Name', 'a' ],
            [ 'break-b', 'leave', 'Name', 'a' ],
            [ 'break-b', 'enter', 'SelectionSet', null ],
            [ 'break-b', 'enter', 'Field', null ],
            [ 'break-b', 'enter', 'Name', 'y' ],
            [ 'break-b', 'leave', 'Name', 'y' ],
            [ 'break-b', 'leave', 'Field', null ],
            [ 'break-b', 'leave', 'SelectionSet', null ],
            [ 'break-b', 'leave', 'Field', null ],
            [ 'break-b', 'enter', 'Field', null ],
            [ 'break-b', 'enter', 'Name', 'b' ]
        ]);
    }

    /**
     * @it allows early exit while leaving
     */
    public function testAllowsEarlyExitWhileLeaving2():void
    {
        $visited = [];

        $ast = Parser::parse('{ a, b { x }, c }');
        Visitor::visit($ast, Visitor::visitInParallel([ [
            /* HH_FIXME[2087]*/
            'enter' => function($node) use (&$visited) {
                $visited[] = ['enter', $node->kind, isset($node->value) ? $node->value : null];
            },
            /* HH_FIXME[2087]*/
            'leave' => function($node) use (&$visited) {
                $value = isset($node->value) ? $node->value : null;
                $visited[] = ['leave', $node->kind, $value];
                if ($node->kind === 'Name' && $value === 'x') {
                    return Visitor::stop();
                }
            }
        ] ]));

        expect($visited)->toBePHPEqual([
            [ 'enter', 'Document', null ],
            [ 'enter', 'OperationDefinition', null ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'a' ],
            [ 'leave', 'Name', 'a' ],
            [ 'leave', 'Field', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'b' ],
            [ 'leave', 'Name', 'b' ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'x' ],
            [ 'leave', 'Name', 'x' ]
        ]);
    }

    /**
     * @it allows early exit from leaving different points
     */
    public function testAllowsEarlyExitFromLeavingDifferentPoints():void
    {
        $visited = [];

        $ast = Parser::parse('{ a { y }, b { x } }');
        Visitor::visit($ast, Visitor::visitInParallel([
            [
                /* HH_FIXME[2087]*/
                'enter' => function($node) use (&$visited) {
                    $visited[] = ['break-a', 'enter', $node->kind, isset($node->value) ? $node->value : null];
                },
                /* HH_FIXME[2087]*/
                'leave' => function($node) use (&$visited) {
                    $visited[] = ['break-a', 'leave', $node->kind, isset($node->value) ? $node->value : null];
                    if ($node->kind === 'Field' && isset($node->name->value) && $node->name->value === 'a') {
                        return Visitor::stop();
                    }
                }
            ],
            [
                /* HH_FIXME[2087]*/
                'enter' => function($node) use (&$visited) {
                    $visited[] = ['break-b', 'enter', $node->kind, isset($node->value) ? $node->value : null];
                },
                /* HH_FIXME[2087]*/
                'leave' => function($node) use (&$visited) {
                    $visited[] = ['break-b', 'leave', $node->kind, isset($node->value) ? $node->value : null];
                    if ($node->kind === 'Field' && isset($node->name->value) && $node->name->value === 'b') {
                        return Visitor::stop();
                    }
                }
            ],
        ]));

        expect($visited)->toBePHPEqual([
            [ 'break-a', 'enter', 'Document', null ],
            [ 'break-b', 'enter', 'Document', null ],
            [ 'break-a', 'enter', 'OperationDefinition', null ],
            [ 'break-b', 'enter', 'OperationDefinition', null ],
            [ 'break-a', 'enter', 'SelectionSet', null ],
            [ 'break-b', 'enter', 'SelectionSet', null ],
            [ 'break-a', 'enter', 'Field', null ],
            [ 'break-b', 'enter', 'Field', null ],
            [ 'break-a', 'enter', 'Name', 'a' ],
            [ 'break-b', 'enter', 'Name', 'a' ],
            [ 'break-a', 'leave', 'Name', 'a' ],
            [ 'break-b', 'leave', 'Name', 'a' ],
            [ 'break-a', 'enter', 'SelectionSet', null ],
            [ 'break-b', 'enter', 'SelectionSet', null ],
            [ 'break-a', 'enter', 'Field', null ],
            [ 'break-b', 'enter', 'Field', null ],
            [ 'break-a', 'enter', 'Name', 'y' ],
            [ 'break-b', 'enter', 'Name', 'y' ],
            [ 'break-a', 'leave', 'Name', 'y' ],
            [ 'break-b', 'leave', 'Name', 'y' ],
            [ 'break-a', 'leave', 'Field', null ],
            [ 'break-b', 'leave', 'Field', null ],
            [ 'break-a', 'leave', 'SelectionSet', null ],
            [ 'break-b', 'leave', 'SelectionSet', null ],
            [ 'break-a', 'leave', 'Field', null ],
            [ 'break-b', 'leave', 'Field', null ],
            [ 'break-b', 'enter', 'Field', null ],
            [ 'break-b', 'enter', 'Name', 'b' ],
            [ 'break-b', 'leave', 'Name', 'b' ],
            [ 'break-b', 'enter', 'SelectionSet', null ],
            [ 'break-b', 'enter', 'Field', null ],
            [ 'break-b', 'enter', 'Name', 'x' ],
            [ 'break-b', 'leave', 'Name', 'x' ],
            [ 'break-b', 'leave', 'Field', null ],
            [ 'break-b', 'leave', 'SelectionSet', null ],
            [ 'break-b', 'leave', 'Field', null ]
        ]);
    }

    /**
     * @it allows for editing on enter
     */
    public function testAllowsForEditingOnEnter2():void
    {
        $visited = [];

        $ast = Parser::parse('{ a, b, c { a, b, c } }', true);
        $editedAst = Visitor::visit($ast, Visitor::visitInParallel([
            [
                /* HH_FIXME[2087]*/
                'enter' => function ($node) use (&$visited) {
                    if ($node->kind === 'Field' && isset($node->name->value) && $node->name->value === 'b') {
                        return Visitor::removeNode();
                    }
                }
            ],
            [
                /* HH_FIXME[2087]*/
                'enter' => function ($node) use (&$visited) {
                    $visited[] = ['enter', $node->kind, isset($node->value) ? $node->value : null];
                },
                /* HH_FIXME[2087]*/
                'leave' => function ($node) use (&$visited) {
                    $visited[] = ['leave', $node->kind, isset($node->value) ? $node->value : null];
                }
            ],
        ]));

        expect($ast)->toBePHPEqual(Parser::parse('{ a, b, c { a, b, c } }', true));

        expect($editedAst)->toBePHPEqual(Parser::parse('{ a,    c { a,    c } }', true));

        expect($visited)->toBePHPEqual([
            ['enter', 'Document', null],
            ['enter', 'OperationDefinition', null],
            ['enter', 'SelectionSet', null],
            ['enter', 'Field', null],
            ['enter', 'Name', 'a'],
            ['leave', 'Name', 'a'],
            ['leave', 'Field', null],
            ['enter', 'Field', null],
            ['enter', 'Name', 'c'],
            ['leave', 'Name', 'c'],
            ['enter', 'SelectionSet', null],
            ['enter', 'Field', null],
            ['enter', 'Name', 'a'],
            ['leave', 'Name', 'a'],
            ['leave', 'Field', null],
            ['enter', 'Field', null],
            ['enter', 'Name', 'c'],
            ['leave', 'Name', 'c'],
            ['leave', 'Field', null],
            ['leave', 'SelectionSet', null],
            ['leave', 'Field', null],
            ['leave', 'SelectionSet', null],
            ['leave', 'OperationDefinition', null],
            ['leave', 'Document', null]
        ]);
    }

    /**
     * @it allows for editing on leave
     */
    public function testAllowsForEditingOnLeave2():void
    {
        $visited = [];

        $ast = Parser::parse('{ a, b, c { a, b, c } }', true);
        $editedAst = Visitor::visit($ast, Visitor::visitInParallel([
            [
                'leave' => function ($node) {
                    if ($node->kind === 'Field' && isset($node->name->value) && $node->name->value === 'b') {
                        return Visitor::removeNode();
                    }
                }
            ],
            [
                /* HH_FIXME[2087]*/
                'enter' => function ($node) use (&$visited) {
                    $visited[] = ['enter', $node->kind, isset($node->value) ? $node->value : null];
                },
                /* HH_FIXME[2087]*/
                'leave' => function ($node) use (&$visited) {
                    $visited[] = ['leave', $node->kind, isset($node->value) ? $node->value : null];
                }
            ],
        ]));

        expect($ast)->toBePHPEqual(Parser::parse('{ a, b, c { a, b, c } }', true));

        expect($editedAst)->toBePHPEqual(Parser::parse('{ a,    c { a,    c } }', true));

        expect($visited)->toBePHPEqual([
            ['enter', 'Document', null],
            ['enter', 'OperationDefinition', null],
            ['enter', 'SelectionSet', null],
            ['enter', 'Field', null],
            ['enter', 'Name', 'a'],
            ['leave', 'Name', 'a'],
            ['leave', 'Field', null],
            ['enter', 'Field', null],
            ['enter', 'Name', 'b'],
            ['leave', 'Name', 'b'],
            ['enter', 'Field', null],
            ['enter', 'Name', 'c'],
            ['leave', 'Name', 'c'],
            ['enter', 'SelectionSet', null],
            ['enter', 'Field', null],
            ['enter', 'Name', 'a'],
            ['leave', 'Name', 'a'],
            ['leave', 'Field', null],
            ['enter', 'Field', null],
            ['enter', 'Name', 'b'],
            ['leave', 'Name', 'b'],
            ['enter', 'Field', null],
            ['enter', 'Name', 'c'],
            ['leave', 'Name', 'c'],
            ['leave', 'Field', null],
            ['leave', 'SelectionSet', null],
            ['leave', 'Field', null],
            ['leave', 'SelectionSet', null],
            ['leave', 'OperationDefinition', null],
            ['leave', 'Document', null]
        ]);
    }

    // Describe: visitWithTypeInfo

    /**
     * @it maintains type info during visit
     */
    public function testMaintainsTypeInfoDuringVisit():void
    {
        $visited = [];

        $typeInfo = new TypeInfo(TestCase::getDefaultSchema());

        $ast = Parser::parse('{ human(id: 4) { name, pets { name }, unknown } }');
        Visitor::visit($ast, Visitor::visitWithTypeInfo($typeInfo, [
            /* HH_FIXME[2087]*/
            'enter' => function ($node) use ($typeInfo, &$visited) {
                $parentType = $typeInfo->getParentType();
                $type = $typeInfo->getType();
                $inputType = $typeInfo->getInputType();
                $visited[] = [
                    'enter',
                    $node->kind,
                    $node->kind === 'Name' ? $node->value : null,
                    $parentType ? (string)$parentType : null,
                    $type ? (string)$type : null,
                    $inputType ? (string)$inputType : null
                ];
            },
            /* HH_FIXME[2087]*/
            'leave' => function ($node) use ($typeInfo, &$visited) {
                $parentType = $typeInfo->getParentType();
                $type = $typeInfo->getType();
                $inputType = $typeInfo->getInputType();
                $visited[] = [
                    'leave',
                    $node->kind,
                    $node->kind === 'Name' ? $node->value : null,
                    $parentType ? (string)$parentType : null,
                    $type ? (string)$type : null,
                    $inputType ? (string)$inputType : null
                ];
            }
        ]));

        expect($visited)->toBePHPEqual([
            ['enter', 'Document', null, null, null, null],
            ['enter', 'OperationDefinition', null, null, 'QueryRoot', null],
            ['enter', 'SelectionSet', null, 'QueryRoot', 'QueryRoot', null],
            ['enter', 'Field', null, 'QueryRoot', 'Human', null],
            ['enter', 'Name', 'human', 'QueryRoot', 'Human', null],
            ['leave', 'Name', 'human', 'QueryRoot', 'Human', null],
            ['enter', 'Argument', null, 'QueryRoot', 'Human', 'ID'],
            ['enter', 'Name', 'id', 'QueryRoot', 'Human', 'ID'],
            ['leave', 'Name', 'id', 'QueryRoot', 'Human', 'ID'],
            ['enter', 'IntValue', null, 'QueryRoot', 'Human', 'ID'],
            ['leave', 'IntValue', null, 'QueryRoot', 'Human', 'ID'],
            ['leave', 'Argument', null, 'QueryRoot', 'Human', 'ID'],
            ['enter', 'SelectionSet', null, 'Human', 'Human', null],
            ['enter', 'Field', null, 'Human', 'String', null],
            ['enter', 'Name', 'name', 'Human', 'String', null],
            ['leave', 'Name', 'name', 'Human', 'String', null],
            ['leave', 'Field', null, 'Human', 'String', null],
            ['enter', 'Field', null, 'Human', '[Pet]', null],
            ['enter', 'Name', 'pets', 'Human', '[Pet]', null],
            ['leave', 'Name', 'pets', 'Human', '[Pet]', null],
            ['enter', 'SelectionSet', null, 'Pet', '[Pet]', null],
            ['enter', 'Field', null, 'Pet', 'String', null],
            ['enter', 'Name', 'name', 'Pet', 'String', null],
            ['leave', 'Name', 'name', 'Pet', 'String', null],
            ['leave', 'Field', null, 'Pet', 'String', null],
            ['leave', 'SelectionSet', null, 'Pet', '[Pet]', null],
            ['leave', 'Field', null, 'Human', '[Pet]', null],
            ['enter', 'Field', null, 'Human', null, null],
            ['enter', 'Name', 'unknown', 'Human', null, null],
            ['leave', 'Name', 'unknown', 'Human', null, null],
            ['leave', 'Field', null, 'Human', null, null],
            ['leave', 'SelectionSet', null, 'Human', 'Human', null],
            ['leave', 'Field', null, 'QueryRoot', 'Human', null],
            ['leave', 'SelectionSet', null, 'QueryRoot', 'QueryRoot', null],
            ['leave', 'OperationDefinition', null, null, 'QueryRoot', null],
            ['leave', 'Document', null, null, null, null]
        ]);
    }

    /**
     * @it maintains type info during edit
     */
    public function testMaintainsTypeInfoDuringEdit():void
    {
        $visited = [];
        $typeInfo = new TypeInfo(TestCase::getDefaultSchema());

        $ast = Parser::parse(
            '{ human(id: 4) { name, pets }, alien }'
        );
        $editedAst = Visitor::visit($ast, Visitor::visitWithTypeInfo($typeInfo, [
            /* HH_FIXME[2087]*/
            'enter' => function ($node) use ($typeInfo, &$visited) {
                $parentType = $typeInfo->getParentType();
                $type = $typeInfo->getType();
                $inputType = $typeInfo->getInputType();
                $visited[] = [
                    'enter',
                    $node->kind,
                    $node->kind === 'Name' ? $node->value : null,
                    $parentType ? (string)$parentType : null,
                    $type ? (string)$type : null,
                    $inputType ? (string)$inputType : null
                ];

                // Make a query valid by adding missing selection sets.
                if (
                    $node->kind === 'Field' &&
                    !$node->selectionSet &&
                    GraphQlType::isCompositeType(GraphQlType::getNamedType($type))
                ) {
                    return new FieldNode(
                        $node->name,
                        $node->alias,
                        $node->arguments,
                        $node->directives,
                        new SelectionSetNode([
                            new FieldNode(new NameNode('__typename'))
                        ]));
                }
            },
            /* HH_FIXME[2087]*/
            'leave' => function ($node) use ($typeInfo, &$visited) {
                $parentType = $typeInfo->getParentType();
                $type = $typeInfo->getType();
                $inputType = $typeInfo->getInputType();
                $visited[] = [
                    'leave',
                    $node->kind,
                    $node->kind === 'Name' ? $node->value : null,
                    $parentType ? (string)$parentType : null,
                    $type ? (string)$type : null,
                    $inputType ? (string)$inputType : null
                ];
            }
        ]));

        expect(Printer::doPrint($ast))->toBePHPEqual(Printer::doPrint(Parser::parse(
            '{ human(id: 4) { name, pets }, alien }'
        )));

        expect(Printer::doPrint($editedAst))->toBePHPEqual(Printer::doPrint(Parser::parse(
            '{ human(id: 4) { name, pets { __typename } }, alien { __typename } }'
        )));

        expect($visited)->toBePHPEqual([
            ['enter', 'Document', null, null, null, null],
            ['enter', 'OperationDefinition', null, null, 'QueryRoot', null],
            ['enter', 'SelectionSet', null, 'QueryRoot', 'QueryRoot', null],
            ['enter', 'Field', null, 'QueryRoot', 'Human', null],
            ['enter', 'Name', 'human', 'QueryRoot', 'Human', null],
            ['leave', 'Name', 'human', 'QueryRoot', 'Human', null],
            ['enter', 'Argument', null, 'QueryRoot', 'Human', 'ID'],
            ['enter', 'Name', 'id', 'QueryRoot', 'Human', 'ID'],
            ['leave', 'Name', 'id', 'QueryRoot', 'Human', 'ID'],
            ['enter', 'IntValue', null, 'QueryRoot', 'Human', 'ID'],
            ['leave', 'IntValue', null, 'QueryRoot', 'Human', 'ID'],
            ['leave', 'Argument', null, 'QueryRoot', 'Human', 'ID'],
            ['enter', 'SelectionSet', null, 'Human', 'Human', null],
            ['enter', 'Field', null, 'Human', 'String', null],
            ['enter', 'Name', 'name', 'Human', 'String', null],
            ['leave', 'Name', 'name', 'Human', 'String', null],
            ['leave', 'Field', null, 'Human', 'String', null],
            ['enter', 'Field', null, 'Human', '[Pet]', null],
            ['enter', 'Name', 'pets', 'Human', '[Pet]', null],
            ['leave', 'Name', 'pets', 'Human', '[Pet]', null],
            ['enter', 'SelectionSet', null, 'Pet', '[Pet]', null],
            ['enter', 'Field', null, 'Pet', 'String!', null],
            ['enter', 'Name', '__typename', 'Pet', 'String!', null],
            ['leave', 'Name', '__typename', 'Pet', 'String!', null],
            ['leave', 'Field', null, 'Pet', 'String!', null],
            ['leave', 'SelectionSet', null, 'Pet', '[Pet]', null],
            ['leave', 'Field', null, 'Human', '[Pet]', null],
            ['leave', 'SelectionSet', null, 'Human', 'Human', null],
            ['leave', 'Field', null, 'QueryRoot', 'Human', null],
            ['enter', 'Field', null, 'QueryRoot', 'Alien', null],
            ['enter', 'Name', 'alien', 'QueryRoot', 'Alien', null],
            ['leave', 'Name', 'alien', 'QueryRoot', 'Alien', null],
            ['enter', 'SelectionSet', null, 'Alien', 'Alien', null],
            ['enter', 'Field', null, 'Alien', 'String!', null],
            ['enter', 'Name', '__typename', 'Alien', 'String!', null],
            ['leave', 'Name', '__typename', 'Alien', 'String!', null],
            ['leave', 'Field', null, 'Alien', 'String!', null],
            ['leave', 'SelectionSet', null, 'Alien', 'Alien', null],
            ['leave', 'Field', null, 'QueryRoot', 'Alien', null],
            ['leave', 'SelectionSet', null, 'QueryRoot', 'QueryRoot', null],
            ['leave', 'OperationDefinition', null, null, 'QueryRoot', null],
            ['leave', 'Document', null, null, null, null]
        ]);
    }
}
