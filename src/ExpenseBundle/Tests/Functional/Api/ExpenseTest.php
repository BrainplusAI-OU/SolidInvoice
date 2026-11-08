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

namespace SolidInvoice\ExpenseBundle\Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Group;
use SolidInvoice\ApiBundle\Test\ApiTestCase;
use SolidInvoice\ApiBundle\Versioning\ApiVersion;
use SolidInvoice\CoreBundle\Company\CompanySelector;
use SolidInvoice\CoreBundle\Test\Factory\CompanyFactory;
use SolidInvoice\ExpenseBundle\Entity\Expense;
use SolidInvoice\ExpenseBundle\Test\Factory\ExpenseCategoryFactory;
use SolidInvoice\ExpenseBundle\Test\Factory\ExpenseFactory;
use SolidInvoice\ExpenseBundle\Test\Factory\VendorFactory;
use Symfony\Component\Uid\Ulid;

#[Group('functional')]
final class ExpenseTest extends ApiTestCase
{
    private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function getResourceClass(): string
    {
        return Expense::class;
    }

    public function testCreate(): void
    {
        $vendor = VendorFactory::createOne(['company' => $this->company, 'name' => 'Makro']);
        $category = ExpenseCategoryFactory::createOne(['company' => $this->company, 'name' => 'Office']);

        // Decimal money is outside the resource's JSON schema, so this is not requestPost(); see MoneyFormatTest.
        $response = self::$client->request('POST', '/api/expenses', ['json' => [
            'date' => '2026-10-01',
            'description' => 'Printer paper',
            'currencyCode' => 'THB',
            'amount' => '1070.00',
            'taxAmount' => '70.00',
            'vendor' => $this->getIriFromResource($vendor),
            'category' => $this->getIriFromResource($category),
            'paymentMethod' => 'Card',
        ], 'headers' => [
            'content-type' => 'application/ld+json',
            'accept' => 'application/ld+json',
            ApiVersion::HEADER => ApiVersion::V2026_10->value,
        ]]);

        self::assertResponseStatusCodeSame(201);
        $result = $response->toArray(false);

        self::assertTrue(Ulid::isValid($result['id'], Ulid::FORMAT_BASE_32));
        self::assertSame('Printer paper', $result['description']);
        self::assertSame('1070.00', $result['amount']);
        self::assertSame('70.00', $result['taxAmount']);
        self::assertSame('1000.00', $result['netAmount']);
        self::assertSame('Makro', $result['vendor']['name']);
        self::assertSame('Office', $result['category']['name']);

        $expense = self::getContainer()->get('doctrine')->getRepository(Expense::class)->find(Ulid::fromString($result['id']));
        self::assertInstanceOf(Expense::class, $expense);
        self::assertSame('107000', (string) $expense->getAmount());
        self::assertSame('2026-10-01', $expense->getDate()->format('Y-m-d'));
    }

    public function testTaxCannotExceedTheAmount(): void
    {
        self::$client->request('POST', '/api/expenses', [
            'json' => [
                'date' => '2026-10-01',
                'description' => 'Taxi',
                'currencyCode' => 'THB',
                'amount' => '100.00',
                'taxAmount' => '150.00',
            ],
            'headers' => [
                'content-type' => 'application/ld+json',
                'accept' => 'application/ld+json',
                ApiVersion::HEADER => ApiVersion::V2026_10->value,
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testGetCollectionIsScopedByCompany(): void
    {
        ExpenseFactory::createMany(2, ['company' => $this->company]);

        $otherCompany = CompanyFactory::new()->create();
        self::getContainer()->get(CompanySelector::class)->switchCompany($otherCompany->getId());
        ExpenseFactory::createMany(3, ['company' => $otherCompany]);
        self::getContainer()->get(CompanySelector::class)->switchCompany($this->company->getId());

        $data = $this->requestGetCollection('/api/expenses');

        self::assertSame(2, $data['totalItems']);
    }

    public function testAttachAReceipt(): void
    {
        $expense = ExpenseFactory::createOne(['company' => $this->company]);

        self::$client->request('POST', '/api/expenses/' . $expense->getId() . '/attachments', [
            'json' => ['fileName' => 'receipt.png', 'content' => self::PNG],
            'headers' => ['content-type' => 'application/ld+json', 'accept' => 'application/ld+json'],
        ]);

        self::assertResponseStatusCodeSame(201);

        $result = $this->requestGet('/api/expenses/' . $expense->getId());

        self::assertCount(1, $result['attachments']);
        self::assertSame('receipt.png', $result['attachments'][0]['fileName']);
        self::assertSame('image/png', $result['attachments'][0]['mimeType']);
    }

    public function testAReceiptMustBeAPdfOrAnImage(): void
    {
        $expense = ExpenseFactory::createOne(['company' => $this->company]);

        self::$client->request('POST', '/api/expenses/' . $expense->getId() . '/attachments', [
            'json' => ['fileName' => 'receipt.svg', 'content' => base64_encode('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
            'headers' => ['content-type' => 'application/ld+json', 'accept' => 'application/ld+json'],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testCannotAttachToAnotherCompanysExpense(): void
    {
        $otherCompany = CompanyFactory::new()->create();
        self::getContainer()->get(CompanySelector::class)->switchCompany($otherCompany->getId());
        $expense = ExpenseFactory::createOne(['company' => $otherCompany]);
        self::getContainer()->get(CompanySelector::class)->switchCompany($this->company->getId());

        self::$client->request('POST', '/api/expenses/' . $expense->getId() . '/attachments', [
            'json' => ['fileName' => 'receipt.png', 'content' => self::PNG],
            'headers' => ['content-type' => 'application/ld+json', 'accept' => 'application/ld+json'],
        ]);

        self::assertResponseStatusCodeSame(404);
    }
}
