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

use Doctrine\Persistence\ManagerRegistry;
use SolidInvoice\ExpenseBundle\Entity\ExpenseCategory;
use SolidWorx\Platform\PlatformBundle\Repository\EntityRepository;
use Symfony\Component\Uid\Ulid;

/**
 * @extends EntityRepository<ExpenseCategory>
 */
class ExpenseCategoryRepository extends EntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExpenseCategory::class);
    }

    /**
     * The category with this name, ignoring case, created when there is none yet.
     */
    public function findOrCreate(string $name): ExpenseCategory
    {
        $name = trim($name);

        $existing = $this->createQueryBuilder('e')
            ->where('LOWER(e.name) = LOWER(:name)')
            ->setParameter('name', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existing instanceof ExpenseCategory) {
            return $existing;
        }

        $entity = new ExpenseCategory();
        $entity->setName($name);
        $this->getEntityManager()->persist($entity);

        return $entity;
    }

    /**
     * Expenses that pointed at a deleted record keep their other details; the foreign key
     * sets the reference to null.
     *
     * @param list<Ulid|string> $ids
     */
    public function delete(array $ids): void
    {
        $em = $this->getEntityManager();

        foreach ($ids as $id) {
            $entity = $this->findOneBy(['id' => $id instanceof Ulid ? $id : Ulid::fromString($id)]);

            if ($entity instanceof ExpenseCategory) {
                $em->remove($entity);
            }
        }

        $em->flush();
    }
}
