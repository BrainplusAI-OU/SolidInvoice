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

namespace SolidInvoice\AssistantBundle\DataGrid;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use SolidInvoice\AssistantBundle\Entity\AssistantMember;
use SolidInvoice\AssistantBundle\Repository\AssistantMemberRepository;
use SolidInvoice\DataGridBundle\Attributes\AsDataGrid;
use SolidInvoice\DataGridBundle\Grid;
use SolidInvoice\DataGridBundle\GridBuilder\Action\EditAction;
use SolidInvoice\DataGridBundle\GridBuilder\Batch\BatchAction;
use SolidInvoice\DataGridBundle\GridBuilder\Column\Column;
use SolidInvoice\DataGridBundle\GridBuilder\Column\DateTimeColumn;
use SolidInvoice\DataGridBundle\GridBuilder\Column\StatusColumn;
use SolidInvoice\DataGridBundle\GridBuilder\Column\StringColumn;
use SolidInvoice\DataGridBundle\GridBuilder\Query;
use SolidInvoice\DataGridBundle\Source\ORMSource;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Translation\TranslatableMessage;
use function htmlspecialchars;

/**
 * The people allowed to use an assistant. The page passes `assistant_id`.
 */
#[AsDataGrid(name: 'assistant_member_grid', title: 'Members')]
final class AssistantMemberGrid extends Grid
{
    public function entityFQCN(): string
    {
        return AssistantMember::class;
    }

    /**
     * @return Column[]
     */
    #[Override]
    public function columns(): array
    {
        $escape = static fn (?string $value): string => htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return [
            StringColumn::new('name')
                ->label('assistant.member.form.name')
                ->formatValue($escape),
            StringColumn::new('externalId')
                ->label('assistant.member.form.external_id')
                ->formatValue($escape),
            StatusColumn::new('active')
                ->label('label.status')
                ->searchable(false)
                ->statusMap(['active' => 'success', 'inactive' => 'secondary'])
                ->formatValue(static fn (bool $active): string => $active ? 'active' : 'inactive'),
            StringColumn::new('payer')
                ->label('assistant.member.form.payer')
                ->formatValue($escape),
            StringColumn::new('language')
                ->label('assistant.member.form.language')
                ->searchable(false)
                ->formatValue($escape),
            StringColumn::new('vehiclePlates')
                ->label('assistant.member.form.vehicle_plates')
                ->sortable(false)
                ->formatValue($escape),
            DateTimeColumn::new('lastSeenAt')
                ->label('assistant.member.grid.last_seen')
                ->format('d M Y H:i'),
        ];
    }

    #[Override]
    public function query(EntityManagerInterface $entityManager, Query $query): Query
    {
        $query->getQueryBuilder()
            ->andWhere(ORMSource::ALIAS . '.assistant = :assistant')
            ->setParameter('assistant', $this->context['assistant_id'] ?? '', UlidType::NAME);

        return $query;
    }

    #[Override]
    public function batchActions(): iterable
    {
        return [
            BatchAction::new('assistant.member.grid.deactivate')
                ->icon('user-off')
                ->action(static function (AssistantMemberRepository $repository, array $selectedItems): void {
                    $repository->setActive($selectedItems, false);
                }),
            BatchAction::new('assistant.member.grid.activate')
                ->icon('user-check')
                ->action(static function (AssistantMemberRepository $repository, array $selectedItems): void {
                    $repository->setActive($selectedItems, true);
                }),
            BatchAction::new('action.delete')
                ->icon('trash')
                ->color('danger')
                ->confirmMessage('assistant.member.grid.delete_confirm')
                ->action(static function (AssistantMemberRepository $repository, array $selectedItems): void {
                    $repository->delete($selectedItems);
                }),
        ];
    }

    /**
     * @return EditAction[]
     */
    #[Override]
    public function actions(): array
    {
        return [
            EditAction::new('_assistants_members_edit', ['id' => 'assistantId', 'member' => 'id']),
        ];
    }

    #[Override]
    public function getEmptyTitle(): TranslatableMessage
    {
        return new TranslatableMessage('assistant.member.grid.empty_title');
    }

    #[Override]
    public function getEmptyDescription(): TranslatableMessage
    {
        return new TranslatableMessage('assistant.member.grid.empty_description');
    }
}
