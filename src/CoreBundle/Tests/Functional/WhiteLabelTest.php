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

namespace SolidInvoice\CoreBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\Group;
use SolidInvoice\InstallBundle\Test\EnsureApplicationInstalled;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

#[Group('functional')]
final class WhiteLabelTest extends WebTestCase
{
    use EnsureApplicationInstalled;

    private const string BRAND = 'Palmora Billing';

    protected function tearDown(): void
    {
        $this->setBrand(null);

        parent::tearDown();
    }

    public function testTheLoginPageShowsSolidInvoiceByDefault(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();

        $client->request(Request::METHOD_GET, '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'SolidInvoice');
    }

    public function testTheLoginPageOfAWhiteLabelInstallationShowsTheBrand(): void
    {
        $this->setBrand(self::BRAND);
        self::ensureKernelShutdown();
        $client = self::createClient();

        $client->request(Request::METHOD_GET, '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', self::BRAND);
        self::assertSelectorTextNotContains('body', 'Powered by');
        self::assertSelectorTextNotContains('body', 'SolidInvoice');
        self::assertSelectorTextContains('title', self::BRAND);
    }

    private function setBrand(?string $name): void
    {
        if ($name === null) {
            putenv('SOLIDINVOICE_BRAND_NAME');
            unset($_ENV['SOLIDINVOICE_BRAND_NAME'], $_SERVER['SOLIDINVOICE_BRAND_NAME']);

            return;
        }

        putenv('SOLIDINVOICE_BRAND_NAME=' . $name);
        $_ENV['SOLIDINVOICE_BRAND_NAME'] = $_SERVER['SOLIDINVOICE_BRAND_NAME'] = $name;
    }
}
