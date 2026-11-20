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

namespace SolidInvoice\CatalogBundle\Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Group;
use SolidInvoice\ApiBundle\Test\ApiTestCase;
use SolidInvoice\ApiBundle\Versioning\ApiVersion;
use SolidInvoice\CatalogBundle\Entity\CatalogItem;
use Symfony\Component\Uid\Ulid;

#[Group('functional')]
final class CatalogItemTest extends ApiTestCase
{
    protected function getResourceClass(): string
    {
        return CatalogItem::class;
    }

    public function testCreateWithADecimalPrice(): void
    {
        // Decimal money is outside the resource's JSON schema, so this is not requestPost(); see MoneyFormatTest.
        $response = self::$client->request('POST', '/api/catalog-items', ['json' => [
            'name' => 'Long term rental',
            'description' => "Customer: Astra Holmes\nCommission fees: 30%",
            'currencyCode' => 'THB',
            'price' => '15345.00',
        ], 'headers' => [
            'content-type' => 'application/ld+json',
            'accept' => 'application/ld+json',
            ApiVersion::HEADER => ApiVersion::V2026_10->value,
        ]]);

        self::assertResponseStatusCodeSame(201);
        $result = $response->toArray(false);

        self::assertTrue(Ulid::isValid($result['id'], Ulid::FORMAT_BASE_32));
        self::assertSame('Long term rental', $result['name']);
        self::assertSame('15345.00', $result['price']);
        self::assertSame('THB', $result['currencyCode']);
        self::assertNull($result['tax']);

        $item = self::getContainer()->get('doctrine')->getRepository(CatalogItem::class)->find(Ulid::fromString($result['id']));
        self::assertInstanceOf(CatalogItem::class, $item);
        self::assertSame('1534500', (string) $item->getPrice());
    }

    public function testANameIsRequired(): void
    {
        self::$client->request('POST', '/api/catalog-items', ['json' => [
            'name' => '',
            'currencyCode' => 'THB',
            'price' => '10.00',
        ], 'headers' => [
            'content-type' => 'application/ld+json',
            'accept' => 'application/ld+json',
            ApiVersion::HEADER => ApiVersion::V2026_10->value,
        ]]);

        self::assertResponseStatusCodeSame(422);
    }
}
