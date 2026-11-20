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

namespace SolidInvoice\UserBundle\Repository;

use Doctrine\Persistence\ManagerRegistry;
use SolidInvoice\UserBundle\Entity\User;
use SolidInvoice\UserBundle\Entity\UserGridPreference;
use SolidWorx\Platform\PlatformBundle\Repository\EntityRepository;

/**
 * @extends EntityRepository<UserGridPreference>
 */
class UserGridPreferenceRepository extends EntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserGridPreference::class);
    }

    public function findForGrid(User $user, string $gridName): ?UserGridPreference
    {
        return $this->findOneBy(['user' => $user, 'gridName' => $gridName]);
    }

    /**
     * @param list<string> $hiddenColumns
     */
    public function saveForGrid(User $user, string $gridName, array $hiddenColumns, string $sort, int $perPage): UserGridPreference
    {
        $preference = $this->findForGrid($user, $gridName) ?? new UserGridPreference($user, $gridName);

        $preference
            ->setHiddenColumns($hiddenColumns)
            ->setSort($sort)
            ->setPerPage($perPage);

        $this->getEntityManager()->persist($preference);
        $this->getEntityManager()->flush();

        return $preference;
    }
}
