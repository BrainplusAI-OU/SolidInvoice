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

namespace SolidInvoice\AssistantBundle\Action;

use Doctrine\ORM\EntityManagerInterface;
use SolidInvoice\AssistantBundle\Entity\Assistant;
use SolidInvoice\AssistantBundle\Form\Type\AssistantType;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\RouterInterface;
use function assert;

/**
 * The assistant's name and channel, and how to connect the bot to the API.
 */
final readonly class Settings
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private RouterInterface $router,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{form: FormView, assistant: Assistant, tab: string}|Response
     */
    #[Template('@SolidInvoiceAssistant/Assistant/settings.html.twig')]
    public function __invoke(Request $request, Assistant $assistant): array | Response
    {
        $form = $this->formFactory->create(AssistantType::class, $assistant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $session = $request->getSession();
            assert($session instanceof Session);
            $session->getFlashBag()->add('success', 'assistant.flash.saved');

            return new RedirectResponse($this->router->generate('_assistants_settings', ['id' => $assistant->getId()]));
        }

        return ['form' => $form->createView(), 'assistant' => $assistant, 'tab' => 'settings'];
    }
}
