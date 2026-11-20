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

namespace SolidInvoice\CatalogBundle\Tests\Twig\Components;

use Carbon\CarbonImmutable;
use SolidInvoice\CatalogBundle\Test\Factory\CatalogItemFactory;
use SolidInvoice\ClientBundle\Entity\Client;
use SolidInvoice\ClientBundle\Test\Factory\ClientFactory;
use SolidInvoice\ClientBundle\Test\Factory\ContactFactory;
use SolidInvoice\CoreBundle\Test\LiveComponentTest;
use SolidInvoice\InvoiceBundle\Entity\Invoice;
use SolidInvoice\InvoiceBundle\Entity\Line;
use SolidInvoice\InvoiceBundle\Enum\InvoiceStatus;
use SolidInvoice\InvoiceBundle\Manager\InvoiceFormManager;
use SolidInvoice\InvoiceBundle\Twig\Components\CreateInvoice;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

final class FillsLinesFromCatalogTest extends LiveComponentTest
{
    public function testPickingAnItemAddsALineWithItsDetails(): void
    {
        $item = CatalogItemFactory::createOne([
            'company' => $this->company,
            'name' => 'Long term rental',
            'description' => 'Commission fees: 30%',
            'price' => 1534500,
            'currencyCode' => 'USD',
        ]);

        [$component, $invoice] = $this->editInvoice('USD');

        self::assertStringContainsString('Long term rental', $component->render()->toString());

        $component->call('addCatalogItem', ['item' => (string) $item->getId()]);
        $component->call('saveUpdate');

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();

        $refreshed = $em->find(Invoice::class, $invoice->getId());

        self::assertNotNull($refreshed);
        $lines = $refreshed->getLines()->toArray();
        self::assertCount(2, $lines);
        self::assertSame('Existing', $lines[0]->getName());
        self::assertSame('Long term rental', $lines[1]->getName());
        self::assertSame('Commission fees: 30%', $lines[1]->getDescription());
        self::assertTrue($lines[1]->getPrice()->isEqualTo(1534500));
        self::assertTrue($lines[1]->getQty()->isEqualTo(1));
    }

    public function testAnItemInAnotherCurrencyLeavesThePriceToType(): void
    {
        $item = CatalogItemFactory::createOne([
            'company' => $this->company,
            'name' => 'Car fuel',
            'price' => 200000,
            'currencyCode' => 'THB',
        ]);

        [$component] = $this->editInvoice('USD');

        $component->call('addCatalogItem', ['item' => (string) $item->getId()]);

        $lines = array_values($component->component()->formValues['lines']);
        self::assertSame('Car fuel', $lines[1]['name']);
        // The re-render shows the empty price as the field's zero.
        self::assertContains($lines[1]['price'], ['', '0', '0.00']);
    }

    public function testAnUnknownItemChangesNothing(): void
    {
        [$component] = $this->editInvoice('USD');

        $component->call('addCatalogItem', ['item' => 'not-an-id']);

        self::assertCount(1, $component->component()->formValues['lines']);
    }

    /**
     * @return array{TestLiveComponent, Invoice}
     */
    private function editInvoice(string $currency): array
    {
        $em = self::getContainer()->get('doctrine')->getManager();

        $client = ClientFactory::createOne(['name' => 'Catalog Corp ' . uniqid(), 'currencyCode' => $currency]);
        self::assertInstanceOf(Client::class, $client);
        $contact = ContactFactory::createOne(['email' => uniqid() . '@example.com', 'client' => $client]);

        $invoice = new Invoice();
        $invoice->setStatus(InvoiceStatus::Draft);
        $invoice->setClient($client);
        $invoice->setInvoiceId('INV-CATALOG-' . uniqid());
        $invoice->setInvoiceDate(CarbonImmutable::parse('2026-11-20'));
        $invoice->addUser($contact);
        $invoice->addLine(new Line()->setName('Existing')->setPrice(10000)->setQty(1));

        $em->persist($invoice);
        $em->flush();

        $formManager = self::getContainer()->get(InvoiceFormManager::class);

        $component = $this->createLiveComponent(
            name: CreateInvoice::class,
            data: [
                'dto' => $formManager->createDTOFromInvoice($invoice),
                'isEdit' => true,
                'invoice' => $invoice,
            ],
            client: $this->client,
        )->actingAs($this->getUser());

        return [$component, $invoice];
    }
}
