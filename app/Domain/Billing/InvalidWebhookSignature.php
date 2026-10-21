<?php

namespace App\Domain\Billing;

use RuntimeException;

final class InvalidWebhookSignature extends RuntimeException {}
