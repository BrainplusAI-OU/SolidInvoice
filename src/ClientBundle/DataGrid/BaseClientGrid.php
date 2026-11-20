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

namespace SolidInvoice\ClientBundle\DataGrid;

use Brick\Math\BigNumber;
use Doctrine\ORM\EntityManagerInterface;
use Money\Currency;
use Money\Money;
use Override;
use SolidInvoice\ClientBundle\Entity\Client;
use SolidInvoice\ClientBundle\Repository\ClientRepository;
use SolidInvoice\DataGridBundle\Grid;
use SolidInvoice\DataGridBundle\GridBuilder\Action\Action;
use SolidInvoice\DataGridBundle\GridBuilder\Action\EditAction;
use SolidInvoice\DataGridBundle\GridBuilder\Action\ViewAction;
use SolidInvoice\DataGridBundle\GridBuilder\Batch\BatchAction;
use SolidInvoice\DataGridBundle\GridBuilder\Column\Column;
use SolidInvoice\DataGridBundle\GridBuilder\Column\CurrencyColumn;
use SolidInvoice\DataGridBundle\GridBuilder\Column\DateTimeColumn;
use SolidInvoice\DataGridBundle\GridBuilder\Column\MoneyColumn;
use SolidInvoice\DataGridBundle\GridBuilder\Column\StringColumn;
use SolidInvoice\DataGridBundle\GridBuilder\Column\UrlColumn;
use SolidInvoice\DataGridBundle\GridBuilder\Filter\ChoiceFilter;
use SolidInvoice\DataGridBundle\GridBuilder\Filter\DateRangeFilter;
use SolidInvoice\DataGridBundle\GridBuilder\Query;
use SolidInvoice\DataGridBundle\Source\ORMSource;
use SolidInvoice\InvoiceBundle\Entity\Invoice;
use SolidInvoice\InvoiceBundle\Enum\InvoiceStatus;
use Symfony\Component\Intl\Currencies;
use Symfony\Component\Translation\TranslatableMessage;
use function array_filter;
use function array_values;
use function count;
use function in_array;
use function reset;

abstract class BaseClientGrid extends Grid
{
    public function __construct(
        private readonly string $locale
    ) {
    }

    /**
     * @return Column[]
     */
    #[Override]
    public function columns(): array
    {
        return [
            StringColumn::new('name')
                ->label('label.name'),
            UrlColumn::new('website')
                ->label('client.grid.website'),
            CurrencyColumn::new('currencyCode')
                ->label(new TranslatableMessage('client.grid.currency'))
                ->filter(new ChoiceFilter('currencyCode', Currencies::getNames($this->locale))),
            MoneyColumn::new('total')
                ->label(new TranslatableMessage('client.grid.total_balance'))
                ->sortable(false)
                ->searchable(false)
                ->formatValue(static fn ($value, Client $client): Money | array => self::sumByCurrency(
                    array_filter(
                        $client->getInvoices()->toArray(),
                        static fn (Invoice $invoice): bool => in_array($invoice->getStatus(), [InvoiceStatus::Paid, InvoiceStatus::Pending, InvoiceStatus::Overdue], true),
                    ),
                    static fn (Invoice $invoice): BigNumber => $invoice->getTotal(),
                    $client,
                )),
            MoneyColumn::new('outstanding')
                ->label(new TranslatableMessage('client.grid.outstanding_balance'))
                ->sortable(false)
                ->searchable(false)
                ->formatValue(static fn ($value, Client $client): Money | array => self::sumByCurrency(
                    $client->getOutstandingInvoices(),
                    static fn (Invoice $invoice): BigNumber => $invoice->getBalance(),
                    $client,
                )),
            DateTimeColumn::new('created')
                ->label('label.created')
                ->format('d F Y')
                ->filter(new DateRangeFilter('created')),
        ];
    }

    /**
     * Sums invoice amounts per currency. Each invoice is counted in its own currency
     * (frozen once issued, else the client's), never in the system currency: a euro
     * client's balance was shown as the same figure in baht. A single currency gives
     * one Money; several give one Money per currency, which the money formatter and the
     * export both understand.
     *
     * @param iterable<Invoice>               $invoices
     * @param callable(Invoice): BigNumber    $amount
     *
     * @return Money|list<Money>
     */
    public static function sumByCurrency(iterable $invoices, callable $amount, Client $client): Money | array
    {
        /** @var array<string, Money> $sums */
        $sums = [];

        foreach ($invoices as $invoice) {
            $code = $invoice->resolveCurrencyCode() ?? $client->getCurrencyCode();

            if ($code === null) {
                continue;
            }

            $money = new Money((string) $amount($invoice)->toBigInteger(), new Currency($code));
            $sums[$code] = isset($sums[$code]) ? $sums[$code]->add($money) : $money;
        }

        if ($sums === []) {
            $code = $client->getCurrencyCode();

            return $code === null ? [] : new Money(0, new Currency($code));
        }

        return count($sums) === 1 ? reset($sums) : array_values($sums);
    }

    /**
     * @return Action[]
     */
    #[Override]
    public function actions(): array
    {
        return [
            ViewAction::new('_clients_view', ['id' => 'id']),
            EditAction::new('_clients_edit', ['id' => 'id']),
        ];
    }

    public function entityFQCN(): string
    {
        return Client::class;
    }

    #[Override]
    public function batchActions(): iterable
    {
        yield BatchAction::new('action.delete')
            ->icon('trash')
            ->color('danger')
            ->action(static function (ClientRepository $repository, array $selectedItems): void {
                $repository->deleteClients($selectedItems);
            });
    }

    #[Override]
    public function query(EntityManagerInterface $entityManager, Query $query): Query
    {
        $query->getQueryBuilder()
            ->orderBy(ORMSource::ALIAS . '.created', 'ASC');

        return $query;
    }
}
