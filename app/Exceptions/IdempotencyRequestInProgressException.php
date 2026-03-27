<?php

namespace App\Exceptions;

use RuntimeException;

class IdempotencyRequestInProgressException extends RuntimeException {}
