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
use SolidInvoice\AssistantBundle\Repository\AssistantMemberRepository;
use SolidInvoice\CoreBundle\Traits\Entity\CompanyAware;
use SolidInvoice\CoreBundle\Traits\Entity\TimeStampable;
use Stringable;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Someone allowed to use an assistant, known by their id on the bot's channel.
 *
 * The bot reads the active members from the API on each message: removing someone here
 * (or turning them off) shuts them out at once, without touching the bot.
 */
#[ORM\Table(name: AssistantMember::TABLE_NAME)]
#[ORM\UniqueConstraint(name: 'assistant_member_external_id', columns: ['assistant_id', 'external_id'])]
#[ORM\Entity(repositoryClass: AssistantMemberRepository::class)]
#[UniqueEntity(fields: ['assistant', 'externalId'], message: 'assistant.member.form.external_id_taken', errorPath: 'externalId')]
class AssistantMember implements Stringable
{
    final public const string TABLE_NAME = 'assistant_members';

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
     * The person's id on the channel: a Telegram user id, a LINE user id.
     */
    #[ORM\Column(name: 'external_id', type: Types::STRING, length: 64)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    private string $externalId = '';

    #[ORM\Column(name: 'name', type: Types::STRING, length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name = '';

    /**
     * Number plates of the vehicles this person fills up, so fuel receipts can be told apart.
     */
    #[ORM\Column(name: 'vehicle_plates', type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $vehiclePlates = null;

    /**
     * The language the bot answers in, as a two-letter code. Empty until the person chooses.
     */
    #[ORM\Column(name: 'language', type: Types::STRING, length: 8, nullable: true)]
    #[Assert\Length(max: 8)]
    private ?string $language = null;

    /**
     * Who the expenses this person records are booked to, when it is not the person
     * themselves.
     */
    #[ORM\Column(name: 'payer', type: Types::STRING, length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $payer = null;

    #[ORM\Column(name: 'active', type: Types::BOOLEAN)]
    private bool $active = true;

    #[ORM\Column(name: 'last_seen_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column(name: 'note', type: Types::TEXT, nullable: true)]
    private ?string $note = null;

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

    /**
     * For the grid's edit link, which reads a plain property of the row.
     */
    public function getAssistantId(): string
    {
        return (string) $this->assistant->getId();
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): self
    {
        $this->externalId = trim($externalId);

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getVehiclePlates(): ?string
    {
        return $this->vehiclePlates;
    }

    public function setVehiclePlates(?string $vehiclePlates): self
    {
        $this->vehiclePlates = self::blankToNull($vehiclePlates);

        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): self
    {
        $language = self::blankToNull($language);
        $this->language = $language === null ? null : strtolower($language);

        return $this;
    }

    public function getPayer(): ?string
    {
        return $this->payer;
    }

    public function setPayer(?string $payer): self
    {
        $this->payer = self::blankToNull($payer);

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getLastSeenAt(): ?DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(?DateTimeImmutable $lastSeenAt): self
    {
        $this->lastSeenAt = $lastSeenAt;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = self::blankToNull($note);

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }

    private static function blankToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
