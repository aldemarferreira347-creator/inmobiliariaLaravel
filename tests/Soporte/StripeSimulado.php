<?php

namespace Tests\Soporte;

use Closure;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

/**
 * Cliente HTTP de Stripe para pruebas: el servicio real corre completo y solo
 * la red se simula. Guarda cada petición para poder inspeccionar lo enviado.
 */
class StripeSimulado implements ClientInterface
{
    /** @var array<int, array{metodo: string, url: string, params: array}> */
    public array $peticiones = [];

    /** @param  Closure(string, string, array): array{0: string, 1: int, 2: array}  $respuesta */
    private function __construct(private readonly Closure $respuesta) {}

    public static function instalar(Closure $respuesta): self
    {
        $cliente = new self($respuesta);
        ApiRequestor::setHttpClient($cliente);

        return $cliente;
    }

    public static function desinstalar(): void
    {
        ApiRequestor::setHttpClient(null);
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->peticiones[] = ['metodo' => $method, 'url' => $absUrl, 'params' => $params];

        return ($this->respuesta)($method, $absUrl, $params);
    }

    /** Respuesta 200 con el objeto dado. */
    public static function ok(array $objeto): array
    {
        return [json_encode($objeto), 200, []];
    }

    /** Respuesta de error de tarjeta (HTTP 402) con el código indicado. */
    public static function errorDeTarjeta(string $codigo, string $mensaje = 'Error de tarjeta.'): array
    {
        return [json_encode(['error' => ['type' => 'card_error', 'code' => $codigo, 'message' => $mensaje]]), 402, []];
    }
}
