<?hh //strict
//partial
namespace GraphQL\Language\AST;

use GraphQL\Error\InvariantViolation;
use GraphQL\Utils\Utils;

abstract class Node
{
    /**
      type Node = NameNode
    | DocumentNode
    | OperationDefinitionNode
    | VariableDefinitionNode
    | VariableNode
    | SelectionSetNode
    | FieldNode
    | ArgumentNode
    | FragmentSpreadNode
    | InlineFragmentNode
    | FragmentDefinitionNode
    | IntValueNode
    | FloatValueNode
    | StringValueNode
    | BooleanValueNode
    | EnumValueNode
    | ListValueNode
    | ObjectValueNode
    | ObjectFieldNode
    | DirectiveNode
    | ListTypeNode
    | NonNullTypeNode
     */

    public function __construct(
        public ?Location $loc,
        public string $kind,
    ){
        invariant($kind !== "", "Kind should never be empty string");
    }

    public function isList(): bool
    {
        return false;
    }

    /**
     * @return $this
     */
    public function cloneDeep():mixed
    {
        return $this->cloneValue($this);
    }

    /**
     * @param $value
     * @return array|Node
     */
    private function cloneValue(mixed $value):mixed
    {
        if (is_array($value))
        {
            $cloned = [];
            foreach ($value as $key => $arrValue)
            {
                $cloned[$key] = $this->cloneValue($arrValue);
            }
        }
        else if ($value instanceof Node)
        {
            $cloned = clone $value;
            /* HH_FIXME[4110]*/
            foreach (\get_object_vars($cloned) as $prop => $propValue)
            {
                /* HH_FIXME[2011]*/
                $cloned->{$prop} = $this->cloneValue($propValue);
            }
        }
        else
        {
            $cloned = $value;
        }

        return $cloned;
    }

    /**
     * @return string
     */
    public function __toString():string
    {
        $tmp = $this->toArray(true);
        return \json_encode($tmp);
    }

    /**
     * @param bool $recursive
     * @return array
     */
    public function toArray(bool $recursive = false):array<string, mixed>
    {
        if ($recursive) {
            return $this->recursiveToArray($this);
        } else {
            /* HH_FIXME[4007]*/
            $tmp = (array) $this;

            if ($this->loc) {
                $tmp['loc'] = [
                    'start' => $this->loc->start,
                    'end' => $this->loc->end
                ];
            }

            return $tmp;
        }
    }

    /**
     * @param Node $node
     * @return array
     */
    private function recursiveToArray(Node $node):array<string, mixed>
    {
        $result = [
            'kind' => $node->kind,
        ];

        if ($node->loc) {
            $result['loc'] = [
                'start' => $node->loc->start,
                'end' => $node->loc->end
            ];
        }
        /* HH_FIXME[4110]*/
        foreach (\get_object_vars($node) as $prop => $propValue)
        {
            if (\array_key_exists($prop, $result))
            {
                continue;
            }

            if ($propValue === null)
                continue;

            if (is_array($propValue))
            {
                $tmp = [];
                foreach ($propValue as $tmp1)
                {
                    /* HH_FIXME[4007]*/
                    $tmp[] = $tmp1 instanceof Node ? $this->recursiveToArray($tmp1) : (array) $tmp1;
                }
            }
            else if ($propValue instanceof Node)
            {
                $tmp = $this->recursiveToArray($propValue);
            }
            else if (\is_scalar($propValue) || null === $propValue)
            {
                $tmp = $propValue;
            }
            else
            {
                $tmp = null;
            }

            $result[$prop] = $tmp;
        }
        return $result;
    }
}
