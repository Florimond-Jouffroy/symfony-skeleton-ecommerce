<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Entity\PromoCode;
use App\Repository\PromoCodeRepository;
use App\Security\Voter\PromoCodeVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/codes-promo')]
class PromoCodeController extends AbstractController
{
    public function __construct(
        private readonly PromoCodeRepository  $repo,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'api_admin_promo_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->denyAccessUnlessGranted(PromoCodeVoter::VIEW);

        $codes = $this->repo->findBy([], ['createdAt' => 'DESC']);

        return $this->json(array_map($this->serialize(...), $codes));
    }

    #[Route('', name: 'api_admin_promo_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PromoCodeVoter::CREATE);

        $payload = $request->toArray();

        $code = new PromoCode();
        $err  = $this->hydrate($code, $payload, isNew: true);
        if ($err) {
            return $this->json(['message' => $err], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($code);
        $this->em->flush();

        return $this->json($this->serialize($code), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_promo_update', methods: ['PATCH'])]
    public function update(PromoCode $code, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PromoCodeVoter::EDIT);

        $payload = $request->toArray();

        $err = $this->hydrate($code, $payload, isNew: false);
        if ($err) {
            return $this->json(['message' => $err], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->flush();

        return $this->json($this->serialize($code));
    }

    #[Route('/{id}', name: 'api_admin_promo_delete', methods: ['DELETE'])]
    public function delete(PromoCode $code): JsonResponse
    {
        $this->denyAccessUnlessGranted(PromoCodeVoter::DELETE);

        $this->em->remove($code);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function hydrate(PromoCode $code, array $p, bool $isNew): ?string
    {
        if ($isNew || array_key_exists('code', $p)) {
            $raw = strtoupper(trim((string) ($p['code'] ?? '')));
            if ('' === $raw) {
                return 'Le code est obligatoire.';
            }
            if (!preg_match('/^[A-Z0-9_-]{2,50}$/', $raw)) {
                return 'Le code ne peut contenir que des lettres, chiffres, tirets et underscores (2–50 caractères).';
            }
            $existing = $this->repo->findByCode($raw);
            if ($existing && $existing->getId() !== $code->getId()) {
                return 'Ce code existe déjà.';
            }
            $code->setCode($raw);
        }

        if ($isNew || array_key_exists('type', $p)) {
            $type = (string) ($p['type'] ?? PromoCode::TYPE_PERCENT);
            if (!in_array($type, [PromoCode::TYPE_PERCENT, PromoCode::TYPE_FIXED], true)) {
                return 'Type invalide (percent ou fixed).';
            }
            $code->setType($type);
        }

        if ($isNew || array_key_exists('value', $p)) {
            $value = (int) ($p['value'] ?? 0);
            if ($value <= 0) {
                return 'La valeur doit être supérieure à 0.';
            }
            if (PromoCode::TYPE_PERCENT === $code->getType() && $value > 100) {
                return 'Un pourcentage ne peut pas dépasser 100.';
            }
            $code->setValue($value);
        }

        if (array_key_exists('expiresAt', $p)) {
            $code->setExpiresAt(
                $p['expiresAt'] ? new \DateTimeImmutable((string) $p['expiresAt']) : null
            );
        }

        if (array_key_exists('maxUses', $p)) {
            $code->setMaxUses($p['maxUses'] !== null ? max(1, (int) $p['maxUses']) : null);
        }

        if (array_key_exists('isActive', $p)) {
            $code->setIsActive((bool) $p['isActive']);
        }

        return null;
    }

    private function serialize(PromoCode $code): array
    {
        return [
            'id'         => $code->getId(),
            'code'       => $code->getCode(),
            'type'       => $code->getType(),
            'value'      => $code->getValue(),
            'expiresAt'  => $code->getExpiresAt()?->format('Y-m-d'),
            'maxUses'    => $code->getMaxUses(),
            'usedCount'  => $code->getUsedCount(),
            'isActive'   => $code->isActive(),
            'isUsable'   => $code->isUsable(),
            'createdAt'  => $code->getCreatedAt()->format('c'),
        ];
    }
}
