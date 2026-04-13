<?php

declare(strict_types=1);

namespace SuperFaktura\Exception;

/**
 * Base exception for all ARES library errors.
 * Allows callers to catch all ARES-related errors with a single catch block.
 */
class AresException extends \RuntimeException {}
