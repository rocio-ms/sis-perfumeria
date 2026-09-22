<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;

class JsonPeService
{
    public function consultarDni(string $dni): array
    {
        $response = Http::withToken(config('services.jsonpe.token'))
            ->acceptJson()
            ->timeout(10)
            ->post('https://api.json.pe/api/dni', [
                'dni' => $dni,
            ]);

        if ($response->failed()) {
            throw new Exception(
                $response->json('message') ?? 'No se pudo consultar el DNI.'
            );
        }

        return $response->json();
    }
}