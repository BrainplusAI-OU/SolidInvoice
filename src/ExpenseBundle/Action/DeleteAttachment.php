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

use Doctrine\Persistence\ManagerRegistry;
use SolidInvoice\ExpenseBundle\Entity\Expense;
use SolidInvoice\ExpenseBundle\Entity\ExpenseAttachment;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use function assert;

final readonly class DeleteAttachment
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private RouterInterface $router,
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request, ExpenseAttachment $attachment): RedirectResponse
    {
        $token = new CsrfToken('expense_attachment_delete_' . $attachment->getId(), (string) $request->request->get('_token'));

        if (! $this->csrfTokenManager->isTokenValid($token)) {
            throw new AccessDeniedHttpException();
        }

        $expense = $attachment->getExpense();
        assert($expense instanceof Expense);

        $expense->removeAttachment($attachment);
        $this->doctrine->getManager()->flush();

        $session = $request->getSession();
        assert($session instanceof Session);
        $session->getFlashBag()->add('success', 'expense.attachment.flash.deleted');

        return new RedirectResponse($this->router->generate('_expenses_edit', ['id' => $expense->getId()]));
    }
}
