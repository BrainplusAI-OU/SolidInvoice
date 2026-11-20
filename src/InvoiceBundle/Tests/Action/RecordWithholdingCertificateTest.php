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

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use SolidInvoice\ClientBundle\Test\Factory\ClientFactory;
use SolidInvoice\InstallBundle\Test\EnsureApplicationInstalled;
use SolidInvoice\InvoiceBundle\Action\RecordWithholdingCertificate;
use SolidInvoice\InvoiceBundle\Action\WithholdingCertificateFile;
use SolidInvoice\InvoiceBundle\Entity\Invoice;
use SolidInvoice\InvoiceBundle\Entity\Line;
use SolidInvoice\InvoiceBundle\Entity\WithholdingCertificate;
use SolidInvoice\InvoiceBundle\Enum\InvoiceStatus;
use SolidInvoice\TaxBundle\Entity\InvoiceTax;
use SolidInvoice\TaxBundle\Entity\LineTax;
use SolidInvoice\TaxBundle\Entity\Tax;
use SolidInvoice\TaxBundle\Enum\TaxDirection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;

#[CoversClass(RecordWithholdingCertificate::class)]
#[CoversClass(WithholdingCertificateFile::class)]
final class RecordWithholdingCertificateTest extends KernelTestCase
{
    use EnsureApplicationInstalled;

    public function testTheFormOffersTheWithholdingLeftToCertify(): void
    {
        $params = $this->action()($this->request('GET'), $this->issuedInvoice());

        self::assertIsArray($params);
        self::assertSame('30.00', $params['form']['amount']->vars['value']);
    }

    public function testSubmittingTheFormRecordsTheCertificateAndItsScan(): void
    {
        $invoice = $this->issuedInvoice();

        $path = (string) tempnam(sys_get_temp_dir(), 'wht');
        file_put_contents($path, "%PDF-1.4\n%%EOF\n");

        $request = $this->request('POST', [
            'withholding_certificate' => [
                'number' => 'Book 1 No. 42',
                'date' => '2026-11-20',
                'amount' => '30.00',
            ],
        ], [
            'withholding_certificate' => ['file' => new UploadedFile($path, 'หนังสือรับรอง 50 ทวิ.pdf', 'application/pdf', null, true)],
        ]);

        $response = $this->action()($request, $invoice);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertCount(1, $invoice->getWithholdingCertificates());
        self::assertSame('104000', (string) $invoice->getBalance()->toBigInteger());

        $certificate = $invoice->getWithholdingCertificates()->first();
        self::assertInstanceOf(WithholdingCertificate::class, $certificate);

        $file = new WithholdingCertificateFile()($certificate);
        self::assertSame('application/pdf', $file->headers->get('Content-Type'));
        self::assertStringContainsString('filename*=utf-8\'\'', (string) $file->headers->get('Content-Disposition'));
        self::assertStringStartsWith('%PDF', (string) $file->getContent());
    }

    public function testACertificateWithoutAScanHasNoFile(): void
    {
        $certificate = new WithholdingCertificate($this->issuedInvoice(), 'WHT-1', new \DateTimeImmutable('2026-11-20'), \Brick\Math\BigInteger::of(3000));

        $this->expectException(NotFoundHttpException::class);

        new WithholdingCertificateFile()($certificate);
    }

    public function testADraftIsSentBackToTheInvoice(): void
    {
        $response = $this->action()($this->request('GET'), $this->issuedInvoice(InvoiceStatus::Draft));

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    private function action(): RecordWithholdingCertificate
    {
        $action = self::getContainer()->get(RecordWithholdingCertificate::class);
        self::assertInstanceOf(RecordWithholdingCertificate::class, $action);

        return $action;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $files
     */
    private function request(string $method, array $data = [], array $files = []): Request
    {
        $container = self::getContainer();
        $request = Request::create('/invoices/withholding-certificate/x', $method);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $container->get('request_stack')->push($request);

        if ($data !== []) {
            $tokens = $container->get(CsrfTokenManagerInterface::class);
            self::assertInstanceOf(CsrfTokenManagerInterface::class, $tokens);
            $data['withholding_certificate']['_token'] = $tokens->getToken('withholding_certificate')->getValue();
            $request->request->replace($data);
            $request->files->replace($files);
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
