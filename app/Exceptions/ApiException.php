<?php

namespace App\Exceptions;

/**
 * Backward-compatible alias for domain/API flow errors.
 *
 * TODO: Migrate remaining call sites to DomainAppException directly.
 */
class ApiException extends DomainAppException {}
