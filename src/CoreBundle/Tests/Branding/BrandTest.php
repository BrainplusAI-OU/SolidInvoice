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

namespace SolidInvoice\CoreBundle\Tests\Branding;

use PHPUnit\Framework\TestCase;
use SolidInvoice\CoreBundle\Branding\Brand;
use SolidInvoice\CoreBundle\SolidInvoiceCoreBundle;

final class BrandTest extends TestCase
{
    public function testWithoutABrandNameTheInstallationIsSolidInvoice(): void
    {
        $brand = new Brand('  ');

        self::assertFalse($brand->isWhiteLabel());
        self::assertSame(SolidInvoiceCoreBundle::APP_NAME, $brand->getName());
        self::assertNull($brand->getLogo());
    }

    public function testABrandNameWhiteLabelsTheInstallation(): void
    {
        $brand = new Brand(' Palmora Billing ');

        self::assertTrue($brand->isWhiteLabel());
        self::assertSame('Palmora Billing', $brand->getName());
    }

    public function testTheLogoIsReadFromTheConfiguredFile(): void
    {
        $logo = sys_get_temp_dir() . '/brand-' . uniqid() . '.svg';
        file_put_contents($logo, '<svg/>');

        self::assertSame('svg+xml|' . base64_encode('<svg/>'), new Brand('Palmora', $logo)->getLogo());
    }

    public function testAMissingOrUnsupportedLogoIsIgnored(): void
    {
        $text = sys_get_temp_dir() . '/brand-' . uniqid() . '.txt';
        file_put_contents($text, 'hello');

        self::assertNull(new Brand('Palmora', '/does/not/exist.png')->getLogo());
        self::assertNull(new Brand('Palmora', $text)->getLogo());
    }

    public function testTheLogoIsShownAloneOnlyForAWhiteLabelWithALogo(): void
    {
        $logo = sys_get_temp_dir() . '/brand-' . uniqid() . '.svg';
        file_put_contents($logo, '<svg/>');

        self::assertTrue(new Brand('Palmora', $logo)->showsLogoOnly());
        self::assertFalse(new Brand('Palmora')->showsLogoOnly());
        self::assertFalse(new Brand('', $logo)->showsLogoOnly());
    }

    public function testTheLoginBackgroundIsReadAsADataUri(): void
    {
        $photo = sys_get_temp_dir() . '/login-' . uniqid() . '.jpg';
        file_put_contents($photo, 'jpeg');

        self::assertSame('data:image/jpeg;base64,' . base64_encode('jpeg'), new Brand('', '', $photo)->getLoginBackground());
        self::assertNull(new Brand()->getLoginBackground());
        self::assertNull(new Brand('', '', '/does/not/exist.jpg')->getLoginBackground());
    }

    public function testAnSvgLoginBackgroundIsRefused(): void
    {
        $svg = sys_get_temp_dir() . '/login-' . uniqid() . '.svg';
        file_put_contents($svg, '<svg/>');

        self::assertNull(new Brand('', '', $svg)->getLoginBackground());
    }

    public function testTheCreditLinkMustBeAnHttpUrl(): void
    {
        self::assertSame('Photo: Roma Neus, CC BY 3.0', new Brand('', '', '', ' Photo: Roma Neus, CC BY 3.0 ')->getLoginBackgroundCredit());
        self::assertSame('https://commons.wikimedia.org/', new Brand('', '', '', '', 'https://commons.wikimedia.org/')->getLoginBackgroundCreditUrl());
        self::assertSame('', new Brand('', '', '', '', 'javascript:alert(1)')->getLoginBackgroundCreditUrl());
    }
}
