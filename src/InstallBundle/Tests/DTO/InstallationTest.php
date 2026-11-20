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

namespace SolidInvoice\InstallBundle\Tests\DTO;

use PHPUnit\Framework\TestCase;
use SolidInvoice\CoreBundle\Branding\Brand;
use SolidInvoice\InstallBundle\DTO\Installation;

final class InstallationTest extends TestCase
{
    public function testUsageStatisticsAreOfferedUntickedUnderAnotherBrand(): void
    {
        self::assertTrue(Installation::withDefaults(new Brand())->telemetryEnabled);
        self::assertFalse(Installation::withDefaults(new Brand('Palmora Property'))->telemetryEnabled);
    }
}
