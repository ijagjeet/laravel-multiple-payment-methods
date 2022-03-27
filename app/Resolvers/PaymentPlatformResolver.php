<?php

namespace App\Resolvers;

use App\PaymentPlatform;

class PaymentPlatformResolver
{
    protected $paymentPlatforms;

    public function __construct()
    {
        $this->paymentPlatforms = PaymentPlatform::all();//the full list of payment platforms that our system supports
    }

    public function resolveService($paymentPlatformId)
    {
        $name = strtolower($this->paymentPlatforms->firstWhere('id', $paymentPlatformId)->name);

        //using the retrieved name to retrieve a configuration file
        $service = config("services.{$name}.class");

        if ($service) {
            return resolve($service);//retrieve the appropriate class
        }

        throw new \Exception('The selected platform is not in the configuration');

    }
}
