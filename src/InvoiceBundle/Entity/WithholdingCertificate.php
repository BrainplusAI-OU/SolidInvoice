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

namespace SolidInvoice\InvoiceBundle\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Brick\Math\BigInteger;
use Brick\Math\BigNumber;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use finfo;
use SolidInvoice\CoreBundle\Doctrine\Type\BigIntegerType;
use SolidInvoice\CoreBundle\Traits\Entity\CompanyAware;
use SolidInvoice\CoreBundle\Traits\Entity\TimeStampable;
use SolidInvoice\InvoiceBundle\Repository\WithholdingCertificateRepository;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Ulid;
use function is_resource;
use function rewind;
use function stream_get_contents;
use function strlen;

/**
 * The certificate a client hands over for the tax it withheld from an invoice: in Thailand the
 * หนังสือรับรองการหักภาษี ณ ที่จ่าย (50 ทวิ). The withheld amount reaches the Revenue Department
 * instead of the seller, so the certificate settles that part of the invoice, and the seller
 * keeps it to deduct the tax from its own.
 *
 * Amounts are in the minor unit of the invoice currency. The scanned certificate is kept in the
 * database, as expense receipts are.
 *
 * @see \SolidInvoice\InvoiceBundle\Tests\Service\WithholdingCertificateRecorderTest
 */
#[ORM\Table(name: WithholdingCertificate::TABLE_NAME)]
#[ORM\Entity(repositoryClass: WithholdingCertificateRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
    ],
    normalizationContext: ['groups' => ['withholding_certificate_api:read']],
)]
class WithholdingCertificate
{
    final public const string TABLE_NAME = 'withholding_certificates';

    final public const int MAX_SIZE = 10 * 1024 * 1024;

    final public const array ALLOWED_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    use TimeStampable;
    use CompanyAware;

    #[ORM\Column(name: 'id', type: UlidType::NAME)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UlidGenerator::class)]
    #[Groups(['withholding_certificate_api:read'])]
    private ?Ulid $id = null;

    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'withholdingCertificates')]
    #[ORM\JoinColumn(name: 'invoice_id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['withholding_certificate_api:read'])]
    private Invoice $invoice;

    /**
     * The certificate's own number, as the client wrote it (book and number on a 50 ทวิ).
     */
    #[ORM\Column(name: 'number', type: Types::STRING, length: 64)]
    #[Groups(['withholding_certificate_api:read'])]
    private string $number;

    #[ORM\Column(name: 'certificate_date', type: Types::DATE_IMMUTABLE)]
    #[Groups(['withholding_certificate_api:read'])]
    private DateTimeImmutable $date;

    #[ORM\Column(name: 'amount', type: BigIntegerType::NAME)]
    #[Groups(['withholding_certificate_api:read'])]
    #[ApiProperty(openapiContext: ['type' => 'number'], jsonSchemaContext: ['type' => 'number'])]
    private BigNumber $amount;

    #[ORM\Column(name: 'file_name', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['withholding_certificate_api:read'])]
    private ?string $fileName = null;

    #[ORM\Column(name: 'mime_type', type: Types::STRING, length: 127, nullable: true)]
    #[Groups(['withholding_certificate_api:read'])]
    private ?string $mimeType = null;

    /**
     * @var resource|string|null
     */
    #[ORM\Column(name: 'content', type: Types::BLOB, nullable: true)]
    private mixed $content = null;

    public function __construct(Invoice $invoice, string $number, DateTimeImmutable $date, BigInteger $amount)
    {
        $this->invoice = $invoice;
        $this->company = $invoice->getCompany();
        $this->number = $number;
        $this->date = $date;
        $this->amount = $amount;
    }

    public function getId(): ?Ulid
    {
        return $this->id;
    }

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    public function getAmount(): BigInteger
    {
        return $this->amount->toBigInteger();
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function hasFile(): bool
    {
        return $this->fileName !== null;
    }

    public function getContent(): string
    {
        if (is_resource($this->content)) {
            rewind($this->content);

            return (string) stream_get_contents($this->content);
        }

        return (string) $this->content;
    }

    public function attach(string $fileName, string $content): self
    {
        $this->fileName = $fileName;
        $this->content = $content;
        $this->mimeType = $content === '' ? null : (string) new finfo(FILEINFO_MIME_TYPE)->buffer($content);

        return $this;
    }

    #[Groups(['withholding_certificate_api:read'])]
    public function getFileSize(): int
    {
        return strlen($this->getContent());
    }
}
