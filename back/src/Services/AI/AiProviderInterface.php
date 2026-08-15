<?php

namespace App\Services\AI;

interface AiProviderInterface
{
    /**
     * Analiza una entidad inmobiliaria y devuelve una respuesta normalizada.
     *
     * Debe devolver:
     *
     * [
     *     'data' => [...],
     *     'model' => 'nombre-modelo',
     *     'tokens_used' => 0
     * ]
     */
    public function analyzeEntity(
        string $entityType,
        array $context,
        string $promptVersion
    ): array;
}