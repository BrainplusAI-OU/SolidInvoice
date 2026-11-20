<?php

declare(strict_types=1);

/*
 * This file is part of SolidInvoice project.
 *
 * (c) Pierre du Plessis <open-source@solidworx.co>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace SolidInvoice\PaymentBundle\Tests\Payum;

use Payum\Core\CoreGatewayFactory;
use Payum\Core\Gateway;
use Payum\Offline\OfflineGatewayFactory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Payum builds every gateway, offline ones included, with a php-http message factory
 * found by discovery. Without one, marking an invoice as paid ended in a 500
 * ("No php-http message factories found") on /payment/capture.
 */
#[CoversNothing]
final class CoreGatewayFactoryTest extends TestCase
{
    public function testAnOfflineGatewayCanBeBuilt(): void
    {
        $factory = new OfflineGatewayFactory([], new CoreGatewayFactory());

        self::assertInstanceOf(Gateway::class, $factory->create());
    }
}
