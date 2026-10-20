<?php

namespace App\Services\Invoices;

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;

/**
 * Renders a UPI payment link as a PNG QR code for invoices.
 */
class UpiQrCode
{
    public function dataUri(string $upiLink, int $size = 240): string
    {
        $png = (new Writer(new GDLibRenderer($size, 1)))->writeString($upiLink);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
