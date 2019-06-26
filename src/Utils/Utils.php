<?hh //partial

namespace GraphQL\Utils;

use GraphQL\Error\InvariantViolation;
use GraphQL\Error\Warning;
use GraphQL\Type\Definition\GraphQlType;
use GraphQL\Type\Definition\WrappingType;
use \Traversable, \InvalidArgumentException;
use stdClass;


type callback1arg = (function(mixed): mixed);
type callback2arg = (function(mixed, mixed): mixed);

class Utils
{
    public static function undefined():stdClass
    {
        static $undefined;
        return $undefined ?? $undefined = new stdClass();
    }

    /**
     * @param object $obj
     * @param array  $vars
     * @param array  $requiredKeys
     *
     * @return array
     */
    public static function assign<T>(T $obj, array<string, mixed> $vars, array<string> $requiredKeys = []):T
    {
        foreach ($requiredKeys as $key)
        {
            if (!(\array_key_exists($key, $vars) && $vars[$key] !== null))
            {
                throw new InvalidArgumentException("Key {$key} is expected to be set and not to be null");
            }
        }

        foreach ($vars as $key => $value)
        {
            if (!\property_exists($obj, $key))
            {
                $cls = \get_class($obj);
                Warning::warn(
                    "Trying to set non-existing property '$key' on class '$cls'",
                    Warning::WARNING_ASSIGN
                );
            }
            $obj->$key = $value;
        }
        return $obj;
    }

    /**
     * @param array|Traversable $traversable
     * @param callable $predicate
     * @return null
     */
    public static function find<Tv>(array<Tv> $traversable, callback2arg $predicate):?Tv
    {
        self::invariant(is_array($traversable) || $traversable instanceof \Traversable, __METHOD__ . ' expects array or Traversable');

        foreach ($traversable as $key => $value) {
            if ($predicate($value, $key)) {
                return $value;
            }
        }
        return null;
    }

    /**
     * @param $traversable
     * @param callable $predicate
     * @return array
     * @throws \Exception
     */
    public static function filter<Tk, Tv>(array<Tk, Tv> $traversable, callback2arg $predicate):array<Tv>
    {
        self::invariant(is_array($traversable) || $traversable instanceof \Traversable, __METHOD__ . ' expects array or Traversable');

        $result = [];
        $assoc = false;
        foreach ($traversable as $key => $value)
        {
            if (!$assoc && !($key is int))
            {
                $assoc = true;
            }
            if ($predicate($value, $key))
            {
                $result[$key] = $value;
            }
        }

        /* HH_FIXME[4110]*/
        return $assoc ? $result : \array_values($result);
    }

    /**
     * @param array|\Traversable $traversable
     * @param callable $fn function($value, $key) => $newValue
     * @return array
     * @throws \Exception
     */

    public static function map<T,TU>(array<T> $traversable, (function(T, arraykey): TU) $fn):array<arraykey, TU>
    {
        self::invariant(is_array($traversable) || $traversable instanceof \Traversable, __METHOD__ . ' expects array or Traversable');

        $map = [];
        foreach ($traversable as $key => $value) {
            $map[$key] = $fn($value, $key);
        }
        return $map;
    }

    /**
     * @param $traversable
     * @param callable $fn
     * @return array
     * @throws \Exception
     */
    public static function mapKeyValue<Tk, Tv>(array<Tk, Tv> $traversable, (function(Tv, Tk): (mixed, mixed)) $fn):array<mixed, mixed>
    {
        self::invariant(is_array($traversable) || $traversable instanceof \Traversable, __METHOD__ . ' expects array or Traversable');

        $map = [];
        foreach ($traversable as $key => $value)
        {
            list($newKey, $newValue) = $fn($value, $key);
            $map[$newKey] = $newValue;
        }
        return $map;
    }

    /**
     * @param $traversable
     * @param callable $keyFn function($value, $key) => $newKey
     * @return array
     * @throws \Exception
     */
    public static function keyMap<Tk, Tv>(array<Tk, Tv> $traversable, (function(Tv, Tk):arraykey) $keyFn):array<arraykey, Tv>
    {
        self::invariant(is_array($traversable) || $traversable instanceof \Traversable, __METHOD__ . ' expects array or Traversable');

        $map = [];
        foreach ($traversable as $key => $value) {
            $newKey = $keyFn($value, $key);
            if (\is_scalar($newKey)) {
                $map[$newKey] = $value;
            }
        }
        return $map;
    }

    /**
     * @param $traversable
     * @param callable $fn
     */
    public static function each<Tk, Tv>(array<Tk, Tv> $traversable, callback2arg $fn):void
    {
        self::invariant(is_array($traversable) || $traversable instanceof \Traversable, __METHOD__ . ' expects array or Traversable');

        foreach ($traversable as $key => $item) {
            $fn($item, $key);
        }
    }

    /**
     * Splits original traversable to several arrays with keys equal to $keyFn return
     *
     * E.g. Utils::groupBy([1, 2, 3, 4, 5], function($value) {return $value % 3}) will output:
     * [
     *    1 => [1, 4],
     *    2 => [2, 5],
     *    0 => [3],
     * ]
     *
     * $keyFn is also allowed to return array of keys. Then value will be added to all arrays with given keys
     *
     * @param $traversable
     * @param callable $keyFn function($value, $key) => $newKey(s)
     * @return array
     */
    public static function groupBy<Tk, Tv>(array<Tk, Tv> $traversable, callback2arg $keyFn):array<Tk, array<Tv>>
    {
        self::invariant(is_array($traversable) || $traversable instanceof \Traversable, __METHOD__ . ' expects array or Traversable');

        $grouped = [];
        foreach ($traversable as $key => $value) {
            $newKeys = (array) $keyFn($value, $key);
            foreach ($newKeys as $key) {
                $grouped[$key][] = $value;
            }
        }

        return $grouped;
    }

    /**
     * @param array|Traversable $traversable
     * @param callable $keyFn
     * @param callable $valFn
     * @return array
     */
    public static function keyValMap<T>( array<T> $traversable, callback1arg $keyFn, callback1arg $valFn):array<mixed, mixed>
    {
        $map = [];
        foreach ($traversable as $item) {
            $map[$keyFn($item)] = $valFn($item);
        }
        return $map;
    }

    /**
     * @param $traversable
     * @param callable $predicate
     * @return bool
     */
    public static function every<Tk, Tv>(array<Tk, Tv> $traversable, (function(Tv, Tk):bool) $predicate):bool
    {
        foreach ($traversable as $key => $value) {
            if (!$predicate($value, $key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param $test
     * @param string $message
     * @param mixed $sprintfParam1
     * @param mixed $sprintfParam2 ...
     * @throws InvariantViolation
     */
    public static function invariant($test, string $message = '', mixed ... $args):void
    {
        if (!$test) {
            if (\count($args) > 0)
            {
                $message = \call_user_func_array('sprintf', array_merge([$message], $args));
            }
            throw new InvariantViolation($message);
        }
    }

    /**
     * @param $var
     * @return string
     */
    public static function getVariableType(mixed $var):string
    {
        if ($var instanceof GraphQlType) {
            // FIXME: Replace with schema printer call
            if ($var instanceof WrappingType) {
                $var = $var->getWrappedType(true);
            }
            return $var->name;
        }
        return is_object($var) ? \get_class($var) : \gettype($var);
    }

    /**
     * @param mixed $var
     * @return string
     */
    public static function printSafeJson(mixed $var):string
    {
        if ($var instanceof stdClass) {
            $var = (array) $var;
        }
        if (is_array($var)) {
            $count = \count($var);
            if (!isset($var[0]) && $count > 0) {
                $keys = [];
                $keyCount = 0;
                foreach ($var as $key => $value) {
                    $keys[] = '"' . $key . '"';
                    if ($keyCount++ > 4) {
                        break;
                    }
                }
                $keysLabel = $keyCount === 1 ? 'key' : 'keys';
                $msg = "object with first $keysLabel: " . \implode(', ', $keys);
            } else {
                $msg = "array($count)";
            }
            return $msg;
        }
        if ('' === $var) {
            return '(empty string)';
        }
        if (null === $var) {
            return 'null';
        }
        if (false === $var) {
            return 'false';
        }
        if (true === $var) {
            return 'false';
        }
        if ($var is string) {
            return "\"$var\"";
        }
        if (\is_scalar($var)) {
            return (string) $var;
        }
        return \gettype($var);
    }

    /**
     * @param $var
     * @return string
     */
    public static function printSafe(mixed $var):string
    {
        if ($var instanceof GraphQlType) {
            return $var->toString();
        }
        if (is_object($var)) {
            return 'instance of ' . \get_class($var);
        }
        if (is_array($var)) {
            $count = \count($var);
            if (!isset($var[0]) && $count > 0) {
                $keys = [];
                $keyCount = 0;
                foreach ($var as $key => $value) {
                    $keys[] = '"' . $key . '"';
                    if ($keyCount++ > 4) {
                        break;
                    }
                }
                $keysLabel = $keyCount === 1 ? 'key' : 'keys';
                $msg = "associative array($count) with first $keysLabel: " . \implode(', ', $keys);
            } else {
                $msg = "array($count)";
            }
            return $msg;
        }
        if ('' === $var) {
            return '(empty string)';
        }
        if (null === $var) {
            return 'null';
        }
        if (false === $var) {
            return 'false';
        }
        if (true === $var) {
            return 'true';
        }
        if ($var is string) {
            return "\"$var\"";
        }
        if (\is_scalar($var)) {
            return (string) $var;
        }
        return \gettype($var);
    }

    /**
     * UTF-8 compatible chr()
     *
     * @param string $ord
     * @param string $encoding
     * @return string
     */
    public static function chr($ord, $encoding = 'UTF-8')
    {
        if ($ord <= 255) {
            return \chr($ord);
        }
        if ($encoding === 'UCS-4BE') {
            return \pack("N", $ord);
        } else {
            return \mb_convert_encoding(self::chr($ord, 'UCS-4BE'), $encoding, 'UCS-4BE');
        }
    }

    /**
     * UTF-8 compatible ord()
     *
     * @param string $char
     * @param string $encoding
     * @return mixed
     */
    public static function ord(string $char, string $encoding = 'UTF-8'):int
    {
        if (!$char && '0' !== $char) {
            return 0;
        }
        if (!isset($char[1])) {
            return \ord($char);
        }
        if ($encoding !== 'UCS-4BE') {
            $char = \mb_convert_encoding($char, 'UCS-4BE', $encoding);
        }
        $list = \unpack('N', $char);
        /*list($a, $ord) = $list;*/
        return $list[1];
    }

    /**
     * Returns UTF-8 char code at given $positing of the $string
     *
     * @param $string
     * @param $position
     * @return mixed
     */
    public static function charCodeAt(string $string, int $position):mixed
    {
        $char = \mb_substr($string, $position, 1, 'UTF-8');
        return self::ord($char);
    }

    /**
     * @param $code
     * @return string
     */
    public static function printCharCode(?int $code):string
    {
        if (null === $code)
        {
            return '<EOF>';
        }
        return $code < 0x007F
            // Trust JSON for ASCII.
            ? \json_encode(Utils::chr($code))
            // Otherwise print the escaped form.
            : '"\\u' . \dechex($code) . '"';
    }

    /**
     * @param $name
     * @param bool $isIntrospection
     * @throws InvariantViolation
     */
    public static function assertValidName(mixed $name, bool $isIntrospection = false):void
    {
        $regex = '/^[_a-zA-Z][_a-zA-Z0-9]*$/';

        if (!$name || !($name is string)) {
            throw new InvariantViolation(
                "Must be named. Unexpected name: " . self::printSafe($name)
            );
        }

        if (!$isIntrospection && isset($name[1]) && $name[0] === '_' && $name[1] === '_') {
            Warning::warnOnce(
                'Name "'.$name.'" must not begin with "__", which is reserved by ' .
                'GraphQL introspection. In a future release of graphql this will ' .
                'become an exception',
                Warning::WARNING_NAME
            );
        }

        if (!\preg_match($regex, $name)) {
            throw new InvariantViolation(
                'Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but "'.$name.'" does not.'
            );
        }
    }

    /**
     * Wraps original closure with PHP error handling (using set_error_handler).
     * Resulting closure will collect all PHP errors that occur during the call in $errors array.
     *
     * @param callable $fn
     * @param \ErrorException[] $errors
     * @return \Closure
     */
    public static function withErrorHandling((function(): mixed) $fn, array &$errors):(function(): mixed)
    {
        return function() use ($fn, &$errors) {
            // Catch custom errors (to report them in query results)
            \set_error_handler(function ($severity, $message, $file, $line) use (&$errors) {
                $errors[] = new \ErrorException($message, 0, $severity, $file, $line);
            });

            try {
                return $fn();
            } finally {
                \restore_error_handler();
            }
        };
    }
}
