<?php

namespace App\Services\Contacts;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Reads a contacts CSV and adds each row as a contact reached by phone (WhatsApp), like Create New
 * Contact. Rows that fail are reported with their line number and do not stop the others; the plan's
 * contacts limit stops the import where it is reached.
 */
class ContactImporter
{
    public const MAX_ROWS = 1000;

    /** Header names each column is read from, in English or Arabic. */
    private const HEADERS = [
        'name' => ['name', 'full name', 'الاسم'],
        'first_name' => ['first name', 'first_name', 'الاسم الأول'],
        'last_name' => ['last name', 'last_name', 'اسم العائلة'],
        'phone' => ['phone', 'phone number', 'mobile', 'whatsapp', 'الجوال', 'الهاتف', 'رقم الجوال'],
        'email' => ['email', 'e-mail', 'البريد', 'البريد الإلكتروني'],
        'city' => ['city', 'المدينة'],
        'gender' => ['gender', 'الجنس'],
        'tags' => ['tags', 'labels', 'الوسوم'],
    ];

    /**
     * @return array{imported: int, skipped: list<array{row: int, reason: string}>, missing_columns: list<string>}
     */
    public function import(User $user, string $path): array
    {
        $rows = $this->rows($path);
        $columns = $this->columns(array_shift($rows) ?? []);

        $missing = array_values(array_filter([
            isset($columns['name']) || isset($columns['first_name']) ? null : 'name',
            isset($columns['phone']) ? null : 'phone',
        ]));

        if ($missing !== []) {
            return ['imported' => 0, 'skipped' => [], 'missing_columns' => $missing];
        }

        $usage = $user->contactsUsage();
        $room = max(0, $usage['limit'] - $usage['used']);
        $known = $user->contacts()->whereNotNull('phone')->pluck('phone')->map($this->digits(...))->flip();
        $imported = 0;
        $skipped = [];

        foreach (array_slice($rows, 0, self::MAX_ROWS) as $index => $cells) {
            $line = $index + 2;
            $values = collect($columns)->map(fn (int $column): string => trim((string) ($cells[$column] ?? '')))->all();

            if (implode('', $values) === '') {
                continue;
            }

            $attributes = $this->attributes($values);
            $reason = $this->problem($attributes);

            if ($reason === null && $known->has($this->digits($attributes['phone']))) {
                $reason = __('contacts.import.duplicate');
            }

            if ($reason === null && $imported >= $room) {
                $reason = __('contacts.import.limit');
            }

            if ($reason !== null) {
                $skipped[] = ['row' => $line, 'reason' => $reason];

                continue;
            }

            $user->contacts()->create([...$attributes, 'platform' => 'whatsapp', 'handle' => $attributes['phone'], 'added_manually' => true, 'consented_at' => now(), 'first_seen_at' => now()]);
            $known->put($this->digits($attributes['phone']), true);
            $imported++;
        }

        if (count($rows) > self::MAX_ROWS) {
            $skipped[] = ['row' => self::MAX_ROWS + 2, 'reason' => __('contacts.import.too_many', ['max' => self::MAX_ROWS])];
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'missing_columns' => []];
    }

    /**
     * @return list<list<string|null>>
     */
    private function rows(string $path): array
    {
        $handle = fopen($path, 'r');
        $first = (string) fgets($handle);
        rewind($handle);
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $rows = [];

        while (($cells = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $rows[] = $cells;
        }

        fclose($handle);

        if (isset($rows[0][0])) {
            $rows[0][0] = Str::after($rows[0][0], "\u{FEFF}");
        }

        return $rows;
    }

    /**
     * @param  list<string|null>  $header
     * @return array<string, int> field => column index
     */
    private function columns(array $header): array
    {
        $columns = [];

        foreach ($header as $index => $title) {
            $title = mb_strtolower(trim((string) $title));

            foreach (self::HEADERS as $field => $names) {
                if (! isset($columns[$field]) && in_array($title, $names, true)) {
                    $columns[$field] = $index;
                }
            }
        }

        return $columns;
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, mixed>
     */
    private function attributes(array $values): array
    {
        $name = $values['name'] ?? trim(($values['first_name'] ?? '').' '.($values['last_name'] ?? ''));
        $gender = mb_strtolower($values['gender'] ?? '');
        $tags = collect(preg_split('/[;|,،]/u', $values['tags'] ?? ''))->map(fn (string $tag): string => trim($tag))->filter()->unique()->values();

        return [
            'name' => $name,
            'phone' => $values['phone'],
            'email' => ($values['email'] ?? '') !== '' ? $values['email'] : null,
            'city' => ($values['city'] ?? '') !== '' ? $values['city'] : null,
            'gender' => match ($gender) {
                'female', 'f', 'أنثى', 'انثى' => 'female',
                'male', 'm', 'ذكر' => 'male',
                default => $gender === '' ? null : $gender,
            },
            'tags' => $tags->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function problem(array $attributes): ?string
    {
        $validator = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:200'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[\d\s\-()]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', 'in:'.implode(',', Contact::GENDERS)],
            'tags' => ['array', 'max:30'],
            'tags.*' => ['string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return $validator->errors()->first();
        }

        return strlen($this->digits($attributes['phone'])) < 7 ? __('contacts.import.phone_short') : null;
    }

    private function digits(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }
}
