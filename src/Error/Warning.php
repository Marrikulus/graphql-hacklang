<?hh //strict
namespace GraphQL\Error;

type warnFuncType = (function(string, int): void);
/**
 * Encapsulates warnings produced by the library.
 *
 * Warnings can be suppressed (individually or all) if required.
 * Also it is possible to override warning handler (which is **trigger_error()** by default)
 */
final class Warning
{
    const WARNING_NAME = 1;
    const WARNING_ASSIGN = 2;
    const WARNING_CONFIG = 4;
    const WARNING_FULL_SCHEMA_SCAN = 8;
    const WARNING_CONFIG_DEPRECATION = 16;
    const WARNING_NOT_A_TYPE = 32;
    const ALL = 63;

    static int $enableWarnings = self::ALL;

    static array<bool> $warned = [];

    static private ?warnFuncType  $warningHandler;

    /**
     * Sets warning handler which can intercept all system warnings.
     * When not set, trigger_error() is used to notify about warnings.
     *
     * @api
     * @param callable|null $warningHandler
     */
    public static function setWarningHandler(?warnFuncType $warningHandler = null):void
    {
        self::$warningHandler = $warningHandler;
    }

    /**
     * Suppress warning by id (has no effect when custom warning handler is set)
     *
     * Usage example:
     * Warning::suppress(Warning::WARNING_NOT_A_TYPE)
     *
     * When passing true - suppresses all warnings.
     *
     * @api
     * @param int $suppress
     */
    public static function suppress(int $suppress = 1):void
    {
        if (1 === $suppress) {
            self::$enableWarnings = 0;
        } else if (0 === $suppress) {
            self::$enableWarnings = self::ALL;
        } else {
            $suppress = (int) $suppress;
            self::$enableWarnings &= ~$suppress;
        }
    }

    /**
     * Re-enable previously suppressed warning by id
     *
     * Usage example:
     * Warning::suppress(Warning::WARNING_NOT_A_TYPE)
     *
     * When passing true - re-enables all warnings.
     *
     * @api
     * @param int $enable
     */
    public static function enable(int $enable = 1):void
    {
        if (1 === $enable) {
            self::$enableWarnings = self::ALL;
        } else if (0 === $enable) {
            self::$enableWarnings = 0;
        } else {
            $enable = (int) $enable;
            self::$enableWarnings |= $enable;
        }
    }

    public static function warnOnce(string $errorMessage, int $warningId, ?int $messageLevel = null):void
    {
        if (self::$warningHandler) {
            $fn = self::$warningHandler;
            $fn($errorMessage, $warningId);
            /* HH_FIXME[4016]*/
        } else if ((self::$enableWarnings & $warningId) > 0 && !\isset(self::$warned[$warningId])) {
            self::$warned[$warningId] = true;
            \trigger_error($errorMessage, $messageLevel ?? \E_USER_WARNING);
        }
    }

    public static function warn(string $errorMessage, int $warningId, ?int $messageLevel = null):void
    {
        if (self::$warningHandler) {
            $fn = self::$warningHandler;
            $fn($errorMessage, $warningId);
        } else if ((self::$enableWarnings & $warningId) > 0) {
            \trigger_error($errorMessage, $messageLevel ?? \E_USER_WARNING);
        }
    }
}
