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

namespace SolidInvoice\CatalogBundle\Repository;

use Doctrine\Persistence\ManagerRegistry;
use SolidInvoice\CatalogBundle\Entity\CatalogItem;
use SolidWorx\Platform\PlatformBundle\Repository\EntityRepository;
use Symfony\Component\Uid\Ulid;

/**
 * @extends EntityRepository<CatalogItem>
 */
class CatalogItemRepository extends EntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CatalogItem::class);
    }

    /**
     * @return list<CatalogItem>
     */
    public function findAllByName(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }

    /**
     * Lines already filled from a deleted item keep what was copied onto them.
     *
     * @param list<Ulid|string> $ids
     */
    public function delete(array $ids): void
    {
        $em = $this->getEntityManager();

        foreach ($ids as $id) {
            $item = $this->find($id instanceof Ulid ? $id : Ulid::fromString($id));

            if ($item instanceof CatalogItem) {
                $em->remove($item);
            }
        }

        $em->flush();
    }
}
