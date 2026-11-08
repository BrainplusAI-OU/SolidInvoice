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

namespace SolidInvoice\ExpenseBundle\Repository;

use Brick\Math\BigInteger;
use Brick\Math\BigNumber;
use DateTimeInterface;
use Doctrine\Persistence\ManagerRegistry;
use SolidInvoice\ExpenseBundle\Entity\Expense;
use SolidInvoice\ExpenseBundle\Enum\ExpenseGrouping;
use SolidWorx\Platform\PlatformBundle\Repository\EntityRepository;
use Symfony\Component\Uid\Ulid;
use function ksort;

/**
 * @extends EntityRepository<Expense>
 */
class ExpenseRepository extends EntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Expense::class);
    }

    /**
     * @param list<Ulid|string> $ids
     */
    public function delete(array $ids): void
    {
        $em = $this->getEntityManager();

        foreach ($ids as $id) {
            $expense = $this->findOneBy(['id' => $id instanceof Ulid ? $id : Ulid::fromString($id)]);

            if ($expense instanceof Expense) {
                $em->remove($expense);
            }
        }

        $em->flush();
    }

    /**
     * Totals in the minor unit, per group and per currency, over expenses dated within the
     * range (both ends inclusive). Currencies are never added together.
     *
     * @return array<string, array<string, array{amount: BigNumber, tax: BigNumber, count: int}>> group label => currency => totals
     */
    public function summarise(?DateTimeInterface $from, ?DateTimeInterface $to, ExpenseGrouping $grouping): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select('e.date', 'e.currencyCode', 'e.amount', 'e.taxAmount', 'v.name AS vendor', 'c.name AS category')
            ->leftJoin('e.vendor', 'v')
            ->leftJoin('e.category', 'c');

        if ($from instanceof DateTimeInterface) {
            $qb->andWhere('e.date >= :from')->setParameter('from', $from->format('Y-m-d'));
        }

        if ($to instanceof DateTimeInterface) {
            $qb->andWhere('e.date <= :to')->setParameter('to', $to->format('Y-m-d'));
        }

        $totals = [];

        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $label = match ($grouping) {
                ExpenseGrouping::Month => $row['date']->format('Y-m'),
                ExpenseGrouping::Category => (string) ($row['category'] ?? ''),
                ExpenseGrouping::Vendor => (string) ($row['vendor'] ?? ''),
            };
            $currency = (string) $row['currencyCode'];

            $current = $totals[$label][$currency] ?? ['amount' => BigInteger::zero(), 'tax' => BigInteger::zero(), 'count' => 0];

            $totals[$label][$currency] = [
                'amount' => $current['amount']->plus($row['amount']),
                'tax' => $current['tax']->plus($row['taxAmount']),
                'count' => $current['count'] + 1,
            ];
        }

        ksort($totals);

        return $totals;
    }
}
