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

namespace SolidInvoice\AssistantBundle\Menu;

use Knp\Menu\ItemInterface;
use SolidInvoice\AssistantBundle\Repository\AssistantRepository;
use SolidInvoice\CoreBundle\Enum\Menu\MenuPriority;
use SolidWorx\Platform\PlatformBundle\Attributes\Menu\MenuBuilder;
use Throwable;

/**
 * One entry per assistant, under its own name (« Jeannine »), or a single « Assistants »
 * entry to add the first one.
 */
final readonly class AssistantMenu
{
    public function __construct(
        private AssistantRepository $assistants,
    ) {
    }

    #[MenuBuilder(name: 'sidebar', priority: MenuPriority::PRIORITY_ASSISTANT->value)]
    public function sidebar(ItemInterface $menu): void
    {
        try {
            $assistants = $this->assistants->findAllByName();
        } catch (Throwable) {
            // No company selected yet, or the table is not migrated: no entry rather than no page.
            $assistants = [];
        }

        if ($assistants === []) {
            $menu->addChild('assistant.menu.main', [
                'route' => '_assistants',
                'extras' => ['icon' => 'robot'],
            ]);

            return;
        }

        foreach ($assistants as $assistant) {
            $menu->addChild('assistant-' . $assistant->getId(), [
                'label' => $assistant->getName(),
                'route' => '_assistants_overview',
                'routeParameters' => ['id' => (string) $assistant->getId()],
                'extras' => [
                    'icon' => 'robot',
                    'translation_domain' => false,
                    // Every tab of the assistant keeps the entry highlighted.
                    'routes' => [
                        ['pattern' => '/^_assistants_/'],
                    ],
                ],
            ]);
        }
    }
}
