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
use SolidInvoice\ExpenseBundle\Entity\Vendor;
use SolidInvoice\ExpenseBundle\Form\Type\VendorType;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\RouterInterface;
use function assert;

final readonly class VendorAdd
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private RouterInterface $router,
        private ManagerRegistry $doctrine
    ) {
    }

    /**
     * @return array{form: FormView}|Response
     */
    #[Template('@SolidInvoiceExpense/Vendor/form.html.twig')]
    public function __invoke(Request $request): array | Response
    {
        $vendor = new Vendor();

        $form = $this->formFactory->create(VendorType::class, $vendor);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->doctrine->getManager();
            $entityManager->persist($vendor);
            $entityManager->flush();

            $session = $request->getSession();
            assert($session instanceof Session);
            $session->getFlashBag()->add('success', 'expense.vendor.flash.saved');

            return new RedirectResponse($this->router->generate('_expense_vendors'));
        }

        return ['form' => $form->createView()];
    }
}
