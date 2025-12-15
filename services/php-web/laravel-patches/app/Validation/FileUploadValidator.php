<?php

namespace App\Validation;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Валидатор для загрузки файлов.
 *
 * Валидирует параметры:
 * - file: загружаемый файл (расширения, размер, MIME-типы)
 */
class FileUploadValidator extends AbstractValidator
{
    public const ALLOWED_EXTENSIONS = [
        'txt', 'csv', 'json', 'xml',
        'pdf', 'doc', 'docx', 'xls', 'xlsx',
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'zip', 'tar', 'gz'
    ];

    public const ALLOWED_MIMES = [
        'text/plain',
        'text/csv',
        'application/json',
        'application/xml',
        'text/xml',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/zip',
        'application/x-tar',
        'application/gzip',
    ];

    public const DANGEROUS_EXTENSIONS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'phar',
        'exe', 'bat', 'cmd', 'sh', 'bash', 'ps1',
        'js', 'vbs', 'wsf', 'wsh',
        'htaccess', 'htpasswd',
        'asp', 'aspx', 'jsp', 'cgi', 'pl', 'py', 'rb',
    ];

    public const MAX_FILE_SIZE = 10 * 1024 * 1024;

    protected ?UploadedFile $file = null;

    public static function fromRequest(Request $request): static
    {
        $instance = new static();
        $instance->file = $request->file('file');
        $instance->data = [
            'file' => $instance->file,
        ];
        return $instance;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:' . (self::MAX_FILE_SIZE / 1024),
                'mimes:' . implode(',', self::ALLOWED_EXTENSIONS),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Файл обязателен для загрузки',
            'file.file' => 'Загруженные данные должны быть файлом',
            'file.max' => 'Максимальный размер файла: ' . $this->formatBytes(self::MAX_FILE_SIZE),
            'file.mimes' => 'Недопустимый тип файла. Допустимые: ' . implode(', ', self::ALLOWED_EXTENSIONS),
        ];
    }

    public function attributes(): array
    {
        return [
            'file' => 'файл',
        ];
    }

    public function validate(): bool
    {
        if (!parent::validate()) {
            return false;
        }

        if ($this->file) {
            $extension = strtolower($this->file->getClientOriginalExtension());
            if (in_array($extension, self::DANGEROUS_EXTENSIONS, true)) {
                $this->errors['file'] = ['Загрузка файлов с расширением .' . $extension . ' запрещена'];
                $this->passed = false;
                return false;
            }

            $originalName = $this->file->getClientOriginalName();
            if ($this->hasDangerousDoubleExtension($originalName)) {
                $this->errors['file'] = ['Обнаружено подозрительное имя файла'];
                $this->passed = false;
                return false;
            }

            $mimeType = $this->file->getMimeType();
            if ($mimeType && !in_array($mimeType, self::ALLOWED_MIMES, true)) {
                $this->errors['file'] = ['Недопустимый MIME-тип файла: ' . $mimeType];
                $this->passed = false;
                return false;
            }

            if (strpos($originalName, "\0") !== false) {
                $this->errors['file'] = ['Недопустимые символы в имени файла'];
                $this->passed = false;
                return false;
            }
        }

        return true;
    }

    protected function hasDangerousDoubleExtension(string $filename): bool
    {
        $parts = explode('.', strtolower($filename));
        if (count($parts) < 3) {
            return false;
        }

        array_pop($parts);
        array_shift($parts);

        foreach ($parts as $part) {
            if (in_array($part, self::DANGEROUS_EXTENSIONS, true)) {
                return true;
            }
        }

        return false;
    }

    public function getSafeFilename(): string
    {
        if (!$this->file) {
            return '';
        }

        $extension = strtolower($this->file->getClientOriginalExtension());
        $basename = pathinfo($this->file->getClientOriginalName(), PATHINFO_FILENAME);
        
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $basename);
        $safeName = preg_replace('/_+/', '_', $safeName);
        $safeName = trim($safeName, '_');
        
        if (empty($safeName)) {
            $safeName = 'file_' . time();
        }

        return $safeName . '.' . $extension;
    }

    public function getUniqueFilename(): string
    {
        if (!$this->file) {
            return '';
        }

        $extension = strtolower($this->file->getClientOriginalExtension());
        return uniqid('upload_', true) . '.' . $extension;
    }

    public function getFile(): ?UploadedFile
    {
        return $this->file;
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}