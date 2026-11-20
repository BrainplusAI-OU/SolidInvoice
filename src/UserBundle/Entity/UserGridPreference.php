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

namespace SolidInvoice\UserBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SolidInvoice\CoreBundle\Export\Attribute\ExportIgnore;
use SolidInvoice\CoreBundle\Traits\Entity\TimeStampable;
use SolidInvoice\UserBundle\Repository\UserGridPreferenceRepository;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;
use function array_values;

/**
 * How one user last left one data grid: hidden columns, sort and page size.
 */
#[ORM\Table(name: UserGridPreference::TABLE_NAME)]
#[ORM\UniqueConstraint(columns: ['user_id', 'grid_name'])]
#[ORM\Entity(repositoryClass: UserGridPreferenceRepository::class)]
#[ExportIgnore]
class UserGridPreference
{
    final public const string TABLE_NAME = 'user_grid_preferences';

    use TimeStampable;

    #[ORM\Column(name: 'id', type: UlidType::NAME)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UlidGenerator::class)]
    private ?Ulid $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'grid_name', type: Types::STRING, length: 125)]
    private string $gridName;

    /**
     * @var list<string>
     */
    #[ORM\Column(name: 'hidden_columns', type: Types::JSON)]
    private array $hiddenColumns = [];

    #[ORM\Column(name: 'sort', type: Types::STRING, length: 255, nullable: true)]
    private ?string $sort = null;

    #[ORM\Column(name: 'per_page', type: Types::INTEGER, nullable: true)]
    private ?int $perPage = null;

    public function __construct(User $user, string $gridName)
    {
        $this->user = $user;
        $this->gridName = $gridName;
    }

    public function getId(): ?Ulid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getGridName(): string
    {
        return $this->gridName;
    }

    /**
     * @return list<string>
     */
    public function getHiddenColumns(): array
    {
        return $this->hiddenColumns;
    }

    /**
     * @param list<string> $hiddenColumns
     */
    public function setHiddenColumns(array $hiddenColumns): self
    {
        $this->hiddenColumns = array_values($hiddenColumns);

        return $this;
    }

    public function getSort(): ?string
    {
        return $this->sort;
    }

    public function setSort(?string $sort): self
    {
        $this->sort = $sort === '' ? null : $sort;

        return $this;
    }

    public function getPerPage(): ?int
    {
        return $this->perPage;
    }

    public function setPerPage(?int $perPage): self
    {
        $this->perPage = $perPage;

        return $this;
    }
}
