<?hh //decl
namespace GraphQL\Language;

use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NodeList;
use GraphQL\Utils\TypeInfo;


/**
 * Utility for efficient AST traversal and modification.
 *
 * `visit()` will walk through an AST using a depth first traversal, calling
 * the visitor's enter function at each node in the traversal, and calling the
 * leave function after visiting that node and all of it's child nodes.
 *
 * By returning different values from the enter and leave functions, the
 * behavior of the visitor can be altered, including skipping over a sub-tree of
 * the AST (by returning false), editing the AST by returning a value or null
 * to remove the value, or to stop the whole traversal by returning BREAK.
 *
 * When using `visit()` to edit an AST, the original AST will not be modified, and
 * a new version of the AST with the changes applied will be returned from the
 * visit function.
 *
 *     $editedAST = Visitor::visit($ast, [
 *       'enter' => function ($node, $key, $parent, $path, $ancestors) {
 *         // return
 *         //   null: no action
 *         //   Visitor::skipNode(): skip visiting this node
 *         //   Visitor::stop(): stop visiting altogether
 *         //   Visitor::removeNode(): delete this node
 *         //   any value: replace this node with the returned value
 *       },
 *       'leave' => function ($node, $key, $parent, $path, $ancestors) {
 *         // return
 *         //   null: no action
 *         //   Visitor::stop(): stop visiting altogether
 *         //   Visitor::removeNode(): delete this node
 *         //   any value: replace this node with the returned value
 *       }
 *     ]);
 *
 * Alternatively to providing enter() and leave() functions, a visitor can
 * instead provide functions named the same as the [kinds of AST nodes](reference.md#graphqllanguageastnodekind),
 * or enter/leave visitors at a named key, leading to four permutations of
 * visitor API:
 *
 * 1) Named visitors triggered when entering a node a specific kind.
 *
 *     Visitor::visit($ast, [
 *       'Kind' => function ($node) {
 *         // enter the "Kind" node
 *       }
 *     ]);
 *
 * 2) Named visitors that trigger upon entering and leaving a node of
 *    a specific kind.
 *
 *     Visitor::visit($ast, [
 *       'Kind' => [
 *         'enter' => function ($node) {
 *           // enter the "Kind" node
 *         }
 *         'leave' => function ($node) {
 *           // leave the "Kind" node
 *         }
 *       ]
 *     ]);
 *
 * 3) Generic visitors that trigger upon entering and leaving any node.
 *
 *     Visitor::visit($ast, [
 *       'enter' => function ($node) {
 *         // enter any node
 *       },
 *       'leave' => function ($node) {
 *         // leave any node
 *       }
 *     ]);
 *
 * 4) Parallel visitors for entering and leaving nodes of a specific kind.
 *
 *     Visitor::visit($ast, [
 *       'enter' => [
 *         'Kind' => function($node) {
 *           // enter the "Kind" node
 *         }
 *       },
 *       'leave' => [
 *         'Kind' => function ($node) {
 *           // leave the "Kind" node
 *         }
 *       ]
 *     ]);
 */



class Visitor
{
    public static array<string, array<string>> $visitorKeys = [
        NodeKind::NAME => [],
        NodeKind::DOCUMENT => ['definitions'],
        NodeKind::OPERATION_DEFINITION => ['name', 'variableDefinitions', 'directives', 'selectionSet'],
        NodeKind::VARIABLE_DEFINITION => ['variable', 'type', 'defaultValue'],
        NodeKind::VARIABLE => ['name'],
        NodeKind::SELECTION_SET => ['selections'],
        NodeKind::FIELD => ['alias', 'name', 'arguments', 'directives', 'selectionSet'],
        NodeKind::ARGUMENT => ['name', 'value'],
        NodeKind::FRAGMENT_SPREAD => ['name', 'directives'],
        NodeKind::INLINE_FRAGMENT => ['typeCondition', 'directives', 'selectionSet'],
        NodeKind::FRAGMENT_DEFINITION => ['name', 'typeCondition', 'directives', 'selectionSet'],

        NodeKind::INT => [],
        NodeKind::FLOAT => [],
        NodeKind::STRING => [],
        NodeKind::BOOLEAN => [],
        NodeKind::NULL => [],
        NodeKind::ENUM => [],
        NodeKind::LST => ['values'],
        NodeKind::OBJECT => ['fields'],
        NodeKind::OBJECT_FIELD => ['name', 'value'],
        NodeKind::DIRECTIVE => ['name', 'arguments'],
        NodeKind::NAMED_TYPE => ['name'],
        NodeKind::LIST_TYPE => ['type'],
        NodeKind::NON_NULL_TYPE => ['type'],

        NodeKind::SCHEMA_DEFINITION => ['directives', 'operationTypes'],
        NodeKind::OPERATION_TYPE_DEFINITION => ['type'],
        NodeKind::SCALAR_TYPE_DEFINITION => ['name', 'directives'],
        NodeKind::OBJECT_TYPE_DEFINITION => ['name', 'interfaces', 'directives', 'fields'],
        NodeKind::FIELD_DEFINITION => ['name', 'values', 'type', 'directives'],
        NodeKind::INPUT_VALUE_DEFINITION => ['name', 'type', 'defaultValue', 'directives'],
        NodeKind::INTERFACE_TYPE_DEFINITION => [ 'name', 'directives', 'fields' ],
        NodeKind::UNION_TYPE_DEFINITION => [ 'name', 'directives', 'types' ],
        NodeKind::ENUM_TYPE_DEFINITION => [ 'name', 'directives', 'values' ],
        NodeKind::ENUM_VALUE_DEFINITION => [ 'name', 'directives' ],
        NodeKind::INPUT_OBJECT_TYPE_DEFINITION => [ 'name', 'directives', 'fields' ],
        NodeKind::TYPE_EXTENSION_DEFINITION => [ 'definition' ],
        NodeKind::DIRECTIVE_DEFINITION => [ 'name', 'arguments', 'locations' ]
    ];

    /**
     * Visit the AST (see class description for details)
     *
     * @api
     * @param NodeList | Node | ArrayObject | array $root
     * @param array $visitor
     * @param array $keyMap
     * @return Node|mixed
     * @throws \Exception
     */
    public static function visit(mixed $root, array<string, mixed> $visitor, ?array<string, array<string>> $keyMap = null):mixed
    {
        $visitorKeys = $keyMap ?? Visitor::$visitorKeys;

        $stack = null;
        $inArray = is_array($root);
        $keys = [$root];
        $index = -1;
        $edits = [];
        $parent = null;
        $path = [];
        $ancestors = [];
        $newRoot = $root;

        $UNDEFINED = null;

        do
        {
            $index++;
            $keyCount = \count($keys);
            $isLeaving = $index === $keyCount;
            $key = null;
            $node = null;
            $isEdited = $isLeaving && \count($edits) !== 0;

            if ($isLeaving)
            {
                $key = \count($ancestors) === 0 ? $UNDEFINED : \array_pop(&$path);
                $node = $parent;
                $parent = \array_pop(&$ancestors);

                if ($isEdited)
                {
                    if ($inArray)
                    {
                        // $node = $node; // arrays are value types in PHP
                    }
                    else
                    {
                        if ($node instanceof Node)
                        {
                            $node = clone $node;
                        }
                    }
                    $editOffset = 0;
                    for ($ii = 0; $ii < \count($edits); $ii++)
                    {
                        $editKey = $edits[$ii][0];
                        $editValue = $edits[$ii][1];

                        if ($inArray)
                        {
                            $editKey -= $editOffset;
                        }
                        if ($inArray && $editValue === null)
                        {
                            \array_splice(&$node, $editKey, 1);
                            $editOffset++;
                        }
                        elseif($editValue !== null)
                        {
                            if(is_array($node))
                            {
                                $node[$editKey] = $editValue;
                            }
                            else
                            {
                                $node->{$editKey} = $editValue;
                            }
                        }
                    }
                }
                $index = $stack['index'];
                $keys = $stack['keys'];
                $edits = $stack['edits'];
                $inArray = $stack['inArray'];
                $stack = $stack['prev'];
            }
            else
            {
                $key = $UNDEFINED;
                $node = $newRoot;
                if ($parent !== null)
                {
                    if(is_array($keys))
                    {
                        $key = $keys[$index];
                    }
                    if ($inArray)
                    {
                        $key = $index;
                    }

                    if (is_array($parent))
                    {
                        $node = $parent[$key];
                    }
                    else
                    {
                        $node = $parent->{$key};
                    }
                }

                if ($node === null || $node === $UNDEFINED)
                {
                    continue;
                }

                if ($parent !== null)
                {
                    $path[] = $key;
                }
            }

            $result = null;
            if (!is_array($node))
            {
                if (!($node instanceof Node)) {
                    throw new \Exception('Invalid AST Node: ' . \json_encode($node));
                }

                $visitFn = Visitor::getVisitFn($visitor, $node->kind, $isLeaving);

                if ($visitFn)
                {
                    /* HH_FIXME[4009]*/
                    $result = call_user_func($visitFn, $node, $key, $parent, $path, $ancestors);

                    if ($result !== null) {
                        if ($result instanceof VisitorOperation) {
                            if ($result->doBreak) {
                                break;
                            }
                            if (!$isLeaving && $result->doContinue) {
                                \array_pop(&$path);
                                continue;
                            }
                            if ($result->removeNode) {
                                $editValue = null;
                            }
                        }
                        else
                        {
                            $editValue = $result;
                        }

                        $edits[] = [$key, $editValue];
                        if (!$isLeaving)
                        {
                            if ($editValue instanceof Node)
                            {
                                $node = $editValue;
                            }
                            else
                            {
                                \array_pop(&$path);
                                continue;
                            }
                        }
                    }
                }
            }

            if ($result === null && $isEdited)
            {
                $edits[] = [$key, $node];
            }

            if (!$isLeaving)
            {
                $stack = [
                    'inArray' => $inArray,
                    'index' => $index,
                    'keys' => $keys,
                    'edits' => $edits,
                    'prev' => $stack
                ];
                $inArray =  \is_array($node);

                if (is_array($node))
                {
                    $keys = $node;
                }
                elseif($node !== null && $node instanceof Node)
                {
                    $kind = $node->kind;
                    $keys = $visitorKeys[$kind] ?? [];
                }
                else
                {
                    $keys = [];
                }

                $index = -1;
                $edits = [];
                if ($parent !== null)
                {
                    $ancestors[] = $parent;
                }
                $parent = $node;
            }

        } while ($stack !== null);

        if (\count($edits) !== 0)
        {
            //var_dump($edits);
            $newRoot = $edits[0][1];
        }

        return $newRoot;
    }

    /**
     * Returns marker for visitor break
     *
     * @api
     * @return VisitorOperation
     */
    public static function stop():VisitorOperation
    {
        $r = new VisitorOperation();
        $r->doBreak = true;
        return $r;
    }

    /**
     * Returns marker for skipping current node
     *
     * @api
     * @return VisitorOperation
     */
    public static function skipNode():VisitorOperation
    {
        $r = new VisitorOperation();
        $r->doContinue = true;
        return $r;
    }

    /**
     * Returns marker for removing a node
     *
     * @api
     * @return VisitorOperation
     */
    public static function removeNode():VisitorOperation
    {
        $r = new VisitorOperation();
        $r->removeNode = true;
        return $r;
    }

    /**
     * @param $visitors
     * @return array
     */
    public static function visitInParallel(array<mixed> $visitors)
    {
        $visitorsCount = \count($visitors);
        $skipping = new \SplFixedArray($visitorsCount);

        return [
            'enter' => function ($node) use ($visitors, $skipping, $visitorsCount)
            {
                for ($i = 0; $i < $visitorsCount; $i++)
                {
                    if ($skipping->offsetExists($i) && $skipping->offsetGet($i) === null)
                    {
                        $fn = Visitor::getVisitFn($visitors[$i], $node->kind, /* isLeaving */ false);

                        if ($fn)
                        {
                            $result = \call_user_func_array($fn, \func_get_args());

                            if ($result instanceof VisitorOperation)
                            {
                                if ($result->doContinue)
                                {
                                    $skipping->offsetSet($i, $node);
                                }
                                else if ($result->doBreak)
                                {
                                    $skipping->offsetSet($i, $result);
                                }
                                else if ($result->removeNode)
                                {
                                    return $result;
                                }
                            }
                            else if ($result !== null)
                            {
                                return $result;
                            }
                        }
                    }
                }
            },
            'leave' => function ($node) use ($visitors, $skipping, $visitorsCount) {
                for ($i = 0; $i < $visitorsCount; $i++)
                {
                    if ($skipping->offsetExists($i) && $skipping->offsetGet($i) === null)
                    {
                        $fn = Visitor::getVisitFn($visitors[$i], $node->kind, /* isLeaving */ true);
                        if ($fn !== null)
                        {
                            $result = \call_user_func_array($fn, \func_get_args());
                            if ($result instanceof VisitorOperation)
                            {
                                if ($result->doBreak)
                                {
                                    $skipping->offsetSet($i, $result);
                                }
                                else if ($result->removeNode)
                                {
                                    return $result;
                                }
                            }
                            else if ($result !== null)
                            {
                                return $result;
                            }
                        }
                    }
                    else if ($skipping->offsetGet($i) === $node)
                    {
                        $skipping->offsetSet($i, null);
                    }
                }
            }
        ];
    }

    /**
     * Creates a new visitor instance which maintains a provided TypeInfo instance
     * along with visiting visitor.
     */
    public static function visitWithTypeInfo(TypeInfo $typeInfo, array<string, mixed> $visitor):array<string, (function(mixed):mixed)>
    {
        return [
            'enter' => function ($node) use ($typeInfo, $visitor) {
                $typeInfo->enter($node);
                $fn = Visitor::getVisitFn($visitor, $node->kind, false);

                if ($fn) {
                    $result = \call_user_func_array($fn, \func_get_args());
                    if ($result) {
                        $typeInfo->leave($node);
                        if ($result instanceof Node) {
                            $typeInfo->enter($result);
                        }
                    }
                    return $result;
                }
                return null;
            },
            'leave' => function ($node) use ($typeInfo, $visitor) {
                $fn = Visitor::getVisitFn($visitor, $node->kind, true);
                $result = $fn ? \call_user_func_array($fn, \func_get_args()) : null;
                $typeInfo->leave($node);
                return $result;
            }
        ];
    }

    /**
     * @param $visitor
     * @param $kind
     * @param $isLeaving
     * @return null
     */
    public static function getVisitFn(array<string, mixed> $visitor, string $kind, bool $isLeaving):?callable
    {
        if (!$visitor) {
            return null;
        }
        $kindVisitor = \array_key_exists($kind, $visitor) ? $visitor[$kind] : null;

        if (!$isLeaving && \is_callable($kindVisitor)) {
            // { Kind() {} }
            return $kindVisitor;
        }

        if (is_array($kindVisitor))
        {
            if ($isLeaving) {
                $kindSpecificVisitor = \array_key_exists('leave', $kindVisitor) ? $kindVisitor['leave'] : null;
            }
            else
            {
                $kindSpecificVisitor = \array_key_exists('enter', $kindVisitor) ? $kindVisitor['enter'] : null;
            }

            if ($kindSpecificVisitor && \is_callable($kindSpecificVisitor))
            {
                // { Kind: { enter() {}, leave() {} } }
                return $kindSpecificVisitor;
            }
            return null;
        }

        $visitor += ['leave' => null, 'enter' => null];
        $specificVisitor = $isLeaving ? $visitor['leave'] : $visitor['enter'];

        if ($specificVisitor)
        {
            if (\is_callable($specificVisitor))
            {
                // { enter() {}, leave() {} }
                return $specificVisitor;
            }
            $specificKindVisitor = \array_key_exists($kind, $specificVisitor) ? $specificVisitor[$kind] : null;

            if (\is_callable($specificKindVisitor))
            {
                // { enter: { Kind() {} }, leave: { Kind() {} } }
                return $specificKindVisitor;
            }
        }
        return null;
    }
}

