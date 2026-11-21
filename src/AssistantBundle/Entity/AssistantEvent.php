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

namespace SolidInvoice\AssistantBundle\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SolidInvoice\AssistantBundle\Enum\EventChannel;
use SolidInvoice\AssistantBundle\Enum\EventDirection;
use SolidInvoice\AssistantBundle\Enum\EventStatus;
use SolidInvoice\AssistantBundle\Enum\EventType;
use SolidInvoice\AssistantBundle\Repository\AssistantEventRepository;
use SolidInvoice\CoreBundle\Traits\Entity\CompanyAware;
use SolidInvoice\CoreBundle\Traits\Entity\TimeStampable;
use SolidInvoice\ExpenseBundle\Entity\Expense;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

/**
 * One line of an assistant's journal.
 *
 * An event with a key is updated, not duplicated, when the bot reports it again: a
 * document starts pending when its summary is posted and becomes processed, rejected or
 * failed under the same key. Each status it goes through is kept in `details.history`.
 */
#[ORM\Table(name: AssistantEvent::TABLE_NAME)]
#[ORM\UniqueConstraint(name: 'assistant_event_key', columns: ['assistant_id', 'event_key'])]
#[ORM\Index(name: 'assistant_event_occurred', columns: ['assistant_id', 'occurred_at'])]
#[ORM\Index(name: 'assistant_event_type_status', columns: ['assistant_id', 'type', 'status'])]
#[ORM\Entity(repositoryClass: AssistantEventRepository::class)]
class AssistantEvent
{
    final public const string TABLE_NAME = 'assistant_events';

    final public const int KEY_LENGTH = 191;

    final public const int SUBJECT_LENGTH = 255;

    use TimeStampable;
    use CompanyAware;

    #[ORM\Column(name: 'id', type: UlidType::NAME)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UlidGenerator::class)]
    private ?Ulid $id = null;

    #[ORM\ManyToOne(targetEntity: Assistant::class)]
    #[ORM\JoinColumn(name: 'assistant_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Assistant $assistant;

    /**
     * The bot's own name for the event, to update it later. 191 characters fit a unique
     * index in utf8mb4 on every MySQL version the application supports.
     */
    #[ORM\Column(name: 'event_key', type: Types::STRING, length: self::KEY_LENGTH, nullable: true)]
    private ?string $eventKey = null;

    #[ORM\Column(name: 'type', type: Types::STRING, length: 20, enumType: EventType::class)]
    private EventType $type = EventType::Message;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 20, enumType: EventStatus::class)]
    private EventStatus $status = EventStatus::Info;

    #[ORM\Column(name: 'channel', type: Types::STRING, length: 20, enumType: EventChannel::class)]
    private EventChannel $channel = EventChannel::Telegram;

    #[ORM\Column(name: 'direction', type: Types::STRING, length: 3, nullable: true, enumType: EventDirection::class)]
    private ?EventDirection $direction = null;

    #[ORM\Column(name: 'occurred_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $occurredAt;

    #[ORM\ManyToOne(targetEntity: AssistantMember::class)]
    #[ORM\JoinColumn(name: 'member_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AssistantMember $member = null;

    /**
     * Who it was, as the bot saw them: kept when the member is deleted, and set for
     * strangers who are not members.
     */
    #[ORM\Column(name: 'member_name', type: Types::STRING, length: 120, nullable: true)]
    private ?string $memberName = null;

    #[ORM\Column(name: 'member_external_id', type: Types::STRING, length: 64, nullable: true)]
    private ?string $memberExternalId = null;

    #[ORM\Column(name: 'subject', type: Types::STRING, length: self::SUBJECT_LENGTH)]
    private string $subject = '';

    #[ORM\Column(name: 'body', type: Types::TEXT, nullable: true)]
    private ?string $body = null;

    /**
     * Free details from the bot: amounts, file names, links (`links`: list of {label, url}),
     * and the status history (`history`: list of {status, at}).
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(name: 'details', type: Types::JSON)]
    private array $details = [];

    #[ORM\ManyToOne(targetEntity: Expense::class)]
    #[ORM\JoinColumn(name: 'expense_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Expense $expense = null;

    public function __construct()
    {
        $this->occurredAt = new DateTimeImmutable();
    }

    public function getId(): ?Ulid
    {
        return $this->id;
    }

    public function getAssistant(): Assistant
    {
        return $this->assistant;
    }

    public function setAssistant(Assistant $assistant): self
    {
        $this->assistant = $assistant;

        return $this;
    }

    public function getEventKey(): ?string
    {
        return $this->eventKey;
    }

    public function setEventKey(?string $eventKey): self
    {
        $this->eventKey = $eventKey;

        return $this;
    }

    public function getType(): EventType
    {
        return $this->type;
    }

    public function setType(EventType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getStatus(): EventStatus
    {
        return $this->status;
    }

    /**
     * Changes the status and notes the change in the history.
     */
    public function setStatus(EventStatus $status, ?DateTimeImmutable $at = null): self
    {
        $history = $this->getHistory();
        $last = $history === [] ? null : $history[array_key_last($history)];

        if ($last === null || $last['status'] !== $status->value) {
            $history[] = ['status' => $status->value, 'at' => ($at ?? new DateTimeImmutable())->format(DATE_ATOM)];
            $this->details['history'] = $history;
        }

        $this->status = $status;

        return $this;
    }

    /**
     * @return list<array{status: string, at: string}>
     */
    public function getHistory(): array
    {
        $history = $this->details['history'] ?? [];

        if (! is_array($history)) {
            return [];
        }

        return array_values(array_filter(
            $history,
            static fn (mixed $row): bool => is_array($row) && is_string($row['status'] ?? null) && is_string($row['at'] ?? null),
        ));
    }

    public function getChannel(): EventChannel
    {
        return $this->channel;
    }

    public function setChannel(EventChannel $channel): self
    {
        $this->channel = $channel;

        return $this;
    }

    public function getDirection(): ?EventDirection
    {
        return $this->direction;
    }

    public function setDirection(?EventDirection $direction): self
    {
        $this->direction = $direction;

        return $this;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(DateTimeImmutable $occurredAt): self
    {
        $this->occurredAt = $occurredAt;

        return $this;
    }

    public function getMember(): ?AssistantMember
    {
        return $this->member;
    }

    public function setMember(?AssistantMember $member): self
    {
        $this->member = $member;

        return $this;
    }

    public function getMemberName(): ?string
    {
        return $this->memberName;
    }

    public function setMemberName(?string $memberName): self
    {
        $this->memberName = $memberName;

        return $this;
    }

    public function getMemberExternalId(): ?string
    {
        return $this->memberExternalId;
    }

    public function setMemberExternalId(?string $memberExternalId): self
    {
        $this->memberExternalId = $memberExternalId;

        return $this;
    }

    /**
     * The member's current name, or the name the bot reported.
     */
    public function getWho(): ?string
    {
        return $this->member?->getName() ?? $this->memberName;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): self
    {
        $this->body = $body;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * @param array<string, mixed> $details
     */
    public function setDetails(array $details): self
    {
        $this->details = $details;

        return $this;
    }

    /**
     * The details a person reads, without the history and links shown on their own.
     *
     * @return array<string, mixed>
     */
    public function getExtraDetails(): array
    {
        return array_diff_key($this->details, ['history' => true, 'links' => true]);
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    public function getLinks(): array
    {
        $links = $this->details['links'] ?? [];

        if (! is_array($links)) {
            return [];
        }

        $valid = [];

        foreach ($links as $link) {
            if (! is_array($link) || ! is_string($link['url'] ?? null)) {
                continue;
            }

            // Only web links: a link from the bot is shown as a clickable anchor.
            if (! preg_match('#^https?://#i', $link['url'])) {
                continue;
            }

            $valid[] = ['label' => is_string($link['label'] ?? null) && $link['label'] !== '' ? $link['label'] : $link['url'], 'url' => $link['url']];
        }

        return $valid;
    }

    public function getExpense(): ?Expense
    {
        return $this->expense;
    }

    public function setExpense(?Expense $expense): self
    {
        $this->expense = $expense;

        return $this;
    }
}
