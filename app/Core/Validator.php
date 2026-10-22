<?php
declare(strict_types=1);

namespace UMP\Core;

/** Minimal rule-based validator: new Validator($data, ['email' => 'required|email|max:190']). */
final class Validator
{
    private array $errors = [];
    private array $clean = [];

    public function __construct(array $data, array $rules)
    {
        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? '';
            $value = is_string($value) ? trim($value) : '';
            $this->clean[$field] = $value;

            foreach (explode('|', $ruleString) as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $error = match ($name) {
                    'required' => $value === '' ? 'is required' : null,
                    'email' => $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL) ? 'must be a valid email address' : null,
                    'min' => $value !== '' && mb_strlen($value) < (int) $arg ? "must be at least $arg characters" : null,
                    'max' => mb_strlen($value) > (int) $arg ? "must be at most $arg characters" : null,
                    'url' => $value !== '' && !filter_var($value, FILTER_VALIDATE_URL) ? 'must be a valid URL' : null,
                    'in' => $value !== '' && !in_array($value, explode(',', (string) $arg), true) ? 'has an invalid value' : null,
                    'date' => $value !== '' && !strtotime($value) ? 'must be a valid date' : null,
                    default => null,
                };
                if ($error) {
                    $this->errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' ' . $error . '.';
                    break;
                }
            }
        }
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function first(): string
    {
        return $this->errors ? reset($this->errors) : '';
    }

    public function data(): array
    {
        return $this->clean;
    }
}
