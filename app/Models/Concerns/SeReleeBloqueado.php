<?php

namespace App\Models\Concerns;

/**
 * Relectura con bloqueo exclusivo de la fila (SELECT ... FOR UPDATE).
 *
 * Las transiciones de estado (pagar, aprobar, cancelar, expirar) deciden sobre
 * el estado actual de la fila. Sin releerla bloqueada, dos peticiones
 * simultáneas —un doble clic, el webhook de Stripe y el administrador a la
 * vez— validan contra la misma foto vieja y aplican el cambio dos veces.
 * Solo tiene efecto dentro de una transacción.
 */
trait SeReleeBloqueado
{
    public function releerBloqueado(): static
    {
        $fila = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

        $this->setRawAttributes($fila->getAttributes(), true);

        return $this;
    }
}
