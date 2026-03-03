<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Plan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->user_type === 'Admin', 403);

        $firm = $request->user()->firm;
        $plan = Plan::where('slug', 'standard')->where('is_active', true)->first();
        $seatCount = Contact::where('firm_id', $firm->id)->firmMembers()->active()->count();
        $isSubscribed = $firm->subscribed('default');

        $subscription = null;
        if ($isSubscribed) {
            $sub = $firm->subscription('default');
            $subscription = [
                'status' => $sub->stripe_status,
                'quantity' => $sub->quantity,
                'ends_at' => $sub->ends_at?->toDateString(),
                'on_grace_period' => $sub->onGracePeriod(),
            ];
        }

        return Inertia::render('Subscription/Index', [
            'plan' => $plan,
            'seatCount' => $seatCount,
            'isSubscribed' => $isSubscribed,
            'subscription' => $subscription,
        ]);
    }

    public function checkout(Request $request)
    {
        abort_unless($request->user()->user_type === 'Admin', 403);

        $request->validate([
            'interval' => 'required|in:monthly,quarterly,yearly',
        ]);

        $firm = $request->user()->firm;
        $plan = Plan::where('slug', 'standard')->where('is_active', true)->firstOrFail();
        $priceId = $plan->{'stripe_price_'.$request->interval};

        if (! $priceId) {
            return back()->withErrors(['interval' => 'This billing interval is not available.']);
        }

        $seatCount = Contact::where('firm_id', $firm->id)->firmMembers()->active()->count();

        $checkout = $firm->newSubscription('default', $priceId)
            ->quantity($seatCount)
            ->checkout([
                'success_url' => route('subscription.success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('subscription.index'),
            ]);

        return Inertia::location($checkout->url);
    }

    public function success(Request $request)
    {
        return redirect()->route('subscription.index')->with('message', 'Subscription activated successfully! It may take a moment for your status to update.');
    }

    public function billingPortal(Request $request)
    {
        abort_unless($request->user()->user_type === 'Admin', 403);

        $firm = $request->user()->firm;

        return Inertia::location($firm->billingPortalUrl(route('subscription.index')));
    }
}
