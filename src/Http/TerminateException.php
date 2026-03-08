<?php

declare(strict_types=1);

namespace ICO\Http;

use RuntimeException;

/**
 * Thrown in place of exit() to allow controller termination paths to be tested.
 *
 * Production usage: catch in the front controller (index.php) and call exit.
 * Test usage: catch in test methods to verify side effects before termination.
 */
class TerminateException extends RuntimeException
{
}
