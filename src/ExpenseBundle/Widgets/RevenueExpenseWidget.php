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

namespace SolidInvoice\ExpenseBundle\Widgets;

use Brick\Math\BigInteger;
use Brick\Math\BigNumber;
use Brick\Math\Exception\MathException;
use Carbon\CarbonImmutable;
use DateMalformedStringException;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\Exception\ORMException;
use IntlDateFormatter;
use Locale;
use Money\Currency;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SolidInvoice\DashboardBundle\Widgets\WidgetInterface;
use SolidInvoice\ExpenseBundle\Enum\ExpenseGrouping;
use SolidInvoice\ExpenseBundle\Repository\ExpenseRepository;
use SolidInvoice\MoneyBundle\Currency\CurrencyScale;
use SolidInvoice\PaymentBundle\Repository\PaymentRepository;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

/**
 * What came in against what went out, month by month, per currency.
 */
final readonly class RevenueExpenseWidget implements WidgetInterface
{
    private const int MONTHS = 12;

    private const array COLORS = [
        'revenue' => 'rgb(46, 150, 58)',
        'expenses' => 'rgb(239, 68, 68)',
    ];

    public function __construct(
        private PaymentRepository $paymentRepository,
        private ExpenseRepository $expenseRepository,
        private ChartBuilderInterface $chartBuilder,
        private CurrencyScale $currencyScale,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $now = CarbonImmutable::now();

        try {
            $revenue = $this->paymentRepository->getRevenueByMonthGrouped(self::MONTHS);
            $expenses = $this->expenseByMonth($now);
            $months = $this->months($now);
            $currencies = $this->currencies($revenue, $expenses);
            $datasets = $this->datasets($revenue, $expenses, $currencies, $months);
            $totals = $this->totals($revenue, $expenses, $currencies, $months);
        } catch (DBALException | ORMException | MathException | DateMalformedStringException | RuntimeException $e) {
            $this->logger->error('Unable to load the revenue and expenses chart data', ['exception' => $e]);

            return ['chart' => null, 'hasError' => true, 'hasData' => false, 'totals' => [], 'months' => self::MONTHS];
        }

        $chart = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $chart->setData([
            'labels' => array_map(
                static fn (CarbonImmutable $month): string => (string) IntlDateFormatter::formatObject($month, 'MMM y', Locale::getDefault()),
                $months,
            ),
            'datasets' => $datasets,
        ]);
        $chart->setOptions([
            'responsive' => true,
            'maintainAspectRatio' => false,
            'interaction' => ['intersect' => false, 'mode' => 'index'],
            'plugins' => ['legend' => ['display' => true, 'position' => 'top']],
            'scales' => [
                'x' => ['grid' => ['display' => false], 'ticks' => ['color' => '#64748b']],
                'y' => ['beginAtZero' => true, 'grid' => ['color' => 'rgba(0, 0, 0, 0.05)'], 'ticks' => ['color' => '#64748b']],
            ],
        ]);

        return [
            'chart' => $chart,
            'hasError' => false,
            'hasData' => $revenue !== [] || $expenses !== [],
            'totals' => $totals,
            'months' => self::MONTHS,
        ];
    }

    public function getTemplate(): string
    {
        return '@SolidInvoiceExpense/Widget/revenue_expense.html.twig';
    }

    /**
     * @return array<string, array<string, BigNumber>> month (Y-m) => currency => amount
     */
    private function expenseByMonth(CarbonImmutable $now): array
    {
        $summary = $this->expenseRepository->summarise($now->startOfMonth()->subMonths(self::MONTHS - 1), $now, ExpenseGrouping::Month);

        return array_map(
            static fn (array $currencies): array => array_map(static fn (array $total): BigNumber => $total['amount'], $currencies),
            $summary,
        );
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function months(CarbonImmutable $now): array
    {
        $months = [];

        for ($i = self::MONTHS - 1; $i >= 0; --$i) {
            $months[] = $now->startOfMonth()->subMonths($i);
        }

        return $months;
    }

    /**
     * @param array<string, array<string, BigNumber>> ...$series
     *
     * @return list<string>
     */
    private function currencies(array ...$series): array
    {
        $currencies = [];

        foreach ($series as $byMonth) {
            foreach ($byMonth as $byCurrency) {
                foreach (array_keys($byCurrency) as $currency) {
                    $currencies[(string) $currency] = true;
                }
            }
        }

        return array_keys($currencies);
    }

    /**
     * @param array<string, array<string, BigNumber>> $revenue
     * @param array<string, array<string, BigNumber>> $expenses
     * @param list<string> $currencies
     * @param list<CarbonImmutable> $months
     *
     * @return list<array<string, mixed>>
     */
    private function datasets(array $revenue, array $expenses, array $currencies, array $months): array
    {
        $datasets = [];

        foreach ($currencies as $code) {
            $currency = new Currency($code);

            $series = [
                'revenue' => [$revenue, $this->translator->trans('expense.dashboard.revenue', ['%currency%' => $code])],
                'expenses' => [$expenses, $this->translator->trans('expense.dashboard.expenses', ['%currency%' => $code])],
            ];

            foreach ($series as $kind => [$values, $label]) {
                $datasets[] = [
                    'label' => $label,
                    'data' => array_map(
                        fn (CarbonImmutable $month): float => isset($values[$month->format('Y-m')][$code])
                            ? $this->currencyScale->toMajorUnit($values[$month->format('Y-m')][$code], $currency)
                            : 0.0,
                        $months,
                    ),
                    'backgroundColor' => self::COLORS[$kind],
                    'borderRadius' => 4,
                ];
            }
        }

        return $datasets;
    }

    /**
     * @param array<string, array<string, BigNumber>> $revenue
     * @param array<string, array<string, BigNumber>> $expenses
     * @param list<string> $currencies
     * @param list<CarbonImmutable> $months
     *
     * @return list<array{currency: string, revenue: BigNumber, expenses: BigNumber, net: BigNumber}>
     */
    private function totals(array $revenue, array $expenses, array $currencies, array $months): array
    {
        $totals = [];

        foreach ($currencies as $code) {
            $in = BigInteger::zero();
            $out = BigInteger::zero();

            foreach ($months as $month) {
                $key = $month->format('Y-m');
                $in = $in->plus($revenue[$key][$code] ?? 0);
                $out = $out->plus($expenses[$key][$code] ?? 0);
            }

            $totals[] = ['currency' => $code, 'revenue' => $in, 'expenses' => $out, 'net' => $in->minus($out)];
        }

        return $totals;
    }
}
