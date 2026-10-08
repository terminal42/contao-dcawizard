<?php

declare(strict_types=1);

namespace Terminal42\DcawizardBundle;

/**
 * @implements \ArrayAccess<string, scalar>
 */
final class UrlConfig implements \ArrayAccess
{
    public function __construct(private array $data)
    {
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }

    public function getForeignTable(): string|null
    {
        return $this->data['foreignTable'] ?? null;
    }

    public function getField(): string|null
    {
        return $this->data['field'] ?? null;
    }

    public function getCurrentRecord(): int|null
    {
        return $this->data['currentRecord'] ?? null;
    }

    /**
     * Table the dcaWizard field is defined on.
     */
    public function getParentTable(): string|null
    {
        return $this->data['parentTable'] ?? null;
    }

    public function isOperation(): bool
    {
        return (bool) ($this->data['operation'] ?? null);
    }

    /**
     * Encodes the DcaWizard configuration for the URL.
     */
    public function urlEncode(): string
    {
        $data = 'dcawizard:'.json_encode($this->data, JSON_THROW_ON_ERROR);

        if (\function_exists('gzencode') && false !== ($encoded = @gzencode($data))) {
            $data = $encoded;
        }

        return strtr(base64_encode($data), '+/=', '-_,');
    }

    /**
     * Initializes the DcaWizard configuration from the URL data.
     */
    public static function urlDecode(string $data): self|null
    {
        $decoded = base64_decode(strtr($data, '-_,', '+/='), true);

        if (\function_exists('gzdecode') && false !== ($uncompressed = @gzdecode($decoded))) {
            $decoded = $uncompressed;
        }

        if (!str_starts_with($decoded, 'dcawizard:')) {
            return null;
        }

        try {
            $json = json_decode(substr($decoded, 10), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return new self($json);
    }
}
