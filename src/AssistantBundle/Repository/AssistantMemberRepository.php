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

use Doctrine\Persistence\ManagerRegistry;
use SolidInvoice\AssistantBundle\Entity\Assistant;
use SolidInvoice\AssistantBundle\Entity\AssistantMember;
use SolidWorx\Platform\PlatformBundle\Repository\EntityRepository;
use Symfony\Component\Uid\Ulid;

/**
 * @extends EntityRepository<AssistantMember>
 */
class AssistantMemberRepository extends EntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssistantMember::class);
    }

    public function findOneByExternalId(Assistant $assistant, string $externalId): ?AssistantMember
    {
        return $this->findOneBy(['assistant' => $assistant, 'externalId' => trim($externalId)]);
    }

    /**
     * In the order they were added, so a list the bot keeps elsewhere lines up with it.
     *
     * @return list<AssistantMember>
     */
    public function findActive(Assistant $assistant): array
    {
        return $this->findBy(['assistant' => $assistant, 'active' => true], ['created' => 'ASC', 'id' => 'ASC']);
    }

    /**
     * @return list<AssistantMember>
     */
    public function findByAssistant(Assistant $assistant): array
    {
        return $this->findBy(['assistant' => $assistant], ['name' => 'ASC']);
    }

    /**
     * Past events keep the name the bot reported.
     *
     * @param list<Ulid|string> $ids
     */
    public function delete(array $ids): void
    {
        $em = $this->getEntityManager();

        foreach ($ids as $id) {
            $member = $this->find($id instanceof Ulid ? $id : Ulid::fromString($id));

            if ($member instanceof AssistantMember) {
                $em->remove($member);
            }
        }

        $em->flush();
    }

    /**
     * @param list<Ulid|string> $ids
     */
    public function setActive(array $ids, bool $active): void
    {
        foreach ($ids as $id) {
            $member = $this->find($id instanceof Ulid ? $id : Ulid::fromString($id));

            if ($member instanceof AssistantMember) {
                $member->setActive($active);
            }
        }

        $this->getEntityManager()->flush();
    }
}
