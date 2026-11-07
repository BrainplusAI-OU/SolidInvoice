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

namespace SolidInvoice\CoreBundle\Generator\BillingIdGenerator;

use Brick\Math\BigInteger;
use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use function assert;

/**
 * Numbers the next document one above the highest existing number written as
 * prefix + digits + suffix, keeping that number's zero padding (2026-0041 → 2026-0042).
 *
 * The digits are matched and compared here rather than cast in SQL: MySQL, MariaDB and
 * SQLite cast "2026-0001" to 2026 and PostgreSQL's to_number() reads it as 20260001, so
 * a SQL MAX() over numbers that do not follow the configured format produced "2027" or
 * "20260002" depending on the database. A number with another prefix (last year's, say)
 * does not count either, which is what lets a new prefix start again from 1.
 *
 * @see \SolidInvoice\CoreBundle\Tests\Generator\BillingIdGenerator\AutoIncrementIdGeneratorTest
 */
#[AsTaggedItem('auto_increment')]
final readonly class AutoIncrementIdGenerator implements IdGeneratorInterface
{
    public function __construct(
        private ManagerRegistry $registry
    ) {
    }

    public static function getName(): string
    {
        return 'auto_increment';
    }

    public function getConfigurationFormType(): ?string
    {
        return null;
    }

    public function generate(object $entity, array $options): string
    {
        $prefix = (string) ($options['prefix'] ?? '');
        $suffix = (string) ($options['suffix'] ?? '');

        $highest = null;

        foreach ($this->existingNumbers($entity, $options['field']) as $number) {
            $sequence = $this->sequenceOf((string) $number, $prefix, $suffix);

            if (null !== $sequence && (null === $highest || $this->isHigher($sequence, $highest))) {
                $highest = $sequence;
            }
        }

        if (null === $highest) {
            return '1';
        }

        return str_pad((string) BigInteger::of($highest)->plus(1), strlen($highest), '0', STR_PAD_LEFT);
    }

    /**
     * @return list<mixed>
     */
    private function existingNumbers(object $entity, string $field): array
    {
        $em = $this->registry->getManagerForClass($entity::class);
        assert($em instanceof EntityManager);

        $filters = $em->getFilters();

        $filters->disable('archivable');

        try {
            return $this->registry
                ->getRepository($entity::class)
                ->createQueryBuilder('e')
                ->select('e.' . $field)
                ->getQuery()
                ->getSingleColumnResult();
        } finally {
            $filters->enable('archivable');
        }
    }

    /**
     * The digits between the prefix and the suffix, or null when the number is not written
     * in the configured format.
     */
    private function sequenceOf(string $number, string $prefix, string $suffix): ?string
    {
        $prefixLength = strlen($prefix);
        $suffixLength = strlen($suffix);

        if (! str_starts_with($number, $prefix) || ! str_ends_with($number, $suffix) || strlen($number) <= $prefixLength + $suffixLength) {
            return null;
        }

        $digits = substr($number, $prefixLength, strlen($number) - $prefixLength - $suffixLength);

        return ctype_digit($digits) ? $digits : null;
    }

    /**
     * Numeric comparison; between equal values the zero-padded one wins, so its width is kept.
     */
    private function isHigher(string $candidate, string $highest): bool
    {
        $comparison = BigInteger::of($candidate)->compareTo(BigInteger::of($highest));

        return $comparison > 0 || (0 === $comparison && strlen($candidate) > strlen($highest));
    }
}
