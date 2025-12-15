<?php

namespace App\Validation;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Базовый абстрактный класс валидатора.
 *
 * Все валидаторы должны наследоваться от этого класса
 * и реализовывать метод rules() для определения правил валидации.
 */
abstract class AbstractValidator
{
    protected array $data = [];
    protected array $validated = [];
    protected array $errors = [];
    protected bool $passed = false;

    public static function fromRequest(Request $request): static
    {
        $instance = new static();
        $instance->data = array_merge(
            $request->query->all(),
            $request->request->all()
        );
        return $instance;
    }

    public static function fromArray(array $data): static
    {
        $instance = new static();
        $instance->data = $data;
        return $instance;
    }

    abstract public function rules(): array;

    public function messages(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [];
    }

    protected function prepareData(): void
    {
    }

    public function validate(): bool
    {
        $this->prepareData();

        $validator = Validator::make(
            $this->data,
            $this->rules(),
            $this->messages(),
            $this->attributes()
        );

        if ($validator->fails()) {
            $this->errors = $validator->errors()->toArray();
            $this->passed = false;
            return false;
        }

        $this->validated = $validator->validated();
        $this->passed = true;
        return true;
    }

    public function validateOrFail(): array
    {
        $this->prepareData();

        $validator = Validator::make(
            $this->data,
            $this->rules(),
            $this->messages(),
            $this->attributes()
        );

        $this->validated = $validator->validate();
        $this->passed = true;
        return $this->validated;
    }

    public function passed(): bool
    {
        return $this->passed;
    }

    public function failed(): bool
    {
        return !$this->passed;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    public function firstErrors(): array
    {
        $result = [];
        foreach ($this->errors as $field => $messages) {
            $result[$field] = $messages[0] ?? '';
        }
        return $result;
    }

    public function validated(): array
    {
        return $this->validated;
    }

    public function get(string $key, $default = null)
    {
        return $this->validated[$key] ?? $default;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function set(string $key, $value): static
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function merge(array $data): static
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }
}