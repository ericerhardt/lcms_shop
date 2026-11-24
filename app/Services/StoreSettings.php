<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Store-wide options that admins edit in the Store settings page.
 */
class StoreSettings
{
    /**
     * Every supported option with its default value.
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'store_name' => (string) config('app.name'),
            'contact_email' => '',
            'order_copy_emails' => '',
            'tax_enabled' => '0',
            'tax_rate' => '0',
            'tax_label' => 'Sales tax',
            'tax_on_shipping' => '0',
            'email_subject' => 'Your {store_name} order {order_number}',
            'email_intro' => 'Thank you for your order, {customer_name}. We have received your payment and are getting your order ready.',
            'email_footer' => 'Questions about your order? Just reply to this email.',
        ];
    }

    /**
     * @var array<string, string>|null
     */
    private ?array $loaded = null;

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        $this->loaded ??= [
            ...static::defaults(),
            ...Setting::query()->pluck('value', 'key')->map(fn ($value) => (string) $value)->all(),
        ];

        return array_intersect_key($this->loaded, static::defaults());
    }

    public function get(string $key): string
    {
        return $this->all()[$key] ?? '';
    }

    /**
     * @param  array<string, string|int|float|bool|null>  $values
     */
    public function save(array $values): void
    {
        foreach (array_intersect_key($values, static::defaults()) as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]);
        }

        $this->loaded = null;
    }

    public function storeName(): string
    {
        return $this->get('store_name') ?: (string) config('app.name');
    }

    public function contactEmail(): ?string
    {
        return filled($this->get('contact_email')) ? $this->get('contact_email') : null;
    }

    /**
     * Addresses that receive a copy of every order confirmation.
     *
     * @return array<int, string>
     */
    public function copyRecipients(): array
    {
        return static::splitEmails($this->get('order_copy_emails'));
    }

    public function taxEnabled(): bool
    {
        return $this->get('tax_enabled') === '1' && $this->taxRate() > 0;
    }

    public function taxRate(): float
    {
        return max(0.0, (float) $this->get('tax_rate'));
    }

    public function taxLabel(): string
    {
        return $this->get('tax_label') ?: 'Sales tax';
    }

    public function taxOnShipping(): bool
    {
        return $this->get('tax_on_shipping') === '1';
    }

    /**
     * Fill the {placeholders} admins can use in the email wording.
     *
     * @param  array<string, string>  $replacements
     */
    public function fill(string $text, array $replacements): string
    {
        $pairs = [];

        foreach ($replacements as $key => $value) {
            $pairs['{'.$key.'}'] = $value;
        }

        return strtr($text, $pairs);
    }

    /**
     * @return array<int, string>
     */
    public static function splitEmails(?string $list): array
    {
        return collect(preg_split('/[,;\s]+/', (string) $list))
            ->map(fn ($email) => Str::lower(trim($email)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
