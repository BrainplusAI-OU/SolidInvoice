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
use SolidInvoice\AssistantBundle\Entity\AssistantMember;
use SolidInvoice\AssistantBundle\Form\Type\AssistantMemberType;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use function assert;

final readonly class MemberEdit
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
    #[Template('@SolidInvoiceAssistant/Assistant/member_form.html.twig')]
    public function __invoke(
        Request $request,
        Assistant $assistant,
        #[MapEntity(id: 'member')]
        AssistantMember $member,
    ): array | Response {
        if (! $member->getAssistant()->getId()?->equals($assistant->getId())) {
            throw new NotFoundHttpException();
        }

        $form = $this->formFactory->create(AssistantMemberType::class, $member);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $session = $request->getSession();
            assert($session instanceof Session);
            $session->getFlashBag()->add('success', 'assistant.member.flash.saved');

            return new RedirectResponse($this->router->generate('_assistants_members', ['id' => $assistant->getId()]));
        }

        return ['form' => $form->createView(), 'assistant' => $assistant, 'tab' => 'members'];
    }
}
