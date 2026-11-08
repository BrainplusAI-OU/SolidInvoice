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

namespace SolidInvoice\ExpenseBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\Group;
use SolidInvoice\ExpenseBundle\Entity\Expense;
use SolidInvoice\ExpenseBundle\Entity\ExpenseAttachment;
use SolidInvoice\ExpenseBundle\Test\Factory\ExpenseFactory;
use SolidInvoice\ExpenseBundle\Test\Factory\VendorFactory;
use SolidInvoice\InstallBundle\Test\EnsureApplicationInstalled;
use SolidInvoice\UserBundle\Enum\UserSettingType;
use SolidInvoice\UserBundle\Repository\UserSettingRepositoryInterface;
use SolidInvoice\UserBundle\Test\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use function Zenstruck\Foundry\Persistence\save;

#[Group('functional')]
final class ExpenseWebTest extends WebTestCase
{
    use EnsureApplicationInstalled;

    private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function testRecordAnExpenseWithAReceipt(): void
    {
        $client = $this->client();
        $vendor = VendorFactory::createOne(['company' => $this->company, 'name' => 'Makro']);

        $crawler = $client->request(Request::METHOD_GET, '/expenses/add');
        self::assertResponseIsSuccessful();

        $receipt = sys_get_temp_dir() . '/receipt-' . uniqid() . '.png';
        new Filesystem()->dumpFile($receipt, (string) base64_decode(self::PNG, true));

        $form = $crawler->selectButton('Save')->form();
        $form['expense[date]'] = '2026-10-05';
        $form['expense[description]'] = 'Printer paper';
        $form->setValues(['expense[vendor]' => (string) $vendor->getId(), 'expense[currencyCode]' => 'THB']);
        $form['expense[amount]'] = '1070.50';
        $form['expense[taxAmount]'] = '70.03';
        $form['expense[receipts]'][0]->upload($receipt);

        $client->submit($form);

        self::assertResponseRedirects('/expenses/');

        $expense = self::getContainer()->get('doctrine')->getRepository(Expense::class)->findOneBy(['description' => 'Printer paper']);
        self::assertInstanceOf(Expense::class, $expense);
        self::assertTrue($expense->getAmount()->isEqualTo(107050));
        self::assertTrue($expense->getTaxAmount()->isEqualTo(7003));
        self::assertSame('Makro', $expense->getVendor()?->getName());
        self::assertCount(1, $expense->getAttachments());

        $attachment = $expense->getAttachments()->first();
        self::assertInstanceOf(ExpenseAttachment::class, $attachment);

        $client->request(Request::METHOD_GET, '/expenses/attachments/' . $attachment->getId());
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertSame(base64_decode(self::PNG, true), $client->getResponse()->getContent());

        $crawler = $client->request(Request::METHOD_GET, '/expenses/edit/' . $expense->getId());
        self::assertResponseIsSuccessful();
        $client->submit($crawler->filter('#delete-attachment-' . $attachment->getId())->form());

        self::assertResponseRedirects('/expenses/edit/' . $expense->getId());
        self::getContainer()->get('doctrine')->getManager()->clear();
        self::assertSame(0, self::getContainer()->get('doctrine')->getRepository(ExpenseAttachment::class)->count([]));
    }

    public function testDeletingAReceiptNeedsAValidToken(): void
    {
        $client = $this->client();

        $expense = ExpenseFactory::createOne(['company' => $this->company]);
        $attachment = new ExpenseAttachment();
        $attachment->setCompany($expense->getCompany());
        $attachment->setFileName('receipt.png');
        $attachment->setBase64Content(self::PNG);

        $expense->addAttachment($attachment);
        save($expense);

        $client->request(Request::METHOD_POST, '/expenses/attachments/' . $attachment->getId() . '/delete', ['_token' => 'forged']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testListPagesRender(): void
    {
        $client = $this->client();

        foreach (['/expenses/', '/expenses/vendors', '/expenses/vendors/add', '/expenses/categories', '/expenses/categories/add', '/dashboard'] as $path) {
            $client->request(Request::METHOD_GET, $path);
            self::assertResponseIsSuccessful($path);
        }

        self::assertSelectorTextContains('body', 'Revenue and Expenses');
    }

    private function client(): KernelBrowser
    {
        $user = UserFactory::createOne(['companies' => [$this->company]]);

        $settings = self::getContainer()->get(UserSettingRepositoryInterface::class);
        $settings->saveSetting($user, UserSettingType::OnboardComplete, 'true');
        $settings->saveSetting($user, UserSettingType::OnboardingChecklistDismissed, 'true');

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->loginUser($user);

        return $client;
    }
}
