<?php

namespace App\Validation;

/**
 * Валидатор для slug CMS страниц.
 *
 * Валидирует параметры:
 * - slug: идентификатор страницы (формат, длина, безопасность)
 */
class CmsSlugValidator extends AbstractValidator
{
    public const RESERVED_SLUGS = [
        'admin', 'api', 'login', 'logout', 'register',
        'dashboard', 'settings', 'profile', 'upload',
        'static', 'assets', 'public', 'storage',
    ];

    public const MIN_LENGTH = 1;
    public const MAX_LENGTH = 100;

    protected string $slug = '';

    public static function fromSlug(string $slug): static
    {
        $instance = new static();
        $instance->slug = $slug;
        $instance->data = ['slug' => $slug];
        return $instance;
    }

    protected function prepareData(): void
    {
        if (isset($this->data['slug'])) {
            $this->data['slug'] = strtolower(trim((string) $this->data['slug']));
            $this->data['slug'] = trim($this->data['slug'], '/');
        }
    }

    public function rules(): array
    {
        return [
            'slug' => [
                'required',
                'string',
                'min:' . self::MIN_LENGTH,
                'max:' . self::MAX_LENGTH,
                'regex:/^[a-z0-9_\-]+$/',
                'not_in:' . implode(',', self::RESERVED_SLUGS),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.required' => 'Идентификатор страницы обязателен',
            'slug.string' => 'Идентификатор страницы должен быть строкой',
            'slug.min' => 'Минимальная длина идентификатора: ' . self::MIN_LENGTH . ' символ',
            'slug.max' => 'Максимальная длина идентификатора: ' . self::MAX_LENGTH . ' символов',
            'slug.regex' => 'Идентификатор может содержать только латинские буквы, цифры, дефисы и подчеркивания',
            'slug.not_in' => 'Этот идентификатор зарезервирован системой',
        ];
    }

    public function attributes(): array
    {
        return [
            'slug' => 'идентификатор страницы',
        ];
    }

    public function validate(): bool
    {
        if (!parent::validate()) {
            return false;
        }

        $slug = $this->validated['slug'] ?? '';

        if ($this->containsPathTraversal($slug)) {
            $this->errors['slug'] = ['Обнаружена попытка обхода директорий'];
            $this->passed = false;
            return false;
        }

        if (strpos($slug, "\0") !== false) {
            $this->errors['slug'] = ['Недопустимые символы в идентификаторе'];
            $this->passed = false;
            return false;
        }

        if ($this->containsSqlInjection($slug)) {
            $this->errors['slug'] = ['Обнаружены недопустимые символы'];
            $this->passed = false;
            return false;
        }

        return true;
    }

    protected function containsPathTraversal(string $value): bool
    {
        $patterns = ['..', './', '/.', '%2e', '%2f', '%5c'];
        foreach ($patterns as $pattern) {
            if (stripos($value, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    protected function containsSqlInjection(string $value): bool
    {
        $patterns = [
            "'", '"', ';', '--', '/*', '*/',
            'union', 'select', 'insert', 'update', 'delete', 'drop',
            'exec', 'execute', 'xp_', 'sp_'
        ];
        $lower = strtolower($value);
        foreach ($patterns as $pattern) {
            if (strpos($lower, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    public function getSlug(): string
    {
        return (string) ($this->validated['slug'] ?? '');
    }

    public function isReserved(): bool
    {
        return in_array($this->getSlug(), self::RESERVED_SLUGS, true);
    }
}