<?php

namespace App\AI\Exceptions;

use RuntimeException;

class UnsupportedProviderException extends RuntimeException
{
    public static function for(string $name): self
    {
        return new self("No AI provider registered for driver [{$name}]. Check config/ai.php.");
    }
}
