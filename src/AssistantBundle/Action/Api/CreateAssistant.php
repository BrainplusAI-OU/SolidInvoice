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

use Doctrine\ORM\EntityManagerInterface;
use SolidInvoice\AssistantBundle\Entity\Assistant;
use SolidInvoice\AssistantBundle\Enum\EventChannel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use function is_string;

/**
 * POST /api/assistants {name, channel?, inboxEmail?, description?}
 */
final readonly class CreateAssistant
{
    public function __construct(
        private ApiSupport $support,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $data = $this->support->body($request);

        $assistant = new Assistant();
        $assistant->setName(is_string($data['name'] ?? null) ? $data['name'] : '');
        $assistant->setDescription(is_string($data['description'] ?? null) ? $data['description'] : null);
        $assistant->setInboxEmail(is_string($data['inboxEmail'] ?? null) ? $data['inboxEmail'] : null);

        if (isset($data['channel'])) {
            $channel = is_string($data['channel']) ? EventChannel::tryFrom($data['channel']) : null;

            if (! $channel instanceof EventChannel) {
                return $this->support->invalid(['channel: one of ' . implode(', ', array_column(EventChannel::cases(), 'value')) . '.']);
            }

            $assistant->setChannel($channel);
        }

        $violations = $this->validator->validate($assistant);

        if (count($violations) > 0) {
            $errors = [];

            foreach ($violations as $violation) {
                $errors[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }

            return $this->support->invalid($errors);
        }

        $this->entityManager->persist($assistant);
        $this->entityManager->flush();

        return new JsonResponse($this->support->describe($assistant), Response::HTTP_CREATED);
    }
}
