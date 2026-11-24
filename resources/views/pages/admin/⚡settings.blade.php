<?php

use App\Services\StoreSettings;

use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Store settings')] class extends Component {
    public string $store_name = '';
    public string $contact_email = '';
    public string $order_copy_emails = '';

    public bool $tax_enabled = false;
    public string $tax_rate = '0';
    public string $tax_label = '';
    public bool $tax_on_shipping = false;

    public string $email_subject = '';
    public string $email_intro = '';
    public string $email_footer = '';

    public function mount(StoreSettings $settings): void
    {
        $values = $settings->all();

        $this->store_name = $values['store_name'];
        $this->contact_email = $values['contact_email'];
        $this->order_copy_emails = $values['order_copy_emails'];
        $this->tax_enabled = $values['tax_enabled'] === '1';
        $this->tax_rate = $values['tax_rate'];
        $this->tax_label = $values['tax_label'];
        $this->tax_on_shipping = $values['tax_on_shipping'] === '1';
        $this->email_subject = $values['email_subject'];
        $this->email_intro = $values['email_intro'];
        $this->email_footer = $values['email_footer'];
    }

    public function save(StoreSettings $settings): void
    {
        $this->order_copy_emails = implode(', ', StoreSettings::splitEmails($this->order_copy_emails));

        $validated = $this->validate([
            'store_name' => ['required', 'string', 'max:100'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'order_copy_emails' => ['nullable', 'string', 'max:1000', function (string $attribute, mixed $value, \Closure $fail): void {
                foreach (StoreSettings::splitEmails($value) as $email) {
                    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $fail(__('":email" is not a valid email address.', ['email' => $email]));
                    }
                }
            }],
            'tax_enabled' => ['boolean'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:30'],
            'tax_label' => ['required', 'string', 'max:50'],
            'tax_on_shipping' => ['boolean'],
            'email_subject' => ['required', 'string', 'max:200'],
            'email_intro' => ['required', 'string', 'max:2000'],
            'email_footer' => ['nullable', 'string', 'max:1000'],
        ]);

        $settings->save($validated);

        Flux::toast(text: __('Store settings saved.'), variant: 'success');
    }
}; ?>

<section class="w-full max-w-3xl">
    <flux:heading size="xl" level="1">{{ __('Store settings') }}</flux:heading>
    <flux:subheading>{{ __('Store details, tax and the order confirmation email.') }}</flux:subheading>

    <form wire:submit="save" class="mt-8 space-y-10">
        <div class="space-y-4">
            <flux:heading>{{ __('Store') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="store_name" :label="__('Store name')" required />
                <flux:input wire:model="contact_email" type="email" :label="__('Customer contact email')" :description="__('Shown as the reply-to on order emails.')" />
            </div>
        </div>

        <div class="space-y-4">
            <flux:heading>{{ __('Order notifications') }}</flux:heading>
            <flux:input
                wire:model="order_copy_emails"
                :label="__('Send a copy of every order to')"
                :description="__('The customer gets the confirmation and these addresses are copied on it. Separate several with commas.')"
                placeholder="orders@yourcompany.com, owner@yourcompany.com"
            />
        </div>

        <div class="space-y-4">
            <flux:heading>{{ __('Tax') }}</flux:heading>
            <flux:switch wire:model.live="tax_enabled" :label="__('Charge tax at checkout')" />

            @if ($tax_enabled)
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="tax_rate" type="number" step="0.001" min="0" max="30" :label="__('Tax rate (%)')" :description="__('For example 8.25')" />
                    <flux:input wire:model="tax_label" :label="__('Label shown to customers')" />
                </div>
                <flux:switch wire:model="tax_on_shipping" :label="__('Also charge tax on shipping')" />
                <flux:text size="sm">{{ __('Check with your accountant which rate and rules apply to your sales. The rate is applied to every order.') }}</flux:text>
            @endif
        </div>

        <div class="space-y-4">
            <flux:heading>{{ __('Order confirmation email') }}</flux:heading>
            <flux:text size="sm">
                {{ __('You can use these placeholders: {store_name}, {order_number} and {customer_name}. The order details table is added automatically.') }}
            </flux:text>
            <flux:input wire:model="email_subject" :label="__('Subject')" required />
            <flux:textarea wire:model="email_intro" :label="__('Message above the order')" rows="3" required />
            <flux:textarea wire:model="email_footer" :label="__('Message below the order')" rows="3" />
        </div>

        <flux:button type="submit" variant="primary" data-test="save-settings">{{ __('Save settings') }}</flux:button>
    </form>
</section>
