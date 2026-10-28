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

namespace SolidInvoice\ExpenseBundle\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use SolidInvoice\ExpenseBundle\Entity\Expense;
use SolidInvoice\ExpenseBundle\Entity\ExpenseAttachment;
use SolidInvoice\ExpenseBundle\Repository\ExpenseRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProcessorInterface<ExpenseAttachment, ExpenseAttachment> */
final readonly class ExpenseAttachmentPersistProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $decorated
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $decorated,
        private ExpenseRepository $expenseRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof ExpenseAttachment && isset($uriVariables['expenseId'])) {
            // findOneBy (not find) so the CompanyFilter applies and another company's expense stays unreachable.
            $expense = $this->expenseRepository->findOneBy(['id' => $uriVariables['expenseId']]);

            if (! $expense instanceof Expense) {
                throw new NotFoundHttpException(sprintf('Expense "%s" not found.', $uriVariables['expenseId']));
            }

            $expense->addAttachment($data);
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }
}
