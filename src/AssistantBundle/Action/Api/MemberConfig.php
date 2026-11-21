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

use SolidInvoice\AssistantBundle\Manager\MemberSync;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * GET /api/assistants/{id}/members/config
 *
 * The active members in the shape of a Google Sheets `values.get` response
 * ({range, majorDimension, values}), with the columns id, name, plates, language, payer.
 * A bot that read its allow-list from a sheet only needs its URL changed.
 */
final readonly class MemberConfig
{
    public function __construct(
        private ApiSupport $support,
        private MemberSync $sync,
    ) {
    }

    public function __invoke(string $id): JsonResponse
    {
        $assistant = $this->support->assistant($id);

        return new JsonResponse([
            'range' => 'members',
            'majorDimension' => 'ROWS',
            'values' => $this->sync->asConfigRows($assistant),
        ]);
    }
}
