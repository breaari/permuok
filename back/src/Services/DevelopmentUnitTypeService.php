<?php

namespace App\Services;

use PDO;
use Exception;

class DevelopmentUnitTypeService
{
    private const ROLE_REAL_ESTATE = 2;
    private const ROLE_AGENT = 3;

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }

    private static function getValidPublisherUser(int $userId): array
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT id, role, real_estate_id, is_active
            FROM users
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute(['id' => $userId]);
        $user = $st->fetch();

        if (!$user) {
            throw new Exception("Usuario no encontrado");
        }

        if (!in_array((int)$user['role'], [self::ROLE_REAL_ESTATE, self::ROLE_AGENT], true)) {
            throw new Exception("No tenés permisos para administrar tipologías");
        }

        if ((int)$user['is_active'] !== 1) {
            throw new Exception("Tu cuenta está inactiva");
        }

        if (empty($user['real_estate_id'])) {
            throw new Exception("El usuario no está vinculado a una inmobiliaria");
        }

        return $user;
    }

    private static function assertMembershipAllowsPublishing(int $realEstateId): void
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT id, status, can_publish_projects, end_date
            FROM memberships
            WHERE real_estate_id = :real_estate_id
              AND deleted_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");
        $st->execute(['real_estate_id' => $realEstateId]);
        $membership = $st->fetch();

        if (!$membership) {
            throw new Exception("La inmobiliaria no tiene una membresía activa");
        }

        if ((int)($membership['status'] ?? -1) !== 1) {
            throw new Exception("La membresía de la inmobiliaria no está activa");
        }

        if ((int)($membership['can_publish_projects'] ?? 0) !== 1) {
            throw new Exception("Tu plan no permite publicar desarrollos");
        }

        if (
            !empty($membership['end_date']) &&
            strtotime((string)$membership['end_date']) < strtotime(date('Y-m-d'))
        ) {
            throw new Exception("La membresía de la inmobiliaria está vencida");
        }
    }

    private static function getOwnedDevelopment(int $userId, int $developmentId): array
    {
        $pdo = self::db();
        $user = self::getValidPublisherUser($userId);
        self::assertMembershipAllowsPublishing((int)$user['real_estate_id']);

        $st = $pdo->prepare("
            SELECT *
            FROM developments
            WHERE id = :id
              AND real_estate_id = :real_estate_id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute([
            'id' => $developmentId,
            'real_estate_id' => (int)$user['real_estate_id'],
        ]);

        $development = $st->fetch();

        if (!$development) {
            throw new Exception("Desarrollo no encontrado");
        }

        return [$user, $development];
    }

    private static function getOwnedUnitType(int $userId, int $unitTypeId): array
    {
        $pdo = self::db();
        $user = self::getValidPublisherUser($userId);
        self::assertMembershipAllowsPublishing((int)$user['real_estate_id']);

        $st = $pdo->prepare("
            SELECT dut.*, d.real_estate_id, d.status AS development_status
            FROM development_unit_types dut
            INNER JOIN developments d ON d.id = dut.development_id
            WHERE dut.id = :id
              AND dut.deleted_at IS NULL
              AND d.deleted_at IS NULL
              AND d.real_estate_id = :real_estate_id
            LIMIT 1
        ");
        $st->execute([
            'id' => $unitTypeId,
            'real_estate_id' => (int)$user['real_estate_id'],
        ]);

        $unitType = $st->fetch();

        if (!$unitType) {
            throw new Exception("Tipología no encontrada");
        }

        return [$user, $unitType];
    }

    private static function normalizeNullableNumber($value, string $label, bool $integer = false): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new Exception("{$label} debe ser un número válido");
        }

        $number = $integer ? (int)$value : (float)$value;

        if ($number < 0) {
            throw new Exception("{$label} no puede ser negativo");
        }

        return $number;
    }

    private static function validatePayload(array $data, bool $partial = false): array
    {
        $payload = [
            'unit_type' => trim((string)($data['unit_type'] ?? '')),
            'label' => trim((string)($data['label'] ?? '')),

            'rooms' => self::normalizeNullableNumber($data['rooms'] ?? null, 'Ambientes'),
            'bedrooms' => self::normalizeNullableNumber($data['bedrooms'] ?? null, 'Dormitorios', true),
            'bathrooms' => self::normalizeNullableNumber($data['bathrooms'] ?? null, 'Baños', true),
            'garages' => self::normalizeNullableNumber($data['garages'] ?? null, 'Cocheras', true),

            'area_from' => self::normalizeNullableNumber($data['area_from'] ?? null, 'Superficie desde'),
            'area_to' => self::normalizeNullableNumber($data['area_to'] ?? null, 'Superficie hasta'),

            'price_from' => self::normalizeNullableNumber($data['price_from'] ?? null, 'Precio desde'),
            'price_to' => self::normalizeNullableNumber($data['price_to'] ?? null, 'Precio hasta'),

            'currency' => trim((string)($data['currency'] ?? 'USD')),
            'available_units' => self::normalizeNullableNumber($data['available_units'] ?? null, 'Unidades disponibles', true),
        ];

        $validTypes = [
            'apartment',
            'house',
            'land',
            'commercial',
            'office',
            'warehouse',
            'garage',
            'other',
        ];

        $validCurrencies = ['ARS', 'USD'];

        if (!$partial && $payload['unit_type'] === '') {
            throw new Exception("El tipo de unidad es obligatorio");
        }

        if ($payload['unit_type'] !== '' && !in_array($payload['unit_type'], $validTypes, true)) {
            throw new Exception("Tipo de unidad inválido");
        }

        if ($payload['currency'] !== '' && !in_array($payload['currency'], $validCurrencies, true)) {
            throw new Exception("Moneda inválida");
        }

        if (mb_strlen($payload['label']) > 120) {
            throw new Exception("El nombre comercial no puede superar los 120 caracteres");
        }

        if (
            $payload['area_from'] !== null &&
            $payload['area_to'] !== null &&
            $payload['area_from'] > $payload['area_to']
        ) {
            throw new Exception("La superficie mínima no puede ser mayor a la máxima");
        }

        if (
            $payload['price_from'] !== null &&
            $payload['price_to'] !== null &&
            $payload['price_from'] > $payload['price_to']
        ) {
            throw new Exception("El precio mínimo no puede ser mayor al máximo");
        }

        if (
            $payload['rooms'] !== null &&
            $payload['bedrooms'] !== null &&
            $payload['bedrooms'] > $payload['rooms']
        ) {
            throw new Exception("Los dormitorios no deberían superar los ambientes");
        }

        if (
            $payload['rooms'] !== null &&
            $payload['bathrooms'] !== null &&
            $payload['bathrooms'] > ($payload['rooms'] + 2)
        ) {
            throw new Exception("Revisá la cantidad de baños para esta tipología");
        }

        if ($payload['rooms'] !== null && $payload['rooms'] > 50) {
            throw new Exception("Revisá la cantidad de ambientes");
        }

        if ($payload['bedrooms'] !== null && $payload['bedrooms'] > 30) {
            throw new Exception("Revisá la cantidad de dormitorios");
        }

        if ($payload['bathrooms'] !== null && $payload['bathrooms'] > 30) {
            throw new Exception("Revisá la cantidad de baños");
        }

        if ($payload['garages'] !== null && $payload['garages'] > 50) {
            throw new Exception("Revisá la cantidad de cocheras");
        }

        if ($payload['available_units'] !== null && $payload['available_units'] > 10000) {
            throw new Exception("Revisá la cantidad de unidades disponibles");
        }

        return $payload;
    }

    public static function listByDevelopment(int $userId, int $developmentId): array
    {
        [, $development] = self::getOwnedDevelopment($userId, $developmentId);
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT *
            FROM development_unit_types
            WHERE development_id = :development_id
              AND deleted_at IS NULL
            ORDER BY id ASC
        ");
        $st->execute(['development_id' => (int)$development['id']]);
        $items = $st->fetchAll() ?: [];

        return [
            'items' => $items,
        ];
    }

    public static function create(int $userId, int $developmentId, array $data): array
    {
        [, $development] = self::getOwnedDevelopment($userId, $developmentId);

        if (!in_array($development['status'], ['draft', 'paused', 'archived', 'published'], true)) {
            throw new Exception("No se pueden cargar tipologías en el estado actual del desarrollo");
        }

        $payload = self::validatePayload($data, false);
        $pdo = self::db();

        $st = $pdo->prepare("
            INSERT INTO development_unit_types (
                development_id,
                unit_type,
                label,
                rooms,
                bedrooms,
                bathrooms,
                garages,
                area_from,
                area_to,
                price_from,
                price_to,
                currency,
                available_units
            ) VALUES (
                :development_id,
                :unit_type,
                :label,
                :rooms,
                :bedrooms,
                :bathrooms,
                :garages,
                :area_from,
                :area_to,
                :price_from,
                :price_to,
                :currency,
                :available_units
            )
        ");
        $st->execute([
            'development_id' => $developmentId,
            'unit_type' => $payload['unit_type'],
            'label' => $payload['label'] !== '' ? $payload['label'] : null,
            'rooms' => $payload['rooms'] !== '' ? $payload['rooms'] : null,
            'bedrooms' => $payload['bedrooms'] !== '' ? $payload['bedrooms'] : null,
            'bathrooms' => $payload['bathrooms'] !== '' ? $payload['bathrooms'] : null,
            'garages' => $payload['garages'] !== '' ? $payload['garages'] : null,
            'area_from' => $payload['area_from'] !== '' ? $payload['area_from'] : null,
            'area_to' => $payload['area_to'] !== '' ? $payload['area_to'] : null,
            'price_from' => $payload['price_from'] !== '' ? $payload['price_from'] : null,
            'price_to' => $payload['price_to'] !== '' ? $payload['price_to'] : null,
            'currency' => $payload['currency'],
            'available_units' => $payload['available_units'] !== '' ? $payload['available_units'] : null,
        ]);

        return self::listByDevelopment($userId, $developmentId);
    }

    public static function update(int $userId, int $unitTypeId, array $data): array
    {
        [, $unitType] = self::getOwnedUnitType($userId, $unitTypeId);

        if (!in_array($unitType['development_status'], ['draft', 'paused', 'archived', 'published'], true)) {
            throw new Exception("No se puede editar la tipología en el estado actual del desarrollo");
        }

        $payload = self::validatePayload($data, true);
        $pdo = self::db();

        $fields = [];
        $params = ['id' => $unitTypeId];

        $map = [
            'unit_type',
            'label',
            'rooms',
            'bedrooms',
            'bathrooms',
            'garages',
            'area_from',
            'area_to',
            'price_from',
            'price_to',
            'currency',
            'available_units',
        ];

        foreach ($map as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $value = $payload[$field];
                $params[$field] = $value === '' ? null : $value;
            }
        }

        if (!$fields) {
            return self::listByDevelopment($userId, (int)$unitType['development_id']);
        }

        $sql = "
            UPDATE development_unit_types
            SET " . implode(", ", $fields) . "
            WHERE id = :id
            LIMIT 1
        ";

        $st = $pdo->prepare($sql);
        $st->execute($params);

        return self::listByDevelopment($userId, (int)$unitType['development_id']);
    }

    public static function delete(int $userId, int $unitTypeId): array
    {
        [, $unitType] = self::getOwnedUnitType($userId, $unitTypeId);

        if (!in_array($unitType['development_status'], ['draft', 'paused', 'archived', 'published'], true)) {
            throw new Exception("No se puede eliminar la tipología en el estado actual del desarrollo");
        }

        $pdo = self::db();

        $st = $pdo->prepare("
            UPDATE development_unit_types
            SET deleted_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");
        $st->execute(['id' => $unitTypeId]);

        return self::listByDevelopment($userId, (int)$unitType['development_id']);
    }
}
