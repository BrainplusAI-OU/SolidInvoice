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

use SolidInvoice\AssistantBundle\Action\Api\CreateAssistant;
use SolidInvoice\AssistantBundle\Action\Api\ImportMembers;
use SolidInvoice\AssistantBundle\Action\Api\ListAssistants;
use SolidInvoice\AssistantBundle\Action\Api\ListMembers;
use SolidInvoice\AssistantBundle\Action\Api\MemberConfig;
use SolidInvoice\AssistantBundle\Action\Api\RecordEvent;
use SolidInvoice\AssistantBundle\Action\Api\UpdateMember;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Under /api, so the API token firewall applies: the token's company is the only one in
 * reach. Plain JSON, outside API Platform, because the events endpoint updates by key.
 */
return static function (RoutingConfigurator $routingConfigurator): void {
    $ulid = ['id' => '[0-9A-HJKMNP-TV-Z]{26}'];

    $routingConfigurator
        ->add('_api_assistants_list', '')
        ->controller(ListAssistants::class)
        ->methods(['GET']);

    $routingConfigurator
        ->add('_api_assistants_create', '')
        ->controller(CreateAssistant::class)
        ->methods(['POST']);

    $routingConfigurator
        ->add('_api_assistants_events', '/{id}/events')
        ->controller(RecordEvent::class)
        ->methods(['POST'])
        ->requirements($ulid);

    $routingConfigurator
        ->add('_api_assistants_members', '/{id}/members')
        ->controller(ListMembers::class)
        ->methods(['GET'])
        ->requirements($ulid);

    $routingConfigurator
        ->add('_api_assistants_members_import', '/{id}/members')
        ->controller(ImportMembers::class)
        ->methods(['PUT'])
        ->requirements($ulid);

    $routingConfigurator
        ->add('_api_assistants_members_config', '/{id}/members/config')
        ->controller(MemberConfig::class)
        ->methods(['GET'])
        ->requirements($ulid);

    $routingConfigurator
        ->add('_api_assistants_member_update', '/{id}/members/{externalId}')
        ->controller(UpdateMember::class)
        ->methods(['PATCH'])
        ->requirements($ulid + ['externalId' => '[^/]{1,64}']);
};
