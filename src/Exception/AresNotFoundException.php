<?php

declare(strict_types=1);

namespace SuperFaktura\Exception;

/**
 * Thrown when ARES returns HTTP 404 (company not found).
 */
class AresNotFoundException extends AresException {}
