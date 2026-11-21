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

use SolidInvoice\AssistantBundle\Manager\InvalidPayload;
use SolidInvoice\AssistantBundle\Manager\MemberSync;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use function is_array;

/**
 * PUT /api/assistants/{id}/members {members: [{externalId, name, vehiclePlates?, language?, payer?, active?}], deactivateMissing?}
 */
final readonly class ImportMembers
{
    public function __construct(
        private ApiSupport $support,
        private MemberSync $sync,
    ) {
    }

    public function __invoke(Request $request, string $id): JsonResponse
    {
        $assistant = $this->support->assistant($id);
        $data = $this->support->body($request);

        if (! isset($data['members']) || ! is_array($data['members']) || ! array_is_list($data['members'])) {
            return $this->support->invalid(['members: a list is required.']);
        }

        try {
            $result = $this->sync->import($assistant, $data['members'], ($data['deactivateMissing'] ?? false) === true);
        } catch (InvalidPayload $exception) {
            return $this->support->invalid($exception->getErrors());
        }

        return new JsonResponse($result);
    }
}
