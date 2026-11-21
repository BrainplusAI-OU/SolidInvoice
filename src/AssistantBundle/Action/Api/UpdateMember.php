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

namespace SolidInvoice\AssistantBundle\Action\Api;

use SolidInvoice\AssistantBundle\Entity\AssistantMember;
use SolidInvoice\AssistantBundle\Manager\MemberSync;
use SolidInvoice\AssistantBundle\Repository\AssistantMemberRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * PATCH /api/assistants/{id}/members/{externalId} {vehiclePlates?, language?, payer?, name?, active?}
 *
 * What the bot changes itself, such as the language a person picks. A stranger cannot be
 * added this way: only a person managing the list, or an import, adds members.
 */
final readonly class UpdateMember
{
    public function __construct(
        private ApiSupport $support,
        private AssistantMemberRepository $members,
        private MemberSync $sync,
    ) {
    }

    public function __invoke(Request $request, string $id, string $externalId): JsonResponse
    {
        $assistant = $this->support->assistant($id);
        $member = $this->members->findOneByExternalId($assistant, $externalId);

        if (! $member instanceof AssistantMember) {
            throw new NotFoundHttpException('Member not found.');
        }

        $this->sync->update($member, $this->support->body($request));

        return new JsonResponse($this->support->member($member));
    }
}
