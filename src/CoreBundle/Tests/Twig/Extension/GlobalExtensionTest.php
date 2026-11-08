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

namespace SolidInvoice\CoreBundle\Tests\Twig\Extension;

use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use SolidInvoice\CoreBundle\Branding\Brand;
use SolidInvoice\CoreBundle\Company\CompanySelector;
use SolidInvoice\CoreBundle\Pdf\Generator;
use SolidInvoice\CoreBundle\SolidInvoiceCoreBundle;
use SolidInvoice\CoreBundle\Twig\Extension\GlobalExtension;
use SolidInvoice\MoneyBundle\Calculator;
use SolidInvoice\SettingsBundle\SystemConfig;
use SolidWorx\Toggler\ToggleInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class GlobalExtensionTest extends TestCase
{
    public function testAppVersionIsExposedWhenNotInSaasMode(): void
    {
        $globals = $this->createExtension(saasEnabled: false)->getGlobals();

        self::assertSame(SolidInvoiceCoreBundle::VERSION, $globals['app_version']);
    }

    public function testAppVersionIsHiddenInSaasMode(): void
    {
        $globals = $this->createExtension(saasEnabled: true)->getGlobals();

        self::assertNull($globals['app_version']);
    }

    public function testAppNameIsSolidInvoiceWithoutABrand(): void
    {
        $globals = $this->createExtension(saasEnabled: false)->getGlobals();

        self::assertSame(SolidInvoiceCoreBundle::APP_NAME, $globals['app_name']);
    }

    public function testAppNameIsTheBrandOfAWhiteLabelInstallation(): void
    {
        $globals = $this->createExtension(saasEnabled: false, brand: new Brand('Palmora Billing'))->getGlobals();

        self::assertSame('Palmora Billing', $globals['app_name']);
    }

    public function testTheDefaultLogoIsTheBrandLogo(): void
    {
        $logo = sys_get_temp_dir() . '/brand-' . uniqid() . '.png';
        file_put_contents($logo, 'not really a png');

        $html = $this->createExtension(saasEnabled: null, brand: new Brand('Palmora Billing', $logo))->displayAppLogo(showDefault: true, showOnlyAppIcon: true);

        self::assertStringContainsString('src="data:image/png;base64,' . base64_encode('not really a png') . '"', $html);
    }

    private function createExtension(?bool $saasEnabled, Brand $brand = new Brand()): GlobalExtension
    {
        $toggler = $this->createMock(ToggleInterface::class);
        $toggler
            ->expects($saasEnabled === null ? self::never() : self::once())
            ->method('isActive')
            ->with('saas_enabled')
            ->willReturn((bool) $saasEnabled);

        return new GlobalExtension(
            new Calculator(),
            $this->createStub(Generator::class),
            $this->createStub(SystemConfig::class),
            new RequestStack(),
            new CompanySelector($this->createStub(ManagerRegistry::class)),
            'default',
            $toggler,
            $brand,
        );
    }
}
