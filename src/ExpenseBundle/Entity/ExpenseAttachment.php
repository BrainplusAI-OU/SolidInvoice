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

namespace SolidInvoice\ExpenseBundle\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use finfo;
use SolidInvoice\CoreBundle\Traits\Entity\CompanyAware;
use SolidInvoice\CoreBundle\Traits\Entity\TimeStampable;
use SolidInvoice\ExpenseBundle\Api\Processor\ExpenseAttachmentPersistProcessor;
use SolidInvoice\ExpenseBundle\Repository\ExpenseAttachmentRepository;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use function base64_decode;
use function basename;
use function in_array;
use function is_resource;
use function mb_substr;
use function preg_replace;
use function rewind;
use function str_replace;
use function stream_get_contents;
use function strlen;
use function trim;

/**
 * A receipt or invoice kept with an expense. The file itself is stored in the database, so
 * it is backed up, exported and isolated per company along with the expense it belongs to.
 * @see \SolidInvoice\ExpenseBundle\Tests\Entity\ExpenseAttachmentTest
 */
#[ORM\Table(name: ExpenseAttachment::TABLE_NAME)]
#[ORM\Entity(repositoryClass: ExpenseAttachmentRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/expenses/{expenseId}/attachments',
            uriVariables: [
                'expenseId' => new Link(fromProperty: 'attachments', fromClass: Expense::class),
            ],
        ),
        new Post(
            uriTemplate: '/expenses/{expenseId}/attachments',
            uriVariables: [
                'expenseId' => new Link(fromProperty: 'attachments', fromClass: Expense::class),
            ],
            // The `attachments` link would otherwise have API Platform deserialize into the
            // expense's existing attachment, so a second post overwrites the first.
            read: false,
            processor: ExpenseAttachmentPersistProcessor::class,
        ),
        new Get(),
        new Delete(),
    ],
    normalizationContext: [
        'groups' => ['expense_attachment_api:read'],
        AbstractObjectNormalizer::SKIP_NULL_VALUES => false,
    ],
    denormalizationContext: [
        'groups' => ['expense_attachment_api:write'],
        AbstractObjectNormalizer::SKIP_NULL_VALUES => false,
    ],
)]
class ExpenseAttachment
{
    final public const string TABLE_NAME = 'expense_attachments';

    final public const int MAX_SIZE = 10 * 1024 * 1024;

    /**
     * Detected from the content, never taken from the client. SVG and HTML are left out on
     * purpose: they can carry script, and receipts are served back to the browser.
     */
    final public const array ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/heic',
        'image/heif',
    ];

    use TimeStampable;
    use CompanyAware;

    #[ORM\Column(name: 'id', type: UlidType::NAME)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UlidGenerator::class)]
    #[Groups(['expense_attachment_api:read', 'expense_api:read'])]
    private ?Ulid $id = null;

    #[ORM\ManyToOne(targetEntity: Expense::class, inversedBy: 'attachments')]
    #[ORM\JoinColumn(name: 'expense_id', nullable: false, onDelete: 'CASCADE')]
    private Expense $expense;

    #[ORM\Column(name: 'file_name', type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Groups(['expense_attachment_api:read', 'expense_attachment_api:write', 'expense_api:read'])]
    private string $fileName = '';

    #[ORM\Column(name: 'mime_type', type: Types::STRING, length: 127)]
    #[Groups(['expense_attachment_api:read', 'expense_api:read'])]
    #[ApiProperty(writable: false)]
    private string $mimeType = '';

    #[ORM\Column(name: 'file_size', type: Types::INTEGER)]
    #[Groups(['expense_attachment_api:read', 'expense_api:read'])]
    #[ApiProperty(writable: false)]
    private int $size = 0;

    /**
     * @var resource|string|null
     */
    #[ORM\Column(name: 'content', type: Types::BLOB)]
    private mixed $content = null;

    public function getId(): ?Ulid
    {
        return $this->id;
    }

    public function getExpense(): ?Expense
    {
        return $this->expense ?? null;
    }

    public function setExpense(Expense $expense): self
    {
        $this->expense = $expense;

        return $this;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): self
    {
        // Only the base name, without control characters or quotes that would break the
        // Content-Disposition header it is sent back in.
        $fileName = basename(str_replace('\\', '/', $fileName));
        $fileName = trim((string) preg_replace('/[\x00-\x1F\x7F"]/u', '', $fileName));

        $this->fileName = mb_substr($fileName, -255);

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getContent(): string
    {
        if (is_resource($this->content)) {
            rewind($this->content);

            return (string) stream_get_contents($this->content);
        }

        return (string) $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        $this->size = strlen($content);
        $this->mimeType = $content === '' ? '' : (string) new finfo(FILEINFO_MIME_TYPE)->buffer($content);

        return $this;
    }

    /**
     * The file, base64-encoded, as the API and the MCP tools receive it.
     */
    #[Groups(['expense_attachment_api:write'])]
    #[SerializedName('content')]
    #[ApiProperty(readable: false, openapiContext: ['type' => 'string', 'format' => 'byte'], jsonSchemaContext: ['type' => 'string', 'contentEncoding' => 'base64'])]
    public function setBase64Content(string $content): self
    {
        $decoded = base64_decode($content, true);

        return $this->setContent($decoded === false ? '' : $decoded);
    }

    #[Assert\Callback]
    public function validateContent(ExecutionContextInterface $context): void
    {
        if ($this->size === 0 || $this->mimeType === '') {
            $context->buildViolation('expense.attachment.empty')
                ->atPath('content')
                ->addViolation();

            return;
        }

        if ($this->size > self::MAX_SIZE) {
            $context->buildViolation('expense.attachment.too_large')
                ->setParameter('{{ limit }}', (string) (self::MAX_SIZE / 1024 / 1024))
                ->atPath('content')
                ->addViolation();
        }

        if (! in_array($this->mimeType, self::ALLOWED_MIME_TYPES, true)) {
            $context->buildViolation('expense.attachment.invalid_type')
                ->atPath('content')
                ->addViolation();
        }
    }
}
