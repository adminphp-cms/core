<?php

namespace Gmarineau\AdminPhp\Settings;

use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reads and writes the stored settings. Values are loaded once per request.
 */
class SettingsStore
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $values = null;

    public function __construct(protected AdminPhp $adminPhp) {}

    /**
     * Every stored value by key.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->values === null) {
            $this->values = Schema::hasTable((new Setting)->getTable())
                ? Setting::query()->pluck('value', 'key')->all()
                : [];
        }

        return $this->values;
    }

    /**
     * The value of a setting; a translatable one is read in the given locale (the app
     * locale by default), falling back to the admin's default locale.
     */
    public function get(string $key, mixed $default = null, ?string $locale = null): mixed
    {
        $value = $this->all()[$key] ?? null;
        $field = $this->adminPhp->settingsForm()?->fields()[$key] ?? null;

        if ($field?->isTranslatable() && is_array($value)) {
            $locale ??= app()->getLocale();
            $value = filled($value[$locale] ?? null) ? $value[$locale] : ($value[$this->adminPhp->defaultLocale()] ?? null);
        }

        return $value ?? $default;
    }

    /**
     * Validates the submitted values against the settings form and stores them.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed> The form values after saving.
     */
    public function save(Settings $settings, array $input): array
    {
        $validated = $settings->validate($input, onlyPresentFields: true);
        $fields = $settings->fields();

        DB::transaction(function () use ($validated, $fields): void {
            foreach ($validated as $key => $value) {
                Setting::query()->updateOrCreate(['key' => $key], ['value' => $fields[$key]->dehydrate($value)]);
            }
        });

        $this->values = null;

        return $this->formValues($settings);
    }

    /**
     * The value of every field of the form, as the admin edits it.
     *
     * @return array<string, mixed>
     */
    public function formValues(Settings $settings): array
    {
        $stored = $this->all();
        $values = [];

        foreach ($settings->fields() as $key => $field) {
            $values[$key] = $stored[$key] ?? ($field->isTranslatable() ? [] : null);
        }

        return $values;
    }
}
