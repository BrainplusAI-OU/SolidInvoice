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

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SolidInvoice\AssistantBundle\Enum\EventChannel;
use SolidInvoice\AssistantBundle\Repository\AssistantRepository;
use SolidInvoice\CoreBundle\Traits\Entity\CompanyAware;
use SolidInvoice\CoreBundle\Traits\Entity\TimeStampable;
use Stringable;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A bot that works for the company, such as an accounting bot on Telegram.
 *
 * The bot itself runs elsewhere. This record gives it a name in the menu, collects what it
 * reports and holds the people allowed to use it.
 */
#[ORM\Table(name: Assistant::TABLE_NAME)]
#[ORM\Entity(repositoryClass: AssistantRepository::class)]
class Assistant implements Stringable
{
    final public const string TABLE_NAME = 'assistants';

    use TimeStampable;
    use CompanyAware;

    #[ORM\Column(name: 'id', type: UlidType::NAME)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UlidGenerator::class)]
    private ?Ulid $id = null;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 60)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 60)]
    private string $name = '';

    #[ORM\Column(name: 'description', type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Where people talk to the bot.
     */
    #[ORM\Column(name: 'channel', type: Types::STRING, length: 20, enumType: EventChannel::class)]
    private EventChannel $channel = EventChannel::Telegram;

    /**
     * The mailbox the bot reads, if it reads one.
     */
    #[ORM\Column(name: 'inbox_email', type: Types::STRING, length: 180, nullable: true)]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private ?string $inboxEmail = null;

    public function getId(): ?Ulid
    {
        return $this->id;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
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

    public function getInboxEmail(): ?string
    {
        return $this->inboxEmail;
    }

    public function setInboxEmail(?string $inboxEmail): self
    {
        $this->inboxEmail = $inboxEmail === null || trim($inboxEmail) === '' ? null : trim($inboxEmail);

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
