<?php

namespace App\Provider;

use Stripe\Error\Base;
use Stripe\Error\Card;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class Stripe
{
    public static function charge($token, $amount, $description)
    {
        \Stripe\Stripe::setApiKey(SystemSettings::getInstance()->get('private_stripe_key'));

        try {
            \Stripe\Charge::create(array(
                "amount" => floatval($amount) * 100, // getting dollars
                "currency" => "usd",
                "source" => $token,
                "description" => $description
            ));
        } catch(Card $e) {
            return $e->getJsonBody()['error']['message'];
        } catch (Base $e) {
            return $e->getJsonBody()['error']['message'];
        }

        return true;
    }
}