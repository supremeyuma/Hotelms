<?php

namespace App\Sync\Exceptions;

/**
 * A change named an entity key with no registered model.
 *
 * Thrown at the applier boundary so the HTTP layer can reject the batch with a
 * 422 that names the offending table. It extends RuntimeException deliberately:
 * the transport and cycle code already treat any throwable from a peer as a
 * failed peer, and a plain RuntimeException from modelFor() is exactly what
 * this class distinguishes from unrelated lookup failures.
 */
class UnknownSyncEntityException extends \RuntimeException {}
