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

use SolidInvoice\AssistantBundle\Manager\EventRecorder;
use SolidInvoice\AssistantBundle\Manager\InvalidPayload;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/assistants/{id}/events
 *
 * 201 for a new journal line, 200 when the key matched an existing one.
 */
final readonly class RecordEvent
{
    public function __construct(
        private ApiSupport $support,
        private EventRecorder $recorder,
    ) {
    }

    public function __invoke(Request $request, string $id): JsonResponse
    {
        $assistant = $this->support->assistant($id);

        try {
            [$event, $created] = $this->recorder->record($assistant, $this->support->body($request));
        } catch (InvalidPayload $exception) {
            return $this->support->invalid($exception->getErrors());
        }

        return new JsonResponse([
            'id' => (string) $event->getId(),
            'key' => $event->getEventKey(),
            'type' => $event->getType()->value,
            'status' => $event->getStatus()->value,
            'member' => $event->getMember()?->getName(),
            'expenseId' => $event->getExpense()?->getId()?->toBase32(),
            'created' => $created,
        ], $created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
