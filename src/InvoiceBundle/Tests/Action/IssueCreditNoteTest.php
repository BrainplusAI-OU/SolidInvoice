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

namespace SolidInvoice\InvoiceBundle\Tests\Action;

use Brick\Math\BigInteger;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use SolidInvoice\ClientBundle\Test\Factory\ClientFactory;
use SolidInvoice\InstallBundle\Test\EnsureApplicationInstalled;
use SolidInvoice\InvoiceBundle\Action\IssueCreditNote;
use SolidInvoice\InvoiceBundle\Action\ViewCreditNote;
use SolidInvoice\InvoiceBundle\Entity\Invoice;
use SolidInvoice\InvoiceBundle\Entity\Line;
use SolidInvoice\InvoiceBundle\Enum\InvoiceStatus;
use SolidInvoice\InvoiceBundle\Service\CreditNoteIssuer;
use SolidInvoice\TaxBundle\Entity\InvoiceTax;
use SolidInvoice\TaxBundle\Entity\LineTax;
use SolidInvoice\TaxBundle\Entity\Tax;
use SolidInvoice\TaxBundle\Enum\TaxDirection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

#[CoversClass(IssueCreditNote::class)]
#[CoversClass(ViewCreditNote::class)]
final class IssueCreditNoteTest extends KernelTestCase
{
    use EnsureApplicationInstalled;

    public function testTheFormOffersWhatIsLeftToCredit(): void
    {
        $invoice = $this->issuedInvoice();

        $params = $this->action()($this->request('GET'), $invoice);

        self::assertIsArray($params);
        self::assertSame('1000.00', $params['form']['amount']->vars['value']);
    }

    public function testSubmittingTheFormIssuesTheCreditNote(): void
    {
        $invoice = $this->issuedInvoice();

        $request = $this->request('POST', [
            'credit_note' => [
                'amount' => '400.00',
                'reason' => 'Two days not delivered',
                'date' => '2026-11-20',
            ],
        ]);

        $response = $this->action()($request, $invoice);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertCount(1, $invoice->getCreditNotes());
        self::assertSame('CN-2026-0001', $invoice->getCreditNotes()->first()->getNumber());
        self::assertSame('64200', (string) $invoice->getBalance()->toBigInteger());
    }

    public function testADraftIsSentBackToTheInvoice(): void
    {
        $response = $this->action()($this->request('GET'), $this->issuedInvoice(InvoiceStatus::Draft));

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testTheDocumentShowsWhatThaiLawAsksOfACreditNote(): void
    {
        $this->company->setCountry('TH');
        $invoice = $this->issuedInvoice();
        $issuer = self::getContainer()->get(CreditNoteIssuer::class);
        self::assertInstanceOf(CreditNoteIssuer::class, $issuer);
        $creditNote = $issuer->issue($invoice, BigInteger::of(40000), 'Two days not delivered', new DateTimeImmutable('2026-11-20'));

        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $html = $twig->render('@SolidInvoiceInvoice/CreditNote/pdf.html.twig', ['creditNote' => $creditNote, 'invoice' => $invoice]);

        self::assertStringContainsString('Credit note · ใบลดหนี้', $html);
        self::assertStringContainsString('#CN-2026-0001', $html);
        self::assertStringContainsString('#2026-0001', $html);
        self::assertStringContainsString('Two days not delivered', $html);

        foreach (['Original value' => '1,000.00', 'Correct value' => '600.00', 'Difference' => '400.00', 'VAT on the difference' => '28.00', 'Total credited' => '428.00'] as $label => $amount) {
            self::assertMatchesRegularExpression('~' . preg_quote($label, '~') . '</td>\s*<td[^>]*>[^<]*' . preg_quote($amount, '~') . '~u', $html);
        }
    }

    private function action(): IssueCreditNote
    {
        $action = self::getContainer()->get(IssueCreditNote::class);
        self::assertInstanceOf(IssueCreditNote::class, $action);

        return $action;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function request(string $method, array $data = []): Request
    {
        $container = self::getContainer();
        $request = Request::create('/invoices/credit-note/x', $method);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $container->get('request_stack')->push($request);

        if ($data !== []) {
            $tokens = $container->get(CsrfTokenManagerInterface::class);
            self::assertInstanceOf(CsrfTokenManagerInterface::class, $tokens);
            $data['credit_note']['_token'] = $tokens->getToken('credit_note')->getValue();
            $request->request->replace($data);
        }

        return $request;
    }

    private function issuedInvoice(InvoiceStatus $status = InvoiceStatus::Pending): Invoice
    {
        $vat = new Tax()->setName('VAT')->setRate(7)->setType(Tax::TYPE_EXCLUSIVE);

        $lineTax = new LineTax();
        $lineTax->snapshotFrom($vat);

        $line = new Line();
        $line->setDescription('Marketing services')->setQty(1)->setPrice(100000);
        $line->addTax($lineTax);

        $withholding = new InvoiceTax();
        $withholding->setNameSnapshot('WHT');
        $withholding->setRateSnapshot('3.0000');
        $withholding->setDirection(TaxDirection::Deductive);

        $invoice = new Invoice();
        $invoice->setClient(ClientFactory::createOne(['company' => $this->company, 'currencyCode' => 'THB']));
        $invoice->setCompany($this->company);
        $invoice->setStatus($status);
        $invoice->setCurrencyCode($status === InvoiceStatus::Draft ? null : 'THB');
        $invoice->setInvoiceId('2026-0001');
        $invoice->addLine($line);
        $invoice->addInvoiceTax($withholding);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->persist($vat);
        $em->persist($invoice);
        $em->flush();

        self::assertSame('107000', (string) $invoice->getTotal()->toBigInteger());
        self::assertSame('3000', (string) $invoice->getWithholdingAmount()->toBigInteger());

        return $invoice;
    }
}
