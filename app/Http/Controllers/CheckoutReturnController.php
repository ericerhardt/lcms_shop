<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Cart;
use App\Services\StripeCheckout;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class CheckoutReturnController extends Controller
{
    /**
     * Stripe redirects here after payment. The session is re-checked with Stripe before the order is marked paid.
     */
    public function success(Request $request, StripeCheckout $stripe, Cart $cart): View
    {
        $order = Order::query()
            ->with('items')
            ->where('token', (string) $request->query('order'))
            ->firstOrFail();

        $paymentFailed = false;

        if ($order->status === OrderStatus::Pending) {
            abort_unless(
                $order->stripe_session_id !== null && hash_equals($order->stripe_session_id, (string) $request->query('session_id')),
                404,
            );

            try {
                $session = $stripe->retrieveSession($order->stripe_session_id);

                if (($session['payment_status'] ?? null) === 'paid') {
                    $order->markPaid($session['payment_intent'] ?? null);
                } else {
                    $paymentFailed = true;
                }
            } catch (Throwable $e) {
                report($e);
                $paymentFailed = true;
            }
        }

        if ($order->status === OrderStatus::Paid) {
            $cart->clear();
        }

        return view('shop.order-complete', ['order' => $order, 'paymentFailed' => $paymentFailed]);
    }

    public function cancel(): RedirectResponse
    {
        return redirect()->route('cart')->with('status', 'Payment was cancelled. Your cart is still here whenever you are ready.');
    }
}
