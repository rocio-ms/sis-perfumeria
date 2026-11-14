<?php

namespace App\Support;

use Normalizer;

final class BusquedaNombre
{
    public static function normalizar(string $nombre): string
    {
        return preg_replace(
            '/[\p{M}\p{Z}\s]+/u',
            '',
            Normalizer::normalize(mb_strtolower($nombre), Normalizer::FORM_D)
        );
    }
}
