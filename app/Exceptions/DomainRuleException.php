<?php

namespace App\Exceptions;

use RuntimeException;

/** A business rule refused the operation. The message is safe to show to the user. */
class DomainRuleException extends RuntimeException {}
