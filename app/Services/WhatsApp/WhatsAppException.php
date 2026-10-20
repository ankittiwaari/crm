<?php

namespace App\Services\WhatsApp;

use RuntimeException;

/**
 * Meta rejected a WhatsApp request, or the integration is not set up.
 */
class WhatsAppException extends RuntimeException {}
