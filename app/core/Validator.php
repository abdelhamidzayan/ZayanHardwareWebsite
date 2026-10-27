<?php
declare(strict_types=1);

namespace App\Core;

/** Tiny server-side validator. The first error per field wins. */
final class Validator
{
    private array $errors = [];

    public function __construct(private array $data)
    {
    }

    public function value(string $field): string
    {
        $value = $this->data[$field] ?? '';

        return is_string($value) ? $value : '';
    }

    public function fail(string $field, string $message): self
    {
        $this->errors[$field] ??= $message;

        return $this;
    }

    public function required(string $field, string $message): self
    {
        return trim($this->value($field)) === '' ? $this->fail($field, $message) : $this;
    }

    public function length(string $field, int $min, int $max, string $message): self
    {
        $length = mb_strlen($this->value($field));

        return ($length < $min || $length > $max) ? $this->fail($field, $message) : $this;
    }

    public function email(string $field, string $message): self
    {
        return filter_var($this->value($field), FILTER_VALIDATE_EMAIL) === false ? $this->fail($field, $message) : $this;
    }

    public function pattern(string $field, string $regex, string $message): self
    {
        return preg_match($regex, $this->value($field)) !== 1 ? $this->fail($field, $message) : $this;
    }

    public function in(string $field, array $allowed, string $message): self
    {
        return in_array($this->value($field), $allowed, true) ? $this : $this->fail($field, $message);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
