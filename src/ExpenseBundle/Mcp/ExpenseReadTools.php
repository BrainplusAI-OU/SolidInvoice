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

namespace SolidInvoice\ExpenseBundle\Mcp;

use Carbon\CarbonImmutable;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;
use SolidInvoice\ClientBundle\Entity\Client;
use SolidInvoice\ClientBundle\Repository\ClientRepository;
use SolidInvoice\ExpenseBundle\Entity\Expense;
use SolidInvoice\ExpenseBundle\Enum\ExpenseGrouping;
use SolidInvoice\ExpenseBundle\Repository\ExpenseCategoryRepository;
use SolidInvoice\ExpenseBundle\Repository\ExpenseRepository;
use SolidInvoice\ExpenseBundle\Repository\VendorRepository;
use SolidInvoice\McpBundle\Mcp\Attribute\McpScopeRequired;
use SolidInvoice\McpBundle\Mcp\McpScopeGuard;
use SolidInvoice\McpBundle\Mcp\Tool\EntityNormalizer;
use SolidInvoice\McpBundle\Mcp\Tool\UlidParser;
use SolidInvoice\McpBundle\Security\McpScope;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Throwable;

final readonly class ExpenseReadTools
{
    public function __construct(
        private ExpenseRepository $expenseRepository,
        private VendorRepository $vendorRepository,
        private ExpenseCategoryRepository $categoryRepository,
        private ClientRepository $clientRepository,
        private EntityNormalizer $normalizer,
        private McpScopeGuard $scopeGuard,
    ) {
    }

    /**
     * List expenses, most recent first. Amounts are in minor units (e.g. cents) of the expense's currency.
     *
     * @param string|null $from        Earliest expense date, inclusive (YYYY-MM-DD)
     * @param string|null $to          Latest expense date, inclusive (YYYY-MM-DD)
     * @param string|null $vendor_id   Only expenses from this vendor (ULID)
     * @param string|null $category_id Only expenses in this category (ULID)
     * @param string|null $client_id   Only expenses incurred for this client (ULID)
     * @param string|null $search      Text to look for in the description or reference
     * @param int         $limit       Max rows (1..100)
     *
     * @return array{results: list<array<string, mixed>>, count: int}
     */
    #[McpTool(name: 'list_expenses', description: 'List expenses, most recent first, optionally filtered by date range, vendor, category, client or text. Amounts are in minor units.')]
    #[McpScopeRequired(McpScope::Read)]
    public function listExpenses(
        ?string $from = null,
        ?string $to = null,
        ?string $vendor_id = null,
        ?string $category_id = null,
        ?string $client_id = null,
        ?string $search = null,
        int $limit = 25,
    ): array {
        $this->scopeGuard->require(McpScope::Read);
        $limit = max(1, min($limit, 100));

        $qb = $this->expenseRepository->createQueryBuilder('e')
            ->orderBy('e.date', 'DESC')
            ->addOrderBy('e.created', 'DESC')
            ->setMaxResults($limit);

        if ($from !== null) {
            $qb->andWhere('e.date >= :from')->setParameter('from', self::date($from, 'from')->format('Y-m-d'));
        }

        if ($to !== null) {
            $qb->andWhere('e.date <= :to')->setParameter('to', self::date($to, 'to')->format('Y-m-d'));
        }

        if ($vendor_id !== null) {
            $qb->andWhere('e.vendor = :vendor')->setParameter('vendor', UlidParser::parse($vendor_id, 'vendor_id'), UlidType::NAME);
        }

        if ($category_id !== null) {
            $qb->andWhere('e.category = :category')->setParameter('category', UlidParser::parse($category_id, 'category_id'), UlidType::NAME);
        }

        if ($client_id !== null) {
            $client = $this->clientRepository->findOneBy(['id' => UlidParser::parse($client_id, 'client_id')]);

            if (! $client instanceof Client) {
                throw new ToolCallException(sprintf('Client %s not found.', $client_id));
            }

            $qb->andWhere('e.client = :client')->setParameter('client', $client->getId(), UlidType::NAME);
        }

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('LOWER(e.description) LIKE :search OR LOWER(e.reference) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower(trim($search)) . '%');
        }

        $results = $this->normalizer->normalizeMany($qb->getQuery()->getResult());

        return ['results' => $results, 'count' => count($results)];
    }

    /**
     * @param string $expense_id Expense ULID
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'get_expense', description: 'Get one expense with its vendor, category and the list of attached receipts.')]
    #[McpScopeRequired(McpScope::Read)]
    public function getExpense(string $expense_id): array
    {
        $this->scopeGuard->require(McpScope::Read);

        $expense = $this->expenseRepository->findOneBy(['id' => UlidParser::parse($expense_id, 'expense_id')]);

        if (! $expense instanceof Expense) {
            throw new ToolCallException(sprintf('Expense %s not found.', $expense_id));
        }

        return $this->normalizer->normalize($expense);
    }

    /**
     * Totals of expenses over a period, broken down by month, category or vendor. Totals are in
     * minor units and kept per currency, since amounts in different currencies are never added up.
     *
     * @param string|null $from     Earliest expense date, inclusive (YYYY-MM-DD)
     * @param string|null $to       Latest expense date, inclusive (YYYY-MM-DD)
     * @param string      $group_by One of: month, category, vendor
     *
     * @return array{group_by: string, groups: list<array<string, mixed>>}
     */
    #[McpTool(name: 'summarize_expenses', description: 'Expense totals over a period, grouped by month, category or vendor, per currency, in minor units.')]
    #[McpScopeRequired(McpScope::Read)]
    public function summarizeExpenses(?string $from = null, ?string $to = null, string $group_by = 'month'): array
    {
        $this->scopeGuard->require(McpScope::Read);

        $grouping = ExpenseGrouping::tryFrom($group_by) ?? throw new ToolCallException(sprintf(
            'Invalid group_by "%s". Use one of: %s.',
            $group_by,
            implode(', ', array_column(ExpenseGrouping::cases(), 'value')),
        ));

        $totals = $this->expenseRepository->summarise(
            $from === null ? null : self::date($from, 'from'),
            $to === null ? null : self::date($to, 'to'),
            $grouping,
        );

        $groups = [];

        foreach ($totals as $label => $currencies) {
            foreach ($currencies as $currency => $total) {
                $groups[] = [
                    'group' => $label === '' ? null : $label,
                    'currency' => $currency,
                    'amount' => (string) $total['amount'],
                    'tax_amount' => (string) $total['tax'],
                    'count' => $total['count'],
                ];
            }
        }

        return ['group_by' => $grouping->value, 'groups' => $groups];
    }

    /**
     * @return array{results: list<array<string, mixed>>, count: int}
     */
    #[McpTool(name: 'list_vendors', description: 'List the vendors expenses can be recorded against.')]
    #[McpScopeRequired(McpScope::Read)]
    public function listVendors(): array
    {
        $this->scopeGuard->require(McpScope::Read);

        $results = $this->normalizer->normalizeMany($this->vendorRepository->findBy([], ['name' => 'ASC']));

        return ['results' => $results, 'count' => count($results)];
    }

    /**
     * @return array{results: list<array<string, mixed>>, count: int}
     */
    #[McpTool(name: 'list_expense_categories', description: 'List the categories expenses can be filed under.')]
    #[McpScopeRequired(McpScope::Read)]
    public function listExpenseCategories(): array
    {
        $this->scopeGuard->require(McpScope::Read);

        $results = $this->normalizer->normalizeMany($this->categoryRepository->findBy([], ['name' => 'ASC']));

        return ['results' => $results, 'count' => count($results)];
    }

    public static function date(string $value, string $field): CarbonImmutable
    {
        try {
            return new CarbonImmutable($value, 'UTC');
        } catch (Throwable) {
            throw new ToolCallException(sprintf('%s "%s" is not a date (YYYY-MM-DD).', $field, $value));
        }
    }
}
