<x-mail::message>
# {{ __('Order :number', ['number' => $order->number()]) }}

{{ $intro }}

<x-mail::table>
| {{ __('Item') }} | {{ __('Qty') }} | {{ __('Price') }} |
| :-- | :-: | --: |
@foreach ($order->items as $item)
| {{ $item->name }} ({{ $item->sku }}) | {{ $item->quantity }} | {{ $item->formattedLineTotal() }} |
@endforeach
</x-mail::table>

<x-mail::table>
| | |
| :-- | --: |
| {{ __('Subtotal') }} | {{ $order->formattedSubtotal() }} |
@if ($order->shipping_carrier)
| {{ __('Shipping') }} ({{ $order->shipping_carrier }}, {{ $order->shipping_timeframe }}) | {{ $order->formattedShipping() }} |
@endif
@if ($order->tax_cents > 0)
| {{ $order->tax_label }} ({{ rtrim(rtrim(number_format((float) $order->tax_rate, 3), '0'), '.') }}%) | {{ $order->formattedTax() }} |
@endif
| **{{ __('Total') }}** | **{{ $order->formattedTotal() }}** |
</x-mail::table>

**{{ __('Shipping to') }}**<br>
{{ $order->name }}<br>
@if ($order->company){{ $order->company }}<br>@endif
{{ $order->address_line1 }}<br>
@if ($order->address_line2){{ $order->address_line2 }}<br>@endif
{{ $order->city }}, {{ $order->state }} {{ $order->postal_code }} {{ $order->country }}

{{ $footer }}

{{ __('Thanks,') }}<br>
{{ $storeName }}
</x-mail::message>
