<?php

use App\Models\Inquiry;
use App\Services\ResendMailer;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Request volume pricing')] #[Layout('layouts::shop')] class extends Component {
    public string $company = '';
    public string $contact_name = '';
    public string $email = '';
    public string $label_type = '';
    public string $monthly_volume = '';
    public string $delivery_zip = '';
    public string $packaging_needs = '';

    public bool $submitted = false;

    public function submit(ResendMailer $mailer): void
    {
        $validated = $this->validate([
            'company' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'label_type' => ['required', 'string', 'max:255'],
            'monthly_volume' => ['required', 'string', 'max:255'],
            'delivery_zip' => ['required', 'regex:/^\d{5}(-\d{4})?$/'],
            'packaging_needs' => ['nullable', 'string', 'max:2000'],
        ], [
            'delivery_zip.regex' => __('Enter a 5-digit ZIP code.'),
        ]);

        $throttleKey = 'volume-inquiry:'.request()->ip();

        if (! RateLimiter::attempt($throttleKey, 5, fn () => true, 3600)) {
            $this->addError('company', __('Too many requests. Please try again later or contact us directly.'));

            return;
        }

        $inquiry = Inquiry::create([...$validated, 'packaging_needs' => $validated['packaging_needs'] ?: null]);

        if ($mailer->isConfigured()) {
            try {
                $mailer->sendInquiry($inquiry);
                $inquiry->update(['notified_at' => now()]);
            } catch (Throwable $e) {
                report($e);
            }
        } else {
            logger()->warning("Volume pricing request {$inquiry->reference()} saved but not emailed: Resend is not configured.");
        }

        $this->submitted = true;
    }
}; ?>

<div class="mx-auto max-w-3xl px-6 py-12 lg:px-8">
    <div class="rounded-[2.5rem] bg-slate-100 px-8 py-10 shadow-lg dark:bg-zinc-800 sm:px-14 sm:py-12">
        @if ($submitted)
            <div class="py-8 text-center" data-test="inquiry-confirmation">
                <flux:icon.check-circle class="mx-auto size-14 text-[#00868b]" />
                <h1 class="mt-4 text-3xl font-bold text-[#112336] dark:text-white">{{ __('Thanks, we got your request!') }}</h1>
                <p class="mt-3 text-lg text-slate-600 dark:text-zinc-300">
                    {{ __('Our commercial team will contact you within one business day.') }}
                </p>
                <a href="{{ route('home') }}" wire:navigate class="mt-8 inline-flex items-center justify-center rounded-md bg-[#00868b] px-8 py-3.5 text-sm font-bold uppercase tracking-wide text-white shadow-md transition hover:bg-[#007479]">
                    {{ __('Back to the catalog') }}
                </a>
            </div>
        @else
            <h1 class="text-3xl font-bold text-[#112336] dark:text-white sm:text-4xl">{{ __('Need more than 10 cases?') }}</h1>
            <p class="mt-5 text-lg text-slate-600 dark:text-zinc-300">
                {{ __('Tell us about your operation and we’ll build a business-specific quote.') }}
            </p>

            <form wire:submit="submit" class="mt-8 space-y-4">
                <flux:input wire:model="company" :label="__('Company')" placeholder="{{ __('Company name') }}" required autocomplete="organization" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="contact_name" :label="__('Contact name')" placeholder="{{ __('Your name') }}" required autocomplete="name" />
                    <flux:input wire:model="email" type="email" :label="__('Email')" placeholder="{{ __('you@company.com') }}" required autocomplete="email" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="label_type" :label="__('Label type')" placeholder="{{ __('e.g. 4 x 6 direct thermal') }}" required />
                    <flux:input wire:model="monthly_volume" :label="__('Monthly volume')" placeholder="{{ __('e.g. 25 cases') }}" required />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="delivery_zip" :label="__('Delivery ZIP code')" placeholder="{{ __('e.g. 76180') }}" required inputmode="numeric" autocomplete="postal-code" />
                    <flux:input wire:model="packaging_needs" :label="__('Other packaging needs')" placeholder="{{ __('Boxes, cartons, custom labels…') }}" />
                </div>

                <button type="submit" class="mt-4 inline-flex min-w-64 items-center justify-center rounded-md bg-[#00868b] px-8 py-4 text-sm font-bold uppercase tracking-wide text-white shadow-md transition hover:bg-[#007479]" data-test="submit-inquiry">
                    <span wire:loading.remove wire:target="submit">{{ __('Request custom pricing') }}</span>
                    <span wire:loading wire:target="submit">{{ __('Sending…') }}</span>
                </button>
            </form>
        @endif
    </div>
</div>
