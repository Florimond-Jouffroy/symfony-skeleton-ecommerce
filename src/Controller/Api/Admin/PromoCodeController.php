<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Dto\PromoCodeDto;
use App\Entity\PromoCode;
use App\Repository\PromoCodeRepository;
use App\Security\Voter\PromoCodeVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/codes-promo')]
class PromoCodeController extends AbstractController
{
    public function __construct(
        private readonly PromoCodeRepository $repo,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'api_admin_promo_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->denyAccessUnlessGranted(PromoCodeVoter::VIEW);

        $codes = $this->repo->findBy([], ['createdAt' => 'DESC']);

        return $this->json(array_map($this->serialize(...), $codes));
    }

    #[Route('', name: 'api_admin_promo_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] PromoCodeDto $dto): JsonResponse
    {
        $this->denyAccessUnlessGranted(PromoCodeVoter::CREATE);

        $code = new PromoCode();
        if ($err = $this->apply($code, $dto)) {
            return $this->json(['message' => $err], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($code);
        $this->em->flush();

        return $this->json($this->serialize($code), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_promo_update', methods: ['PATCH'])]
    public function update(PromoCode $code, #[MapRequestPayload] PromoCodeDto $dto): JsonResponse
    {
        $this->denyAccessUnlessGranted(PromoCodeVoter::EDIT);

        if ($err = $this->apply($code, $dto)) {
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

    /**
     * Applique le DTO validé à l'entité. La seule règle non exprimable sur le DTO
     * (unicité du code en base, en s'excluant soi-même) est contrôlée ici.
     *
     * @return string|null message d'erreur, ou null si tout est valide
     */
    private function apply(PromoCode $code, PromoCodeDto $dto): ?string
    {
        $normalized = strtoupper(trim($dto->code));
        $existing   = $this->repo->findByCode($normalized);
        if ($existing && $existing->getId() !== $code->getId()) {
            return 'Ce code existe déjà.';
        }

        $code->setCode($dto->code);
        $code->setType($dto->type);
        $code->setValue($dto->value);
        $code->setExpiresAt($dto->expiresAt);
        $code->setMaxUses($dto->maxUses);
        $code->setIsActive($dto->isActive);

        return null;
    }

    private function serialize(PromoCode $code): array
    {
        return [
            'id'        => $code->getId(),
            'code'      => $code->getCode(),
            'type'      => $code->getType(),
            'value'     => $code->getValue(),
            'expiresAt' => $code->getExpiresAt()?->format('Y-m-d'),
            'maxUses'   => $code->getMaxUses(),
            'usedCount' => $code->getUsedCount(),
            'isActive'  => $code->isActive(),
            'isUsable'  => $code->isUsable(),
            'createdAt' => $code->getCreatedAt()->format('c'),
        ];
    }
}
