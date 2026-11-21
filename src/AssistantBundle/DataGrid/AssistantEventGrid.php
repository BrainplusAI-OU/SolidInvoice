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
use SolidInvoice\AssistantBundle\Entity\AssistantEvent;
use SolidInvoice\AssistantBundle\Enum\EventChannel;
use SolidInvoice\AssistantBundle\Enum\EventDirection;
use SolidInvoice\AssistantBundle\Enum\EventStatus;
use SolidInvoice\AssistantBundle\Enum\EventType;
use SolidInvoice\DataGridBundle\Attributes\AsDataGrid;
use SolidInvoice\DataGridBundle\Grid;
use SolidInvoice\DataGridBundle\GridBuilder\Column\Column;
use SolidInvoice\DataGridBundle\GridBuilder\Column\DateTimeColumn;
use SolidInvoice\DataGridBundle\GridBuilder\Column\StatusColumn;
use SolidInvoice\DataGridBundle\GridBuilder\Column\StringColumn;
use SolidInvoice\DataGridBundle\GridBuilder\Filter\ChoiceFilter;
use SolidInvoice\DataGridBundle\GridBuilder\Filter\DateRangeFilter;
use SolidInvoice\DataGridBundle\GridBuilder\Query;
use SolidInvoice\DataGridBundle\Source\ORMSource;
use SolidInvoice\ExpenseBundle\Entity\Expense;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\TranslatableMessage;
use function htmlspecialchars;
use function mb_strimwidth;
use function sprintf;

/**
 * An assistant's journal. The page passes `assistant_id`, and `view` to narrow it to the
 * conversations (messages) or the e-mails.
 *
 * Everything in a row was written by the bot, so every text is escaped here: the grid
 * prints cells as HTML.
 */
#[AsDataGrid(name: 'assistant_event_grid', title: 'Activity')]
final class AssistantEventGrid extends Grid
{
    public const string VIEW_ACTIVITY = 'activity';

    public const string VIEW_CONVERSATIONS = 'conversations';

    public const string VIEW_EMAILS = 'emails';

    public function __construct(
        private readonly RouterInterface $router,
    ) {
    }

    public function entityFQCN(): string
    {
        return AssistantEvent::class;
    }

    /**
     * @return Column[]
     */
    #[Override]
    public function columns(): array
    {
        return [
            DateTimeColumn::new('occurredAt')
                ->label('assistant.event.grid.when')
                ->format('d M Y H:i')
                ->filter(new DateRangeFilter('occurredAt')),
            StringColumn::new('type')
                ->label('assistant.event.grid.type')
                ->searchable(false)
                ->formatValue(static fn (EventType $type): TranslatableMessage => new TranslatableMessage($type->getLabel()))
                ->filter(ChoiceFilter::new('type', EventType::choices())->multiple()),
            StatusColumn::new('status')
                ->label('label.status')
                ->searchable(false)
                ->statusMap(EventStatus::variants())
                ->formatValue(static fn (EventStatus $status): string => $status->value)
                ->filter(ChoiceFilter::new('status', EventStatus::choices())->multiple()),
            StringColumn::new('memberName')
                ->label('assistant.event.grid.who')
                ->formatValue(static fn (?string $name, AssistantEvent $event): string => self::escape($event->getWho() ?? '-')),
            StringColumn::new('subject')
                ->label('assistant.event.grid.subject')
                ->sortable(false)
                ->formatValue(static function (string $subject, AssistantEvent $event): string {
                    $arrow = match ($event->getDirection()) {
                        EventDirection::In => '→ ',
                        EventDirection::Out => '← ',
                        null => '',
                    };

                    return $arrow . self::escape(mb_strimwidth($subject, 0, 90, '…'));
                }),
            StringColumn::new('channel')
                ->label('assistant.event.grid.channel')
                ->searchable(false)
                ->formatValue(static fn (EventChannel $channel): TranslatableMessage => new TranslatableMessage($channel->getLabel()))
                ->filter(ChoiceFilter::new('channel', EventChannel::choices())->multiple()),
            StringColumn::new('expense')
                ->label('assistant.event.grid.expense')
                ->searchable(false)
                ->sortable(false)
                ->formatValue(fn (?Expense $expense): string => $expense instanceof Expense && $expense->getId() !== null
                    ? sprintf('<a href="%s">%s</a>', self::escape($this->router->generate('_expenses_edit', ['id' => $expense->getId()])), self::escape(mb_strimwidth(trim((string) $expense) !== '' ? (string) $expense : (string) $expense->getId(), 0, 40, '…')))
                    : ''),
        ];
    }

    #[Override]
    public function query(EntityManagerInterface $entityManager, Query $query): Query
    {
        $queryBuilder = $query->getQueryBuilder();

        // Never the whole company's journal: a page always says whose it is.
        $queryBuilder
            ->andWhere(ORMSource::ALIAS . '.assistant = :assistant')
            ->setParameter('assistant', $this->context['assistant_id'] ?? '', UlidType::NAME);

        $type = match ($this->context['view'] ?? self::VIEW_ACTIVITY) {
            self::VIEW_CONVERSATIONS => EventType::Message,
            self::VIEW_EMAILS => EventType::Email,
            default => null,
        };

        if ($type instanceof EventType) {
            $queryBuilder
                ->andWhere(ORMSource::ALIAS . '.type = :view_type')
                ->setParameter('view_type', $type->value);
        }

        $queryBuilder
            ->orderBy(ORMSource::ALIAS . '.occurredAt', 'DESC')
            ->addOrderBy(ORMSource::ALIAS . '.id', 'DESC');

        return $query;
    }

    #[Override]
    public function hasRowDetails(): bool
    {
        return true;
    }

    #[Override]
    public function getRowDetailTemplate(): string
    {
        return '@SolidInvoiceAssistant/Assistant/_event_details.html.twig';
    }

    #[Override]
    public function getEmptyTitle(): TranslatableMessage
    {
        return new TranslatableMessage('assistant.event.grid.empty_title');
    }

    #[Override]
    public function getEmptyDescription(): TranslatableMessage
    {
        return new TranslatableMessage('assistant.event.grid.empty_description');
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
