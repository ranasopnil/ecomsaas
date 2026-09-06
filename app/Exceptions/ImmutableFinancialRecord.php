<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when something tries to edit a financial record that is already made.
 * Corrections are new rows, never changes to old ones.
 */
class ImmutableFinancialRecord extends RuntimeException {}
