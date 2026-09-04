<?php

namespace Database\Seeders\Demo;

/**
 * The knobs the demo fixtures are built from, in one place.
 *
 * EMAIL_DOMAIN is the important one: every account the demo invents lives on
 * it, which is what lets DemoSeeder wipe and rebuild its own data without
 * ever touching a real customer. Change it and re-running the seeder will
 * leave the previous batch behind, so change it only if you mean to.
 */
final class DemoConfig
{
    /** Every generated account is <name>@demo.optics.test. */
    public const EMAIL_DOMAIN = 'demo.optics.test';

    /** One password for every demo account, so a presenter can sign in as anyone. */
    public const PASSWORD = 'password';

    /** Fixed RNG seed: the same command always produces the same shop. */
    public const SEED = 20260904;

    /** How far back the order/view history reaches, in days. */
    public const HISTORY_DAYS = 150;

    public const CUSTOMERS = 48;

    public const STAFF = 2;

    public const DELIVERY = 3;

    public const ORDERS = 140;

    public const REVIEWS = 200;

    public const PRODUCT_VIEWS = 4000;

    public const RETURNS = 14;

    public const CONTACT_MESSAGES = 18;

    /** Carts left mid-shop, so the cart and checkout screens are never empty. */
    public const OPEN_CARTS = 8;

    /** Flat shipping charged at checkout — mirrors CheckoutController. */
    public const SHIPPING_COST = 5.00;

    /** Nothing in the shop is taxed today; kept explicit so totals are traceable. */
    public const TAX_RATE = 0.0;
}
