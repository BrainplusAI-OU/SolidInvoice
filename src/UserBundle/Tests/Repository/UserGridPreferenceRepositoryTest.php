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

namespace SolidInvoice\UserBundle\Tests\Repository;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use SolidInvoice\CoreBundle\Test\Traits\FakerTestTrait;
use SolidInvoice\InstallBundle\Test\EnsureApplicationInstalled;
use SolidInvoice\UserBundle\Entity\User;
use SolidInvoice\UserBundle\Entity\UserGridPreference;
use SolidInvoice\UserBundle\Repository\UserGridPreferenceRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(UserGridPreferenceRepository::class)]
#[CoversClass(UserGridPreference::class)]
final class UserGridPreferenceRepositoryTest extends KernelTestCase
{
    use EnsureApplicationInstalled;
    use FakerTestTrait;

    private UserGridPreferenceRepository $repository;

    private EntityManagerInterface $em;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $em = self::getContainer()->get('doctrine')->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;
        $repository = self::getContainer()->get(UserGridPreferenceRepository::class);
        self::assertInstanceOf(UserGridPreferenceRepository::class, $repository);
        $this->repository = $repository;

        $this->user = new User();
        $this->user->setEmail($this->getFaker()->email())
            ->setPassword($this->getFaker()->password())
            ->addCompany($this->company);

        $this->em->persist($this->user);
        $this->em->flush();
    }

    public function testNothingIsSavedForANewGrid(): void
    {
        self::assertNull($this->repository->findForGrid($this->user, 'invoice_grid'));
    }

    public function testSavedChoicesComeBackForTheSameUserAndGrid(): void
    {
        $this->repository->saveForGrid($this->user, 'invoice_grid', ['client', 'due'], 'invoiceId,desc', 25);
        $this->em->clear();

        $user = $this->em->find(User::class, $this->user->getId());
        self::assertInstanceOf(User::class, $user);
        $preference = $this->repository->findForGrid($user, 'invoice_grid');

        self::assertInstanceOf(UserGridPreference::class, $preference);
        self::assertSame(['client', 'due'], $preference->getHiddenColumns());
        self::assertSame('invoiceId,desc', $preference->getSort());
        self::assertSame(25, $preference->getPerPage());
        self::assertNull($this->repository->findForGrid($user, 'expense_grid'));
    }

    public function testSavingAgainUpdatesTheSameRow(): void
    {
        $this->repository->saveForGrid($this->user, 'expense_grid', ['vendor'], '', 10);
        $preference = $this->repository->saveForGrid($this->user, 'expense_grid', [], 'date,asc', 50);

        self::assertCount(1, $this->repository->findBy(['user' => $this->user, 'gridName' => 'expense_grid']));
        self::assertSame([], $preference->getHiddenColumns());
        self::assertSame('date,asc', $preference->getSort());
        self::assertSame(50, $preference->getPerPage());
    }

    public function testAnEmptySortIsStoredAsNoSort(): void
    {
        $preference = $this->repository->saveForGrid($this->user, 'client_grid', [], '', 10);

        self::assertNull($preference->getSort());
    }
}
