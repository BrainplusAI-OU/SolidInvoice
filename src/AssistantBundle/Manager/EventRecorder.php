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

namespace SolidInvoice\AssistantBundle\Manager;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use SolidInvoice\AssistantBundle\Entity\Assistant;
use SolidInvoice\AssistantBundle\Entity\AssistantEvent;
use SolidInvoice\AssistantBundle\Entity\AssistantMember;
use SolidInvoice\AssistantBundle\Enum\EventChannel;
use SolidInvoice\AssistantBundle\Enum\EventDirection;
use SolidInvoice\AssistantBundle\Enum\EventStatus;
use SolidInvoice\AssistantBundle\Enum\EventType;
use SolidInvoice\AssistantBundle\Repository\AssistantEventRepository;
use SolidInvoice\AssistantBundle\Repository\AssistantMemberRepository;
use SolidInvoice\ExpenseBundle\Entity\Expense;
use Symfony\Component\Uid\Ulid;
use function array_is_list;
use function is_array;
use function is_scalar;
use function is_string;
use function mb_substr;
use function trim;

/**
 * Turns what a bot reports into a journal line.
 *
 * The same key updates the same line, so a bot can report a document when it arrives and
 * again when it is recorded, and can safely retry a call that timed out. Only what is sent
 * changes: a later call with just `status` keeps the subject, the member and the details.
 *
 * @see \SolidInvoice\AssistantBundle\Tests\Functional\Api\AssistantApiTest
 */
final readonly class EventRecorder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AssistantEventRepository $events,
        private AssistantMemberRepository $members,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{0: AssistantEvent, 1: bool} the event, and whether it is new
     *
     * @throws InvalidPayload
     */
    public function record(Assistant $assistant, array $payload): array
    {
        $errors = [];
        $key = $this->optionalString($payload, 'key', $errors);

        if ($key !== null && mb_strlen($key) > AssistantEvent::KEY_LENGTH) {
            $errors[] = 'key: at most ' . AssistantEvent::KEY_LENGTH . ' characters.';
        }

        $event = $key === null ? null : $this->events->findOneByKey($assistant, $key);
        $created = ! $event instanceof AssistantEvent;

        $type = $this->enum($payload, 'type', EventType::class, $errors);
        $status = $this->enum($payload, 'status', EventStatus::class, $errors);
        $channel = $this->enum($payload, 'channel', EventChannel::class, $errors);
        $direction = $this->enum($payload, 'direction', EventDirection::class, $errors);
        $subject = $this->optionalString($payload, 'subject', $errors);
        $occurredAt = $this->date($payload, 'occurredAt', $errors);

        if ($created) {
            if (! $type instanceof EventType) {
                $errors[] = 'type: required, one of ' . implode(', ', array_column(EventType::cases(), 'value')) . '.';
            }

            if ($subject === null) {
                $errors[] = 'subject: required.';
            }
        }

        $details = $payload['details'] ?? null;

        if ($details !== null && (! is_array($details) || ($details !== [] && array_is_list($details)))) {
            $errors[] = 'details: must be an object.';
        }

        $links = $payload['links'] ?? null;

        if ($links !== null && (! is_array($links) || ! array_is_list($links))) {
            $errors[] = 'links: must be a list of {label, url}.';
        }

        if ($errors !== []) {
            throw new InvalidPayload($errors);
        }

        if ($created) {
            assert($type instanceof EventType && $subject !== null);

            $event = new AssistantEvent();
            $event->setAssistant($assistant);
            $event->setEventKey($key);
            $event->setType($type);
            $event->setChannel($channel ?? ($type === EventType::Email ? EventChannel::Email : $assistant->getChannel()));
            $event->setOccurredAt($occurredAt ?? new DateTimeImmutable());
            $event->setStatus($status ?? self::defaultStatus($type), $event->getOccurredAt());

            $this->entityManager->persist($event);
        } else {
            assert($event instanceof AssistantEvent);

            if ($type instanceof EventType) {
                $event->setType($type);
            }

            if ($channel instanceof EventChannel) {
                $event->setChannel($channel);
            }

            if ($status instanceof EventStatus) {
                $event->setStatus($status);
            }
        }

        if ($subject !== null) {
            $event->setSubject(mb_substr($subject, 0, AssistantEvent::SUBJECT_LENGTH));
        }

        if ($direction instanceof EventDirection) {
            $event->setDirection($direction);
        }

        if (array_key_exists('body', $payload)) {
            $body = $payload['body'];
            $event->setBody(is_scalar($body) && trim((string) $body) !== '' ? (string) $body : null);
        }

        $this->applyMember($assistant, $event, $payload);
        $this->applyDetails($event, is_array($details) ? $details : [], is_array($links) ? $links : []);

        if (array_key_exists('expenseId', $payload)) {
            $event->setExpense($this->findExpense($payload['expenseId']));
        }

        $this->entityManager->flush();

        return [$event, $created];
    }

    private static function defaultStatus(EventType $type): EventStatus
    {
        return match ($type) {
            EventType::Document, EventType::Email => EventStatus::Pending,
            EventType::Access => EventStatus::Rejected,
            EventType::Error => EventStatus::Failed,
            default => EventStatus::Info,
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyMember(Assistant $assistant, AssistantEvent $event, array $payload): void
    {
        $member = $payload['member'] ?? null;

        if (! is_array($member)) {
            return;
        }

        $externalId = isset($member['externalId']) && is_scalar($member['externalId']) ? trim((string) $member['externalId']) : '';
        $name = isset($member['name']) && is_scalar($member['name']) ? trim((string) $member['name']) : '';

        if ($externalId !== '') {
            $event->setMemberExternalId(mb_substr($externalId, 0, 64));
            $known = $this->members->findOneByExternalId($assistant, $externalId);
            $event->setMember($known);

            // Seen: when they wrote, not when the bot wrote to them.
            if ($known instanceof AssistantMember && $event->getDirection() !== EventDirection::Out) {
                $at = $event->getOccurredAt();

                if (! $known->getLastSeenAt() instanceof DateTimeImmutable || $known->getLastSeenAt() < $at) {
                    $known->setLastSeenAt($at);
                }
            }
        }

        if ($name !== '') {
            $event->setMemberName(mb_substr($name, 0, 120));
        }
    }

    /**
     * Details are merged one level deep; links are merged by URL.
     *
     * @param array<array-key, mixed> $details
     * @param list<mixed>            $links
     */
    private function applyDetails(AssistantEvent $event, array $details, array $links): void
    {
        $current = $event->getDetails();

        foreach ($details as $name => $value) {
            if ($name === 'history' || $name === 'links') {
                continue;
            }

            $current[(string) $name] = $value;
        }

        if ($links !== []) {
            $byUrl = [];

            foreach ([...$event->getLinks(), ...$links] as $link) {
                if (is_array($link) && is_string($link['url'] ?? null) && $link['url'] !== '') {
                    $byUrl[$link['url']] = ['label' => is_string($link['label'] ?? null) ? $link['label'] : $link['url'], 'url' => $link['url']];
                }
            }

            $current['links'] = array_values($byUrl);
        }

        $event->setDetails($current);
    }

    private function findExpense(mixed $id): ?Expense
    {
        if (! is_string($id) || ! Ulid::isValid($id)) {
            return null;
        }

        // The company filter keeps an expense of another company out of reach.
        return $this->entityManager->getRepository(Expense::class)->find(Ulid::fromString($id));
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string>         $errors
     */
    private function optionalString(array $payload, string $field, array &$errors): ?string
    {
        if (! isset($payload[$field])) {
            return null;
        }

        if (! is_scalar($payload[$field])) {
            $errors[] = $field . ': must be a string.';

            return null;
        }

        $value = trim((string) $payload[$field]);

        return $value === '' ? null : $value;
    }

    /**
     * @template T of \BackedEnum
     *
     * @param array<string, mixed> $payload
     * @param class-string<T>      $enum
     * @param list<string>         $errors
     *
     * @return T|null
     */
    private function enum(array $payload, string $field, string $enum, array &$errors): mixed
    {
        if (! isset($payload[$field]) || $payload[$field] === '') {
            return null;
        }

        $value = is_string($payload[$field]) ? $enum::tryFrom(strtolower(trim($payload[$field]))) : null;

        if ($value === null) {
            $errors[] = $field . ': one of ' . implode(', ', array_column($enum::cases(), 'value')) . '.';
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string>         $errors
     */
    private function date(array $payload, string $field, array &$errors): ?DateTimeImmutable
    {
        if (! isset($payload[$field]) || $payload[$field] === '') {
            return null;
        }

        if (! is_string($payload[$field])) {
            $errors[] = $field . ': an ISO 8601 date.';

            return null;
        }

        try {
            return new DateTimeImmutable($payload[$field]);
        } catch (Exception) {
            $errors[] = $field . ': an ISO 8601 date.';

            return null;
        }
    }
}
