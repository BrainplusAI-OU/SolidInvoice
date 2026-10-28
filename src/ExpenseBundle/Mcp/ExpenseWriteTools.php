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

use Brick\Math\BigInteger;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;
use SolidInvoice\ClientBundle\Entity\Client;
use SolidInvoice\ClientBundle\Repository\ClientRepository;
use SolidInvoice\ExpenseBundle\Entity\Expense;
use SolidInvoice\ExpenseBundle\Entity\ExpenseAttachment;
use SolidInvoice\ExpenseBundle\Repository\ExpenseCategoryRepository;
use SolidInvoice\ExpenseBundle\Repository\ExpenseRepository;
use SolidInvoice\ExpenseBundle\Repository\VendorRepository;
use SolidInvoice\InvoiceBundle\Entity\Invoice;
use SolidInvoice\InvoiceBundle\Repository\InvoiceRepository;
use SolidInvoice\McpBundle\Mcp\Attribute\McpScopeRequired;
use SolidInvoice\McpBundle\Mcp\McpScopeGuard;
use SolidInvoice\McpBundle\Mcp\Tool\EntityNormalizer;
use SolidInvoice\McpBundle\Mcp\Tool\UlidParser;
use SolidInvoice\McpBundle\Security\McpScope;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class ExpenseWriteTools
{
    public function __construct(
        private ExpenseRepository $expenseRepository,
        private VendorRepository $vendorRepository,
        private ExpenseCategoryRepository $categoryRepository,
        private ClientRepository $clientRepository,
        private InvoiceRepository $invoiceRepository,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private EntityNormalizer $normalizer,
        private McpScopeGuard $scopeGuard,
    ) {
    }

    /**
     * Record an expense. Vendor and category are matched by name, ignoring case, and created when
     * they do not exist yet.
     *
     * @param string      $date           Date of the expense (YYYY-MM-DD)
     * @param string      $description    What was bought
     * @param int         $amount         Total paid, tax included, in minor units (e.g. cents)
     * @param string      $currency       ISO 4217 currency code
     * @param int         $tax_amount     Part of the amount that is tax, in minor units
     * @param string|null $vendor         Vendor name
     * @param string|null $category       Category name
     * @param string|null $payment_method How it was paid (e.g. card, bank transfer, cash)
     * @param string|null $reference      Number of the vendor's invoice or receipt
     * @param string|null $notes          Free-form notes
     * @param string|null $client_id      Client ULID, when the expense was incurred for a client
     * @param bool        $billable       Whether the expense is to be re-billed to that client
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'create_expense', description: 'Record an expense (date, description, amount in minor units, currency, optional tax part, vendor and category by name, which are created if new).')]
    #[McpScopeRequired(McpScope::Write)]
    public function createExpense(
        string $date,
        string $description,
        int $amount,
        string $currency,
        int $tax_amount = 0,
        ?string $vendor = null,
        ?string $category = null,
        ?string $payment_method = null,
        ?string $reference = null,
        ?string $notes = null,
        ?string $client_id = null,
        bool $billable = false,
    ): array {
        $this->scopeGuard->require(McpScope::Write);

        $expense = new Expense();
        $expense->setDate(ExpenseReadTools::date($date, 'date'));
        $expense->setDescription($description);
        $expense->setAmount(BigInteger::of($amount));
        $expense->setCurrencyCode($currency);
        $expense->setTaxAmount(BigInteger::of($tax_amount));
        $expense->setPaymentMethod($payment_method);
        $expense->setReference($reference);
        $expense->setNotes($notes);
        $expense->setBillable($billable);

        if ($vendor !== null && trim($vendor) !== '') {
            $expense->setVendor($this->vendorRepository->findOrCreate($vendor));
        }

        if ($category !== null && trim($category) !== '') {
            $expense->setCategory($this->categoryRepository->findOrCreate($category));
        }

        if ($client_id !== null) {
            $expense->setClient($this->client($client_id));
        }

        $this->validate($expense);

        $this->entityManager->persist($expense);
        $this->entityManager->flush();

        return $this->normalizer->normalize($expense);
    }

    /**
     * Update an expense. Pass only the fields to change; an empty string clears vendor, category
     * or client.
     *
     * @param string      $expense_id     Expense ULID
     * @param string|null $date           Date of the expense (YYYY-MM-DD)
     * @param string|null $description    What was bought
     * @param int|null    $amount         Total paid, tax included, in minor units
     * @param string|null $currency       ISO 4217 currency code
     * @param int|null    $tax_amount     Part of the amount that is tax, in minor units
     * @param string|null $vendor         Vendor name
     * @param string|null $category       Category name
     * @param string|null $payment_method How it was paid
     * @param string|null $reference      Number of the vendor's invoice or receipt
     * @param string|null $notes          Free-form notes
     * @param string|null $client_id      Client ULID
     * @param bool|null   $billable       Whether the expense is to be re-billed to the client
     * @param string|null $invoice_id     ULID of the invoice the expense was re-billed on
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'update_expense', description: 'Update an expense. Pass only the fields you want to change; an empty string clears vendor, category, client or invoice.')]
    #[McpScopeRequired(McpScope::Write)]
    public function updateExpense(
        string $expense_id,
        ?string $date = null,
        ?string $description = null,
        ?int $amount = null,
        ?string $currency = null,
        ?int $tax_amount = null,
        ?string $vendor = null,
        ?string $category = null,
        ?string $payment_method = null,
        ?string $reference = null,
        ?string $notes = null,
        ?string $client_id = null,
        ?bool $billable = null,
        ?string $invoice_id = null,
    ): array {
        $this->scopeGuard->require(McpScope::Write);

        $expense = $this->expense($expense_id);

        if ($date !== null) {
            $expense->setDate(ExpenseReadTools::date($date, 'date'));
        }

        if ($description !== null) {
            $expense->setDescription($description);
        }

        if ($amount !== null) {
            $expense->setAmount(BigInteger::of($amount));
        }

        if ($currency !== null) {
            $expense->setCurrencyCode($currency);
        }

        if ($tax_amount !== null) {
            $expense->setTaxAmount(BigInteger::of($tax_amount));
        }

        if ($vendor !== null) {
            $expense->setVendor(trim($vendor) === '' ? null : $this->vendorRepository->findOrCreate($vendor));
        }

        if ($category !== null) {
            $expense->setCategory(trim($category) === '' ? null : $this->categoryRepository->findOrCreate($category));
        }

        if ($payment_method !== null) {
            $expense->setPaymentMethod($payment_method === '' ? null : $payment_method);
        }

        if ($reference !== null) {
            $expense->setReference($reference === '' ? null : $reference);
        }

        if ($notes !== null) {
            $expense->setNotes($notes === '' ? null : $notes);
        }

        if ($client_id !== null) {
            $expense->setClient($client_id === '' ? null : $this->client($client_id));
        }

        if ($billable !== null) {
            $expense->setBillable($billable);
        }

        if ($invoice_id !== null) {
            $expense->setInvoice($invoice_id === '' ? null : $this->invoice($invoice_id));
        }

        $this->validate($expense);

        $this->entityManager->flush();

        return $this->normalizer->normalize($expense);
    }

    /**
     * @param string $expense_id Expense ULID
     *
     * @return array{deleted: string}
     */
    #[McpTool(name: 'delete_expense', description: 'Delete an expense and its attached receipts.')]
    #[McpScopeRequired(McpScope::Write)]
    public function deleteExpense(string $expense_id): array
    {
        $this->scopeGuard->require(McpScope::Write);

        $expense = $this->expense($expense_id);

        $this->entityManager->remove($expense);
        $this->entityManager->flush();

        return ['deleted' => $expense_id];
    }

    /**
     * Attach a receipt (PDF or image, up to 10 MB) to an expense.
     *
     * @param string $expense_id     Expense ULID
     * @param string $file_name      File name to keep, e.g. receipt.jpg
     * @param string $content_base64 The file, base64-encoded
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'add_expense_attachment', description: 'Attach a receipt (PDF or image, base64-encoded, up to 10 MB) to an expense.')]
    #[McpScopeRequired(McpScope::Write)]
    public function addExpenseAttachment(string $expense_id, string $file_name, string $content_base64): array
    {
        $this->scopeGuard->require(McpScope::Write);

        $expense = $this->expense($expense_id);

        $attachment = new ExpenseAttachment();
        $attachment->setFileName($file_name);
        $attachment->setBase64Content($content_base64);

        $this->validate($attachment);

        $expense->addAttachment($attachment);
        $this->entityManager->flush();

        return $this->normalizer->normalize($expense);
    }

    private function expense(string $id): Expense
    {
        $expense = $this->expenseRepository->findOneBy(['id' => UlidParser::parse($id, 'expense_id')]);

        if (! $expense instanceof Expense) {
            throw new ToolCallException(sprintf('Expense %s not found.', $id));
        }

        return $expense;
    }

    private function client(string $id): Client
    {
        $client = $this->clientRepository->findOneBy(['id' => UlidParser::parse($id, 'client_id')]);

        if (! $client instanceof Client) {
            throw new ToolCallException(sprintf('Client %s not found.', $id));
        }

        return $client;
    }

    private function invoice(string $id): Invoice
    {
        $invoice = $this->invoiceRepository->findOneBy(['id' => UlidParser::parse($id, 'invoice_id')]);

        if (! $invoice instanceof Invoice) {
            throw new ToolCallException(sprintf('Invoice %s not found.', $id));
        }

        return $invoice;
    }

    private function validate(object $entity): void
    {
        $violations = $this->validator->validate($entity);

        if (count($violations) === 0) {
            return;
        }

        $messages = [];

        foreach ($violations as $violation) {
            $messages[] = sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage());
        }

        throw new ToolCallException(implode(' ', $messages));
    }
}
