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

namespace SolidInvoice\CatalogBundle\Action;

use Doctrine\Persistence\ManagerRegistry;
use SolidInvoice\CatalogBundle\Entity\CatalogItem;
use SolidInvoice\CatalogBundle\Form\Type\CatalogItemType;
use SolidInvoice\SettingsBundle\SystemConfig;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\RouterInterface;
use function assert;

final readonly class Add
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private RouterInterface $router,
        private ManagerRegistry $doctrine,
        private SystemConfig $config,
    ) {
    }

    /**
     * @return array{form: FormView}|Response
     */
    #[Template('@SolidInvoiceCatalog/Item/form.html.twig')]
    public function __invoke(Request $request): array | Response
    {
        $item = new CatalogItem();
        $item->setCurrencyCode($this->config->getCurrency()->getCode());

        $form = $this->formFactory->create(CatalogItemType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->doctrine->getManager();
            $entityManager->persist($item);
            $entityManager->flush();

            $session = $request->getSession();
            assert($session instanceof Session);
            $session->getFlashBag()->add('success', 'catalog.item.flash.saved');

            return new RedirectResponse($this->router->generate('_catalog_items'));
        }

        return ['form' => $form->createView()];
    }
}
