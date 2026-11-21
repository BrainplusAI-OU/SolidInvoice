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

namespace SolidInvoice\AssistantBundle\Action;

use DateTimeImmutable;
use SolidInvoice\AssistantBundle\Entity\Assistant;
use SolidInvoice\AssistantBundle\Entity\AssistantEvent;
use SolidInvoice\AssistantBundle\Enum\EventStatus;
use SolidInvoice\AssistantBundle\Enum\EventType;
use SolidInvoice\AssistantBundle\Repository\AssistantEventRepository;
use SolidInvoice\AssistantBundle\Repository\AssistantMemberRepository;
use Symfony\Bridge\Twig\Attribute\Template;

/**
 * The last 30 days at a glance: documents by outcome, what waits for someone, the latest
 * exchanges.
 */
final readonly class Overview
{
    public const int DAYS = 30;

    public function __construct(
        private AssistantEventRepository $events,
        private AssistantMemberRepository $members,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[Template('@SolidInvoiceAssistant/Assistant/overview.html.twig')]
    public function __invoke(Assistant $assistant): array
    {
        $since = new DateTimeImmutable('-' . self::DAYS . ' days');
        $counts = $this->events->countByTypeAndStatus($assistant, $since);

        $documents = [];

        foreach ([EventStatus::Processed, EventStatus::Pending, EventStatus::Rejected, EventStatus::Failed] as $status) {
            $documents[$status->value] = $counts[EventType::Document->value][$status->value] ?? 0;
        }

        $total = static fn (EventType $type): int => array_sum($counts[$type->value] ?? []);

        return [
            'assistant' => $assistant,
            'tab' => 'overview',
            'days' => self::DAYS,
            'documents' => $documents,
            'messages' => $total(EventType::Message),
            'emails' => $total(EventType::Email),
            'denied' => $total(EventType::Access),
            'members' => count($this->members->findActive($assistant)),
            'attention' => $this->events->findNeedingAttention($assistant, $since),
            'latest' => $this->events->findLatest($assistant, 12),
            'lastActivity' => $this->events->findLastOccurredAt($assistant),
            'statuses' => EventStatus::cases(),
            'nothing' => array_sum(array_map(array_sum(...), $counts)) === 0,
            'eventClass' => AssistantEvent::class,
        ];
    }
}
