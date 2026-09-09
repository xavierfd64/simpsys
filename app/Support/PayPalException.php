<?php

namespace App\Support;

use RuntimeException;

/**
 * Always carries a message safe to show a Platform Admin or tenant — never
 * built from a raw PayPal error body without first stripping anything that
 * could contain the client secret or an access token (neither ever appears
 * in a PayPal error response, but callers constructing messages here must
 * keep it that way).
 */
class PayPalException extends RuntimeException {}
