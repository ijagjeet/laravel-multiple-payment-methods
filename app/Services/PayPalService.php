<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Traits\ConsumesExternalServices;

class PayPalService
{
    use ConsumesExternalServices;

    protected $baseUri;
    protected $clientId;
    protected $clientSecret;
    protected $plans;

    public function __construct()
    {
        $this->baseUri = config('services.paypal.base_uri');
        $this->clientId = config('services.paypal.client_id');
        $this->clientSecret = config('services.paypal.client_secret');
        $this->plans = config('services.paypal.plans');
    }

    //UTILITY FUNCTIONS
    public function resolveAccessToken()
    {
        $credentials = base64_encode("{$this->clientId}:{$this->clientSecret}");

        return "Basic {$credentials}";//returning a basic token
    }

    public function decodeResponse($response)
    {
        return json_decode($response);
    }

    public function resolveFactor($currency)
    {
        $zeroDecimalCurrencies = ['JPY'];

        if (in_array(strtoupper($currency), $zeroDecimalCurrencies)) {
            return 1;
        }

        return 100;
    }
    //END OF UTILITY FUNCTIONS

    /*
     * we need to pass them by reference.
        Basically, what we are doing with this is say: Any change that you may here inside the "resolveAuthorization" method for this, or this, or this is
        going to be automatically be reflected after this call.
    So, if we add a header in the "resolveAuthorization" of the PayPal service, that header is going to be reflected
    in the list of headers that we'll receive and it's going to be sent in the request.
     * */
    public function resolveAuthorization(&$queryParams, &$formParams, &$headers)
    {
        $headers['Authorization'] = $this->resolveAccessToken();
    }

    public function handlePayment(Request $request)
    {
        $order = $this->createOrder($request->value, $request->currency);

        $orderLinks = collect($order->links);

        $approve = $orderLinks->where('rel', 'approve')->first();

        session()->put('approvalId', $order->id);//temporally saving the order id

        return redirect($approve->href);
    }

    public function createOrder($value, $currency)
    {
        return $this->makeRequest(
            'POST',
            '/v2/checkout/orders',
            [],//query params
            [//body
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    0 => [//an array with only one element
                        'amount' => [
                            'currency_code' =>strtoupper($currency),
                            'value' => round($value * $factor = $this->resolveFactor($currency)) / $factor,
                        ]
                    ]
                ],
                'application_context' => [//this will json
                    'brand_name' => config('app.name'),
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'PAY_NOW',
                    'return_url' => route('approval'),
                    'cancel_url' => route('cancelled'),
                ]
            ],
            [],//headers
            $isJsonRequest = true,
        );
    }

    public function handleApproval()
    {
        if (session()->has('approvalId')) {
            $approvalId = session()->get('approvalId');

            $payment = $this->capturePayment($approvalId);

            $name = $payment->payer->name->given_name;
            $payment = $payment->purchase_units[0]->payments->captures[0]->amount;
            $amount = $payment->value;
            $currency = $payment->currency_code;

            return redirect()
                ->route('home')
                ->withSuccess(['payment' => "Thanks, {$name}. We received your {$amount}{$currency} payment."]);
        }

        return redirect()
            ->route('home')
            ->withErrors('We cannot capture the payment. Try again, please');
    }

    public function capturePayment($approvalId)
    {
        return $this->makeRequest(
            'POST',
            "/v2/checkout/orders/{$approvalId}/capture",
            [],//query
            [],//body
            [//header
                'Content-Type' => 'application/json'
            ],
        );
    }

    public function handleSubscription(Request $request)
    {
        $subscription = $this->createSubscription($request->plan, $request->user()->name,$request->user()->email,);

        $subscriptionLinks = collect($subscription->links);//links are returned in the response and then convert to collection

        $approve = $subscriptionLinks->where('rel', 'approve')->first();//obtain the approve link

        session()->put('subscriptionId', $subscription->id);

        return redirect($approve->href);//redirect the user to the link that was returned in the response
    }

    public function createSubscription($planSlug, $name, $email)
    {
        return $this->makeRequest(
            'POST',
            '/v1/billing/subscriptions',
            [],
            [
                'plan_id' => $this->plans[$planSlug],//or you can receive the plan id directly
                'subscriber' => [
                    'name' => [
                        'given_name' => $name,
                    ],
                    'email_address' => $email
                ],
                'application_context' => [//customizes the payer experience during the subscription approval process with PayPal
                    'brand_name' => config('app.name'),
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'SUBSCRIBE_NOW',
                    'return_url' => route('subscribe.approval', ['plan' => $planSlug]),
                    'cancel_url' => route('subscribe.cancelled'),
                ]
            ],
            [],
            $isJsonRequest = true,
        );
    }

    public function validateSubscription(Request $request)
    {
        if (session()->has('subscriptionId')) {
            $subscriptionId = session()->get('subscriptionId');

            session()->forget('subscriptionId');//ensure that it can not be used later

            return $request->subscription_id == $subscriptionId;
        }

        return false;
    }

}
