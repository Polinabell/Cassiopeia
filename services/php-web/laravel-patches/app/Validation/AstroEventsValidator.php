<?php

namespace App\Validation;

/**
 * Валидатор для запросов астрономических событий.
 *
 * Валидирует параметры:
 * - lat: широта (-90 до 90)
 * - lon: долгота (-180 до 180)
 * - days: количество дней (1-366)
 * - body: небесное тело (sun, moon, mars и т.д.)
 */
class AstroEventsValidator extends AbstractValidator
{
    public const ALLOWED_BODIES = [
        'sun', 'moon', 'mercury', 'venus', 'mars',
        'jupiter', 'saturn', 'uranus', 'neptune'
    ];

    protected array $defaults = [
        'lat' => 55.7558,
        'lon' => 37.6176,
        'days' => 7,
        'body' => 'sun',
    ];

    protected function prepareData(): void
    {
        foreach ($this->defaults as $key => $default) {
            if (!isset($this->data[$key]) || $this->data[$key] === '') {
                $this->data[$key] = $default;
            }
        }

        if (isset($this->data['lat'])) {
            $this->data['lat'] = (float) $this->data['lat'];
        }
        if (isset($this->data['lon'])) {
            $this->data['lon'] = (float) $this->data['lon'];
        }
        if (isset($this->data['days'])) {
            $this->data['days'] = (int) $this->data['days'];
        }
        if (isset($this->data['body'])) {
            $this->data['body'] = strtolower(trim((string) $this->data['body']));
        }
    }

    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'min:-90', 'max:90'],
            'lon' => ['required', 'numeric', 'min:-180', 'max:180'],
            'days' => ['required', 'integer', 'min:1', 'max:366'],
            'body' => ['required', 'string', 'in:' . implode(',', self::ALLOWED_BODIES)],
        ];
    }

    public function messages(): array
    {
        return [
            'lat.required' => 'Широта обязательна',
            'lat.numeric' => 'Широта должна быть числом',
            'lat.min' => 'Широта не может быть меньше -90',
            'lat.max' => 'Широта не может быть больше 90',
            'lon.required' => 'Долгота обязательна',
            'lon.numeric' => 'Долгота должна быть числом',
            'lon.min' => 'Долгота не может быть меньше -180',
            'lon.max' => 'Долгота не может быть больше 180',
            'days.required' => 'Количество дней обязательно',
            'days.integer' => 'Количество дней должно быть целым числом',
            'days.min' => 'Минимальное количество дней: 1',
            'days.max' => 'Максимальное количество дней: 366',
            'body.required' => 'Небесное тело обязательно',
            'body.in' => 'Недопустимое небесное тело. Допустимые: ' . implode(', ', self::ALLOWED_BODIES),
        ];
    }

    public function attributes(): array
    {
        return [
            'lat' => 'широта',
            'lon' => 'долгота',
            'days' => 'количество дней',
            'body' => 'небесное тело',
        ];
    }

    public function getLatitude(): float
    {
        return (float) ($this->validated['lat'] ?? $this->defaults['lat']);
    }

    public function getLongitude(): float
    {
        return (float) ($this->validated['lon'] ?? $this->defaults['lon']);
    }

    public function getDays(): int
    {
        return (int) ($this->validated['days'] ?? $this->defaults['days']);
    }

    public function getBody(): string
    {
        return (string) ($this->validated['body'] ?? $this->defaults['body']);
    }
}