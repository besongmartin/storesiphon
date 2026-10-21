<?php

namespace App\Domain\Extraction;

use RuntimeException;

/** Thrown at a checkpoint when the user cancelled the job; products saved so far are kept. */
final class ExtractionCancelled extends RuntimeException {}
