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

namespace SolidInvoice\AssistantBundle\Repository;

use DateTimeImmutable;
use Doctrine\Persistence\ManagerRegistry;
use SolidInvoice\AssistantBundle\Entity\Assistant;
use SolidInvoice\AssistantBundle\Entity\AssistantEvent;
use SolidInvoice\AssistantBundle\Enum\EventStatus;
use SolidInvoice\AssistantBundle\Enum\EventType;
use SolidWorx\Platform\PlatformBundle\Repository\EntityRepository;
use Symfony\Bridge\Doctrine\Types\UlidType;

/**
 * @extends EntityRepository<AssistantEvent>
 */
class AssistantEventRepository extends EntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssistantEvent::class);
    }

    public function findOneByKey(Assistant $assistant, string $key): ?AssistantEvent
    {
        return $this->findOneBy(['assistant' => $assistant, 'eventKey' => $key]);
    }

    /**
     * How many events of each type ended in each status since a date.
     *
     * @return array<string, array<string, int>> type => status => count
     */
    public function countByTypeAndStatus(Assistant $assistant, DateTimeImmutable $since): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('e.type AS type', 'e.status AS status', 'COUNT(e.id) AS total')
            ->andWhere('e.assistant = :assistant')
            ->andWhere('e.occurredAt >= :since')
            ->setParameter('assistant', $assistant->getId(), UlidType::NAME)
            ->setParameter('since', $since)
            ->groupBy('e.type', 'e.status')
            ->getQuery()
            ->getArrayResult();

        $counts = [];

        foreach ($rows as $row) {
            $type = $row['type'] instanceof EventType ? $row['type']->value : (string) $row['type'];
            $status = $row['status'] instanceof EventStatus ? $row['status']->value : (string) $row['status'];
            $counts[$type][$status] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Documents waiting for someone, and anything that failed, newest first.
     *
     * @return list<AssistantEvent>
     */
    public function findNeedingAttention(Assistant $assistant, DateTimeImmutable $since, int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.assistant = :assistant')
            ->andWhere('e.occurredAt >= :since')
            ->andWhere('(e.type = :document AND e.status = :pending) OR e.status = :failed')
            ->setParameter('assistant', $assistant->getId(), UlidType::NAME)
            ->setParameter('since', $since)
            ->setParameter('document', EventType::Document->value)
            ->setParameter('pending', EventStatus::Pending->value)
            ->setParameter('failed', EventStatus::Failed->value)
            ->orderBy('e.occurredAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<AssistantEvent>
     */
    public function findLatest(Assistant $assistant, int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.assistant = :assistant')
            ->setParameter('assistant', $assistant->getId(), UlidType::NAME)
            ->orderBy('e.occurredAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findLastOccurredAt(Assistant $assistant): ?DateTimeImmutable
    {
        $latest = $this->findLatest($assistant, 1);

        return $latest === [] ? null : $latest[0]->getOccurredAt();
    }
}
