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

use SolidInvoice\AssistantBundle\Entity\Assistant;
use SolidInvoice\AssistantBundle\Repository\AssistantRepository;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * The company's assistants. With just one, straight to it.
 */
final readonly class Index
{
    public function __construct(
        private AssistantRepository $assistants,
        private RouterInterface $router,
    ) {
    }

    /**
     * @return array{assistants: list<Assistant>}|Response
     */
    #[Template('@SolidInvoiceAssistant/Assistant/index.html.twig')]
    public function __invoke(): array | Response
    {
        $assistants = $this->assistants->findAllByName();

        if (count($assistants) === 1) {
            return new RedirectResponse($this->router->generate('_assistants_overview', ['id' => $assistants[0]->getId()]));
        }

        return ['assistants' => $assistants];
    }
}
