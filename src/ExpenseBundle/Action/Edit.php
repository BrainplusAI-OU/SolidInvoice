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

namespace SolidInvoice\ExpenseBundle\Action;

use SolidInvoice\ExpenseBundle\Entity\Expense;
use SolidInvoice\ExpenseBundle\Form\Type\ExpenseType;
use SolidInvoice\ExpenseBundle\Manager\ExpenseManager;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\RouterInterface;
use function assert;

final readonly class Edit
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private RouterInterface $router,
        private ExpenseManager $manager,
    ) {
    }

    /**
     * @return array{form: FormView, expense: Expense}|Response
     */
    #[Template('@SolidInvoiceExpense/Expense/form.html.twig')]
    public function __invoke(Request $request, Expense $expense): array | Response
    {
        $form = $this->formFactory->create(ExpenseType::class, $expense);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->manager->save($expense, $form->get('receipts')->getData() ?? []);

            $session = $request->getSession();
            assert($session instanceof Session);
            $session->getFlashBag()->add('success', 'expense.flash.saved');

            return new RedirectResponse($this->router->generate('_expenses_index'));
        }

        return ['form' => $form->createView(), 'expense' => $expense];
    }
}
