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

use JsonException;
use SolidInvoice\AssistantBundle\Entity\Assistant;
use SolidInvoice\AssistantBundle\Entity\AssistantMember;
use SolidInvoice\AssistantBundle\Repository\AssistantRepository;
use SolidInvoice\CoreBundle\Company\CompanySelector;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Ulid;
use function is_array;
use function json_decode;

/**
 * What the assistant API endpoints share: finding the assistant of the token's company,
 * reading a JSON body, and the shape of a member in a response.
 */
final readonly class ApiSupport
{
    public function __construct(
        private AssistantRepository $assistants,
        private CompanySelector $companySelector,
    ) {
    }

    /**
     * Another company's assistant is a 404. The company filter covers queries, but find() can
     * return an entity already loaded in this entity manager without one, so the company is
     * checked here too.
     */
    public function assistant(string $id): Assistant
    {
        $assistant = Ulid::isValid($id) ? $this->assistants->find(Ulid::fromString($id)) : null;
        $company = $this->companySelector->getCompany();

        if (! $assistant instanceof Assistant || ! $company instanceof Ulid || ! $company->equals($assistant->getCompany()->getId())) {
            throw new NotFoundHttpException('Assistant not found.');
        }

        return $assistant;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function body(Request $request): array
    {
        $content = $request->getContent();

        if (trim($content) === '') {
            return [];
        }

        try {
            $data = json_decode($content, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new BadRequestHttpException('The body is not valid JSON.');
        }

        if (! is_array($data)) {
            throw new BadRequestHttpException('The body must be a JSON object.');
        }

        return $data;
    }

    /**
     * @param list<string> $errors
     */
    public function invalid(array $errors): JsonResponse
    {
        return new JsonResponse(['message' => 'Invalid data.', 'errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @return array<string, mixed>
     */
    public function member(AssistantMember $member): array
    {
        return [
            'id' => (string) $member->getId(),
            'externalId' => $member->getExternalId(),
            'name' => $member->getName(),
            'vehiclePlates' => $member->getVehiclePlates(),
            'language' => $member->getLanguage(),
            'payer' => $member->getPayer(),
            'active' => $member->isActive(),
            'lastSeenAt' => $member->getLastSeenAt()?->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function describe(Assistant $assistant): array
    {
        return [
            'id' => (string) $assistant->getId(),
            'name' => $assistant->getName(),
            'channel' => $assistant->getChannel()->value,
            'inboxEmail' => $assistant->getInboxEmail(),
            'description' => $assistant->getDescription(),
        ];
    }
}
