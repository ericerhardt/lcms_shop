<?php

use App\Models\Inquiry;
use App\Services\ResendMailer;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Volume requests')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $delivery = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDelivery(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Inquiry>
     */
    #[Computed]
    public function inquiries(): LengthAwarePaginator
    {
        $term = '%'.Str::lower(trim($this->search)).'%';

        return Inquiry::query()
            ->when(trim($this->search) !== '', fn ($query) => $query->where(function ($query) use ($term) {
                $query->whereRaw('lower(company) like ?', [$term])
                    ->orWhereRaw('lower(contact_name) like ?', [$term])
                    ->orWhereRaw('lower(email) like ?', [$term])
                    ->orWhereRaw('lower(label_type) like ?', [$term]);
            }))
            ->when($this->delivery === 'sent', fn ($query) => $query->whereNotNull('notified_at'))
            ->when($this->delivery === 'unsent', fn ($query) => $query->whereNull('notified_at'))
            ->latest()
            ->latest('id')
            ->paginate(15);
    }

    public function resend(int $inquiryId, ResendMailer $mailer): void
    {
        $inquiry = Inquiry::findOrFail($inquiryId);

        if (! $mailer->isConfigured()) {
            Flux::toast(text: __('Email is not configured on this server.'), variant: 'danger');

            return;
        }

        try {
            $mailer->sendInquiry($inquiry);
        } catch (Throwable $e) {
            report($e);
            Flux::toast(text: __('The email could not be sent. Check the logs.'), variant: 'danger');

            return;
        }

        $inquiry->update(['notified_at' => now()]);

        unset($this->inquiries);
        Flux::toast(text: __('Email sent.'), variant: 'success');
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">{{ __('Volume requests') }}</flux:heading>
            <flux:subheading>{{ __('Submissions from the Request Volume Pricing form.') }}</flux:subheading>
        </div>
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search company, contact, email or label')" clearable class="max-w-sm" />
        <flux:select wire:model.live="delivery" class="w-48">
            <flux:select.option value="">{{ __('All requests') }}</flux:select.option>
            <flux:select.option value="sent">{{ __('Emailed') }}</flux:select.option>
            <flux:select.option value="unsent">{{ __('Not emailed') }}</flux:select.option>
        </flux:select>
    </div>

    <div class="mt-4 space-y-3">
        @forelse ($this->inquiries as $inquiry)
            <details wire:key="inquiry-{{ $inquiry->id }}" class="group rounded-xl border border-zinc-200 dark:border-zinc-700">
                <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-3 p-4">
                    <div>
                        <span class="font-semibold">{{ $inquiry->company }}</span>
                        <span class="ms-2 text-sm text-zinc-500">{{ $inquiry->reference() }} &middot; {{ $inquiry->created_at->format('M j, Y g:i A') }}</span>
                        <div class="text-sm text-zinc-500">{{ $inquiry->contact_name }} &middot; {{ $inquiry->email }}</div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm">{{ $inquiry->label_type }}</span>
                        <flux:badge size="sm" :color="$inquiry->notified_at ? 'green' : 'amber'">{{ $inquiry->notified_at ? __('Emailed') : __('Not emailed') }}</flux:badge>
                    </div>
                </summary>
                <div class="border-t border-zinc-200 p-4 text-sm dark:border-zinc-700">
                    <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-zinc-500">{{ __('Contact') }}</dt>
                            <dd>{{ $inquiry->contact_name }}, <a href="mailto:{{ $inquiry->email }}" class="underline">{{ $inquiry->email }}</a></dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500">{{ __('Label type') }}</dt>
                            <dd>{{ $inquiry->label_type }}</dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500">{{ __('Monthly volume') }}</dt>
                            <dd>{{ $inquiry->monthly_volume }}</dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500">{{ __('Delivery ZIP code') }}</dt>
                            <dd>{{ $inquiry->delivery_zip }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-zinc-500">{{ __('Other packaging needs') }}</dt>
                            <dd class="whitespace-pre-line">{{ $inquiry->packaging_needs ?: '—' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                        <flux:text size="sm">
                            {{ $inquiry->notified_at ? __('Emailed to the sales team :when.', ['when' => $inquiry->notified_at->format('M j, Y g:i A')]) : __('The notification email was not sent.') }}
                        </flux:text>
                        @unless ($inquiry->notified_at)
                            <flux:button wire:click="resend({{ $inquiry->id }})" size="sm" icon="paper-airplane" data-test="resend-inquiry">{{ __('Send email now') }}</flux:button>
                        @endunless
                    </div>
                </div>
            </details>
        @empty
            <flux:text class="py-8 text-center">{{ __('No volume requests found.') }}</flux:text>
        @endforelse
    </div>

    <div class="mt-6">
        <flux:pagination :paginator="$this->inquiries" />
    </div>
</section>
