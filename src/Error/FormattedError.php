<?hh //strict
namespace GraphQL\Error;

use GraphQL\Language\SourceLocation;
use GraphQL\Type\Definition\GraphQlType;
use GraphQL\Type\Definition\WrappingType;
use GraphQL\Utils\Utils;

type Formatter = (function(\Exception):array<string,mixed>);
/**
 * This class is used for [default error formatting](error-handling.md).
 * It converts PHP exceptions to [spec-compliant errors](https://facebook.github.io/graphql/#sec-Errors)
 * and provides tools for error debugging.
 */
class FormattedError
{
    private static string $internalErrorMessage = 'Internal server error';

    /**
     * Set default error message for internal errors formatted using createFormattedError().
     * This value can be overridden by passing 3rd argument to `createFormattedError()`.
     *
     * @api
     * @param string $msg
     */
    public static function setInternalErrorMessage(string $msg):void
    {
        self::$internalErrorMessage = $msg;
    }

    /**
     * Standard GraphQL error formatter. Converts any exception to array
     * conforming to GraphQL spec.
     *
     * This method only exposes exception message when exception implements ClientAware interface
     * (or when debug flags are passed).
     *
     * For a list of available debug flags see GraphQL\Error\Debug constants.
     *
     * @api
     * @param \Throwable $e
     * @param bool|int $debug
     * @param string $internalErrorMessage
     * @return array
     * @throws \Throwable
     */
    public static function createFromException(\Exception $e, int $debug = 0, ?string $internalErrorMessage = null):array<string,mixed>
    {
        /* HH_FIXME[4105]*/
        Utils::invariant(
            $e instanceof \Exception || $e instanceof \Throwable,
            "Expected exception, got %s",
            Utils::getVariableType($e)
        );

        $internalErrorMessage = $internalErrorMessage ?? self::$internalErrorMessage;

        if ($e instanceof ClientAware) {
            $formattedError = [
                /* HH_FIXME[4053]*/
                'message' => $e->isClientSafe() ? $e->getMessage() : $internalErrorMessage,
                'category' => $e->getCategory()
            ];
        } else {
            $formattedError = [
                'message' => $internalErrorMessage,
                'category' => Error::CATEGORY_INTERNAL
            ];
        }

        if ($e instanceof Error) {
            $locations = \array_map(function(SourceLocation $loc) {
                return $loc->toSerializableArray();
            },$e->getLocations());

            if (\count($locations) !== 0) {
                $formattedError['locations'] = $locations;
            }
            if (\count($e->path) !== 0) {
                $formattedError['path'] = $e->path;
            }
        }

        if ($debug) {
            /* HH_FIXME[4110]*/
            $formattedError = self::addDebugEntries($formattedError, $e, $debug);
        }

        return $formattedError;
    }

    /**
     * Decorates spec-compliant $formattedError with debug entries according to $debug flags
     * (see GraphQL\Error\Debug for available flags)
     *
     * @param array $formattedError
     * @param \Throwable $e
     * @param bool $debug
     * @return array
     * @throws \Throwable
     */
    public static function addDebugEntries(array<string, mixed> $formattedError, \Exception $e, int $debug):array<string, mixed>
    {
        if (!$debug) {
            return $formattedError;
        }

        /* HH_FIXME[4105]*/
        Utils::invariant(
            $e instanceof \Exception || $e instanceof \Throwable,
            "Expected exception, got %s",
            Utils::getVariableType($e)
        );

        $debug = (int) $debug;

        if ($debug & Debug::RETHROW_INTERNAL_EXCEPTIONS) {
            if (!$e instanceof Error)
            {
                throw $e;
            }
            else if (($prev = $e->getPrevious()) !== null)
            {
                /* HH_FIXME[4110]*/
                throw $prev;
            }
        }

        $isInternal = !$e instanceof ClientAware || !$e->isClientSafe();

        if (($debug & Debug::INCLUDE_DEBUG_MESSAGE) && $isInternal) {
            // Displaying debugMessage as a first entry:
            $formattedError = \array_merge(['debugMessage' => $e->getMessage()], $formattedError);
        }

        if ($debug & Debug::INCLUDE_TRACE) {
            if ($e instanceof \ErrorException || $e instanceof Error) {
                $formattedError = \array_merge($formattedError, [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }

            $isTrivial = $e instanceof Error && !$e->getPrevious();

            if (!$isTrivial) {
                $debugging = $e->getPrevious() ?? $e;
                $formattedError['trace'] = static::toSafeTrace($debugging);
            }
        }
        return $formattedError;
    }


    /**
     * Prepares final error formatter taking in account $debug flags.
     * If initial formatter is not set, FormattedError::createFromException is used
     *
     * @param callable|null $formatter
     * @param $debug
     * @return callable|\Closure
     */
    public static function prepareFormatter(?Formatter $formatter = null, int $debug = 0):Formatter
    {
        $formatter = $formatter ?? function($e) {
            return FormattedError::createFromException($e);
        };
        if ($debug) {
            $formatter = function($e) use ($formatter, $debug) {
                return FormattedError::addDebugEntries($formatter($e), $e, $debug);
            };
        }
        return $formatter;
    }

    /**
     * Returns error trace as serializable array
     *
     * @api
     * @param \Throwable $error
     * @return array
     */
    public static function toSafeTrace(\Exception $error):array<string, mixed>
    {
        $trace = $error->getTrace();

        if(\array_key_exists(0, $trace) && ($t = $trace[0]) !== null && \is_array($t))
        {
            // Remove invariant entries as they don't provide much value:
            if (\array_key_exists('function', $t) && \array_key_exists('class', $t) &&
                ('GraphQL\Utils\Utils::invariant' === $t['class'].'::'.$t['function']))
            {
                \array_shift(&$trace);
            }
            // Remove root call as it's likely error handler trace:
            else if (!(\array_key_exists('file', $t) && $t['file'] !== null))
            {
                \array_shift(&$trace);
            }
        }

        /* HH_FIXME[4110]*/
        return \array_map(function($err)
        {
            $safeErr = \array_intersect_key($err, ['file' => true, 'line' => true]);

            if ($err !== null  && \is_array($err) && \array_key_exists('function', $err))
            {
                $func = $err['function'];
                $args = \count($err['args']) !== 0 ? \array_map(class_meth(__CLASS__, 'printVar'), $err['args']) : [];
                $funcStr = $func . '(' . \implode(", ", $args) . ')';

                if (\array_key_exists('class',$err))
                {
                    $safeErr['call'] = $err['class'] . '::' . $funcStr;
                } else {
                    $safeErr['function'] = $funcStr;
                }
            }

            return $safeErr;
        }, $trace);
    }

    /**
     * @param $var
     * @return string
     */
    public static function printVar(mixed $var):mixed
    {
        if ($var instanceof GraphQlType) {
            // FIXME: Replace with schema printer call
            if ($var instanceof WrappingType) {
                $var = $var->getWrappedType(true);
            }
            return 'GraphQLType: ' . $var->name;
        }

        if (is_object($var)) {
            return 'instance of ' . \get_class($var) . ($var instanceof \Countable ? '(' . \count($var) . ')' : '');
        }
        if (is_array($var)) {
            return 'array(' . \count($var) . ')';
        }
        if ('' === $var) {
            return '(empty string)';
        }
        if ($var is string) {
            return "'" . \addcslashes($var, "'") . "'";
        }
        if ($var is bool) {
            return $var === true ? 'true' : 'false';
        }
        if (\is_scalar($var)) {
            return $var;
        }
        if (null === $var) {
            return 'null';
        }
        return \gettype($var);
    }

    /**
     * @deprecated as of v0.8.0
     * @param $error
     * @param SourceLocation[] $locations
     * @return array
     */
    public static function create(string $error, array<SourceLocation> $locations = []):array<string, mixed>
    {
        $formatted = [
            'message' => $error
        ];

        if (\count($locations) !== 0) {
            $formatted['locations'] = \array_map(function($loc) { return $loc->toArray();}, $locations);
        }

        return $formatted;
    }

    /**
     * @param \ErrorException $e
     * @deprecated as of v0.10.0, use general purpose method createFromException() instead
     * @return array
     */
    public static function createFromPHPError(\ErrorException $e):array<string, mixed>
    {
        return [
            'message' => $e->getMessage(),
            'severity' => $e->getSeverity(),
            'trace' => self::toSafeTrace($e)
        ];
    }
}
