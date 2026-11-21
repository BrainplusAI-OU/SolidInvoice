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

use SolidInvoice\AssistantBundle\Action\Add;
use SolidInvoice\AssistantBundle\Action\Events;
use SolidInvoice\AssistantBundle\Action\Index;
use SolidInvoice\AssistantBundle\Action\MemberAdd;
use SolidInvoice\AssistantBundle\Action\MemberEdit;
use SolidInvoice\AssistantBundle\Action\Members;
use SolidInvoice\AssistantBundle\Action\Overview;
use SolidInvoice\AssistantBundle\Action\Settings;
use SolidInvoice\AssistantBundle\DataGrid\AssistantEventGrid;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routingConfigurator): void {
    $ulid = ['id' => '[0-9A-HJKMNP-TV-Z]{26}', 'member' => '[0-9A-HJKMNP-TV-Z]{26}'];

    $routingConfigurator
        ->add('_assistants', '/')
        ->controller(Index::class);

    $routingConfigurator
        ->add('_assistants_add', '/add')
        ->controller(Add::class);

    $routingConfigurator
        ->add('_assistants_overview', '/{id}')
        ->controller(Overview::class)
        ->requirements($ulid);

    foreach ([AssistantEventGrid::VIEW_ACTIVITY, AssistantEventGrid::VIEW_CONVERSATIONS, AssistantEventGrid::VIEW_EMAILS] as $view) {
        $routingConfigurator
            ->add('_assistants_' . $view, '/{id}/' . $view)
            ->controller(Events::class)
            ->defaults(['view' => $view])
            ->requirements($ulid);
    }

    $routingConfigurator
        ->add('_assistants_members', '/{id}/members')
        ->controller(Members::class)
        ->requirements($ulid);

    $routingConfigurator
        ->add('_assistants_members_add', '/{id}/members/add')
        ->controller(MemberAdd::class)
        ->requirements($ulid);

    $routingConfigurator
        ->add('_assistants_members_edit', '/{id}/members/{member}/edit')
        ->controller(MemberEdit::class)
        ->requirements($ulid);

    $routingConfigurator
        ->add('_assistants_settings', '/{id}/settings')
        ->controller(Settings::class)
        ->requirements($ulid);
};
