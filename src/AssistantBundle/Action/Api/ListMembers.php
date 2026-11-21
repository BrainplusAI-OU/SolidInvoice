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

use SolidInvoice\AssistantBundle\Repository\AssistantMemberRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * GET /api/assistants/{id}/members
 */
final readonly class ListMembers
{
    public function __construct(
        private ApiSupport $support,
        private AssistantMemberRepository $members,
    ) {
    }

    public function __invoke(string $id): JsonResponse
    {
        $assistant = $this->support->assistant($id);

        return new JsonResponse(array_map($this->support->member(...), $this->members->findByAssistant($assistant)));
    }
}
