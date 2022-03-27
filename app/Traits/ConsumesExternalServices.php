<?php

namespace App\Traits;

use GuzzleHttp\Client;

/*
 * And this time, we need to add the possibility to send any HTTP request to any service.
We need to consume the APIs from our payment platforms, PayPal, Stripe or any other that you want to add.
And for that, we can use a very important or interesting library that is called "GuzzleHTTP".
Guzzle is a PHP library, of course, that allow us to use several components and classes, to send
easily HTTP request with different methods, different URLs, different components or bodies
 * */
trait ConsumesExternalServices
{
    // to make a request,we need a lot of different options that we can use to customize these as much as possible
    public function makeRequest($method, $requestUrl, $queryParams = [], $formParams = [], $headers = [], $isJsonRequest = false)
    {
        $client = new Client([
            'base_uri' => $this->baseUri,
        ]);

        if (method_exists($this, 'resolveAuthorization')) {
            $this->resolveAuthorization($queryParams, $formParams, $headers);
        }

        $response = $client->request($method, $requestUrl, [
            $isJsonRequest ? 'json' : 'form_params' => $formParams,
            'headers' => $headers,
            'query' => $queryParams,
        ]);

        $response = $response->getBody()->getContents();

        //Every single service needs to know how to decode the response, if it is XML, if it is JSON,
        //if it is something different.
        if (method_exists($this, 'decodeResponse')) {
            $response = $this->decodeResponse($response);
        }

        return $response;
    }
}
