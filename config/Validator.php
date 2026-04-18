<?php

class Validator
{
    private array $data;
    private array $rules = [];
    private array $errors = [];
    private array $afterCallbacks = [];

    public function __construct($data = [])
    {
        $this->data = $this->normalizeData($data);
    }

    public function rule(string $field, $rules): self
    {
        $fieldRules = is_array($rules) ? $rules : explode('|', (string) $rules);

        foreach ($fieldRules as $rule) {
            $this->rules[$field][] = $rule;
        }

        return $this;
    }

    public function after(callable $callback): self
    {
        $this->afterCallbacks[] = $callback;
        return $this;
    }

    public function validate(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $rules) {
            $value = $this->value($field);

            foreach ($rules as $rule) {
                if ($this->hasError($field)) {
                    break;
                }

                if (is_callable($rule)) {
                    $result = $rule($value, $field, $this);

                    if (is_string($result) && $result !== '') {
                        $this->addError($field, $result);
                    }

                    continue;
                }

                [$ruleName, $parameter] = $this->parseRule($rule);

                if ($ruleName === 'nullable' && $this->isEmpty($value)) {
                    break;
                }

                if ($this->isEmpty($value) && !in_array($ruleName, ['required', 'accepted'], true)) {
                    continue;
                }

                $message = $this->applyRule($field, $value, $ruleName, $parameter);

                if ($message !== null) {
                    $this->addError($field, $message);
                }
            }
        }

        foreach ($this->afterCallbacks as $callback) {
            $callback($this);
        }

        return empty($this->errors);
    }

    public function value(string $field, $default = null)
    {
        return $this->data[$field] ?? $default;
    }

    public function addError(string $field, string $message): void
    {
        if (!$this->hasError($field)) {
            $this->errors[$field] = $message;
        }
    }

    public function hasError(string $field): bool
    {
        return array_key_exists($field, $this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function all(): array
    {
        return array_values($this->errors);
    }

    public function firstError(): ?string
    {
        $errors = $this->all();
        return $errors[0] ?? null;
    }

    private function normalizeData($data): array
    {
        if (is_object($data)) {
            return get_object_vars($data);
        }

        return is_array($data) ? $data : [];
    }

    private function parseRule($rule): array
    {
        $rule = (string) $rule;
        $parts = explode(':', $rule, 2);

        return [$parts[0], $parts[1] ?? null];
    }

    private function applyRule(string $field, $value, string $ruleName, ?string $parameter): ?string
    {
        switch ($ruleName) {
            case 'required':
                return $this->isEmpty($value) ? $this->label($field) . ' is required.' : null;

            case 'accepted':
                return $this->isAccepted($value) ? null : $this->label($field) . ' must be accepted.';

            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) ? null : $this->label($field) . ' must be a valid email address.';

            case 'min':
                return $this->length($value) < (int) $parameter
                    ? $this->label($field) . ' must be at least ' . (int) $parameter . ' characters.'
                    : null;

            case 'max':
                return $this->length($value) > (int) $parameter
                    ? $this->label($field) . ' cannot exceed ' . (int) $parameter . ' characters.'
                    : null;

            case 'in':
                $allowed = array_map('trim', explode(',', (string) $parameter));
                return in_array((string) $value, $allowed, true)
                    ? null
                    : $this->label($field) . ' is invalid.';

            case 'regex':
                return preg_match((string) $parameter, (string) $value)
                    ? null
                    : $this->label($field) . ' format is invalid.';

            case 'json':
                json_decode((string) $value);
                return json_last_error() === JSON_ERROR_NONE
                    ? null
                    : $this->label($field) . ' must be valid JSON.';

            case 'array':
                return is_array($value) ? null : $this->label($field) . ' must be an array.';
        }

        return null;
    }

    private function isEmpty($value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return count($value) === 0;
        }

        return false;
    }

    private function isAccepted($value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
    }

    private function length($value): int
    {
        if (is_array($value)) {
            return count($value);
        }

        $stringValue = (string) $value;

        if (function_exists('mb_strlen')) {
            return mb_strlen($stringValue);
        }

        return strlen($stringValue);
    }

    private function label(string $field): string
    {
        $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $field);
        $label = str_replace('_', ' ', $label);

        return ucfirst(strtolower((string) $label));
    }
}
