<?php

namespace App\Validation;

/**
 * Валидатор для запросов скачивания телеметрии (XLSX/CSV).
 *
 * Валидирует параметры:
 * - limit: количество записей (1-1000)
 * - sort: колонка сортировки
 * - dir: направление сортировки (asc/desc)
 */
class TelemetryDownloadValidator extends AbstractValidator
{
    public const ALLOWED_SORT_COLUMNS = [
        'id', 'recorded_at', 'voltage', 'temp', 'source_file'
    ];

    public const ALLOWED_SORT_DIRECTIONS = ['asc', 'desc'];

    protected array $defaults = [
        'limit' => 500,
        'sort' => 'recorded_at',
        'dir' => 'desc',
    ];

    protected function prepareData(): void
    {
        foreach ($this->defaults as $key => $default) {
            if (!isset($this->data[$key]) || $this->data[$key] === '') {
                $this->data[$key] = $default;
            }
        }

        if (isset($this->data['limit'])) {
            $this->data['limit'] = (int) $this->data['limit'];
        }
        if (isset($this->data['sort'])) {
            $this->data['sort'] = strtolower(trim((string) $this->data['sort']));
        }
        if (isset($this->data['dir'])) {
            $this->data['dir'] = strtolower(trim((string) $this->data['dir']));
        }
    }

    public function rules(): array
    {
        return [
            'limit' => ['required', 'integer', 'min:1', 'max:1000'],
            'sort' => ['required', 'string', 'in:' . implode(',', self::ALLOWED_SORT_COLUMNS)],
            'dir' => ['required', 'string', 'in:' . implode(',', self::ALLOWED_SORT_DIRECTIONS)],
        ];
    }

    public function messages(): array
    {
        return [
            'limit.required' => 'Лимит обязателен',
            'limit.integer' => 'Лимит должен быть целым числом',
            'limit.min' => 'Минимальный лимит: 1',
            'limit.max' => 'Максимальный лимит для скачивания: 1000',
            'sort.required' => 'Колонка сортировки обязательна',
            'sort.in' => 'Недопустимая колонка сортировки. Допустимые: ' . implode(', ', self::ALLOWED_SORT_COLUMNS),
            'dir.required' => 'Направление сортировки обязательно',
            'dir.in' => 'Направление сортировки должно быть asc или desc',
        ];
    }

    public function attributes(): array
    {
        return [
            'limit' => 'лимит',
            'sort' => 'колонка сортировки',
            'dir' => 'направление сортировки',
        ];
    }

    public function getLimit(): int
    {
        return (int) ($this->validated['limit'] ?? $this->defaults['limit']);
    }

    public function getSortColumn(): string
    {
        return (string) ($this->validated['sort'] ?? $this->defaults['sort']);
    }

    public function getSortDirection(): string
    {
        return (string) ($this->validated['dir'] ?? $this->defaults['dir']);
    }
}