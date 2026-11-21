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

use SolidInvoice\AssistantBundle\Repository\AssistantRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * GET /api/assistants
 */
final readonly class ListAssistants
{
    public function __construct(
        private AssistantRepository $assistants,
        private ApiSupport $support,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        return new JsonResponse(array_map($this->support->describe(...), $this->assistants->findAllByName()));
    }
}
