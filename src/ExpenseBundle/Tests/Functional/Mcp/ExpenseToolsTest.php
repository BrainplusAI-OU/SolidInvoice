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

namespace SolidInvoice\ExpenseBundle\Tests\Functional\Mcp;

use Brick\Math\BigInteger;
use DateTimeImmutable;
use Mcp\Exception\ToolCallException;
use PHPUnit\Framework\Attributes\Group;
use SolidInvoice\CoreBundle\Company\CompanySelector;
use SolidInvoice\CoreBundle\Test\Factory\CompanyFactory;
use SolidInvoice\ExpenseBundle\Entity\Expense;
use SolidInvoice\ExpenseBundle\Mcp\ExpenseReadTools;
use SolidInvoice\ExpenseBundle\Mcp\ExpenseWriteTools;
use SolidInvoice\ExpenseBundle\Test\Factory\ExpenseFactory;
use SolidInvoice\ExpenseBundle\Test\Factory\VendorFactory;
use SolidInvoice\InstallBundle\Test\EnsureApplicationInstalled;
use SolidInvoice\McpBundle\Security\McpOAuthAuthenticator;
use SolidInvoice\McpBundle\Security\McpScope;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Ulid;

#[Group('functional')]
final class ExpenseToolsTest extends KernelTestCase
{
    use EnsureApplicationInstalled;

    private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function testCreateExpenseMatchesTheVendorByNameAndCreatesTheCategory(): void
    {
        $this->setActiveScopes([McpScope::Write->value]);
        $vendor = VendorFactory::createOne(['company' => $this->company, 'name' => 'Makro']);

        $tools = self::getContainer()->get(ExpenseWriteTools::class);
        self::assertInstanceOf(ExpenseWriteTools::class, $tools);

        $result = $tools->createExpense('2026-10-02', 'Coffee beans', 45000, 'THB', 2944, 'makro', 'Office supplies');

        self::assertSame('45000', $result['amount']);
        self::assertSame('2944', $result['tax_amount']);
        self::assertSame('42056', $result['net_amount']);
        self::assertSame($vendor->getId()?->toRfc4122(), $result['vendor']['id']);
        self::assertSame('Office supplies', $result['category']['name']);

        $expense = self::getContainer()->get('doctrine')->getRepository(Expense::class)->find(Ulid::fromString($result['id']));
        self::assertInstanceOf(Expense::class, $expense);
        self::assertSame($this->company->getId()->toRfc4122(), $expense->getCompany()->getId()->toRfc4122());
    }

    public function testCreateExpenseRejectsTaxAboveTheAmount(): void
    {
        $this->setActiveScopes([McpScope::Write->value]);

        $tools = self::getContainer()->get(ExpenseWriteTools::class);
        self::assertInstanceOf(ExpenseWriteTools::class, $tools);

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessageIsOrContains('taxAmount');

        $tools->createExpense('2026-10-02', 'Taxi', 100, 'THB', 200);
    }

    public function testReadOnlyTokenRejectsWrite(): void
    {
        $this->setActiveScopes([McpScope::Read->value]);

        $tools = self::getContainer()->get(ExpenseWriteTools::class);
        self::assertInstanceOf(ExpenseWriteTools::class, $tools);

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessageIsOrContains('mcp:write');

        $tools->createExpense('2026-10-02', 'Taxi', 100, 'THB');
    }

    public function testAddAttachment(): void
    {
        $this->setActiveScopes([McpScope::Write->value]);
        $expense = ExpenseFactory::createOne(['company' => $this->company]);

        $tools = self::getContainer()->get(ExpenseWriteTools::class);
        self::assertInstanceOf(ExpenseWriteTools::class, $tools);

        $result = $tools->addExpenseAttachment((string) $expense->getId(), 'receipt.png', self::PNG);

        self::assertCount(1, $result['attachments']);
        self::assertSame('image/png', $result['attachments'][0]['mime_type']);
    }

    public function testAddAttachmentRejectsOtherFileTypes(): void
    {
        $this->setActiveScopes([McpScope::Write->value]);
        $expense = ExpenseFactory::createOne(['company' => $this->company]);

        $tools = self::getContainer()->get(ExpenseWriteTools::class);
        self::assertInstanceOf(ExpenseWriteTools::class, $tools);

        $this->expectException(ToolCallException::class);

        $tools->addExpenseAttachment((string) $expense->getId(), 'page.html', base64_encode('<html><script>alert(1)</script></html>'));
    }

    public function testAnotherCompanysExpenseIsNotFound(): void
    {
        $otherCompany = CompanyFactory::new()->create();
        self::getContainer()->get(CompanySelector::class)->switchCompany($otherCompany->getId());
        $expense = ExpenseFactory::createOne(['company' => $otherCompany]);

        $this->setActiveScopes([McpScope::Read->value]);

        $tools = self::getContainer()->get(ExpenseReadTools::class);
        self::assertInstanceOf(ExpenseReadTools::class, $tools);

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessageIsOrContains('not found');

        $tools->getExpense((string) $expense->getId());
    }

    public function testSummarizeKeepsCurrenciesApart(): void
    {
        $this->setActiveScopes([McpScope::Read->value]);

        foreach ([['2026-09-03', 'THB', 1000], ['2026-09-20', 'THB', 500], ['2026-09-21', 'EUR', 300], ['2026-10-01', 'THB', 700]] as [$date, $currency, $amount]) {
            ExpenseFactory::createOne([
                'company' => $this->company,
                'date' => new DateTimeImmutable($date),
                'currencyCode' => $currency,
                'amount' => BigInteger::of($amount),
            ]);
        }

        $tools = self::getContainer()->get(ExpenseReadTools::class);
        self::assertInstanceOf(ExpenseReadTools::class, $tools);

        $result = $tools->summarizeExpenses('2026-09-01', '2026-09-30', 'month');

        self::assertSame('month', $result['group_by']);
        self::assertEqualsCanonicalizing([
            ['group' => '2026-09', 'currency' => 'THB', 'amount' => '1500', 'tax_amount' => '0', 'count' => 2],
            ['group' => '2026-09', 'currency' => 'EUR', 'amount' => '300', 'tax_amount' => '0', 'count' => 1],
        ], $result['groups']);
    }

    /**
     * @param list<string> $scopes
     */
    private function setActiveScopes(array $scopes): void
    {
        $container = self::getContainer();

        $stack = $container->get(RequestStack::class);
        self::assertInstanceOf(RequestStack::class, $stack);

        while ($stack->getMainRequest() instanceof Request) {
            $stack->pop();
        }

        $request = new Request();
        $request->attributes->set(McpOAuthAuthenticator::ATTR_SCOPES, $scopes);

        $stack->push($request);

        $selector = $container->get(CompanySelector::class);
        self::assertInstanceOf(CompanySelector::class, $selector);
        $selector->switchCompany($this->company->getId());
    }
}
