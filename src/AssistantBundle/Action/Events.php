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

use SolidInvoice\AssistantBundle\DataGrid\AssistantEventGrid;
use SolidInvoice\AssistantBundle\Entity\Assistant;
use Symfony\Bridge\Twig\Attribute\Template;

/**
 * The journal, whole (activity) or narrowed to conversations or e-mails.
 */
final readonly class Events
{
    /**
     * @return array{assistant: Assistant, tab: string, view: string}
     */
    #[Template('@SolidInvoiceAssistant/Assistant/events.html.twig')]
    public function __invoke(Assistant $assistant, string $view = AssistantEventGrid::VIEW_ACTIVITY): array
    {
        return ['assistant' => $assistant, 'tab' => $view, 'view' => $view];
    }
}
