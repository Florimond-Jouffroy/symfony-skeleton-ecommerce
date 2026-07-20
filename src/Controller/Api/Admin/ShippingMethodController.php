<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Dto\ShippingMethodDto;
use App\Entity\ShippingMethod;
use App\Repository\ShippingMethodRepository;
use App\Security\Voter\ShippingVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/livraison')]
class ShippingMethodController extends AbstractController
{
    #[Route('', name: 'api_admin_shipping_list', methods: ['GET'])]
    public function list(ShippingMethodRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted(ShippingVoter::VIEW);

        return $this->json(array_map($this->serialize(...), $repo->findAllOrdered()));
    }

    #[Route('', name: 'api_admin_shipping_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] ShippingMethodDto $dto, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(ShippingVoter::CREATE);

        $method = new ShippingMethod();
        $this->apply($method, $dto);
        $em->persist($method);
        $em->flush();

        return $this->json($this->serialize($method), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_shipping_update', methods: ['PUT'])]
    public function update(ShippingMethod $method, #[MapRequestPayload] ShippingMethodDto $dto, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(ShippingVoter::EDIT);

        $this->apply($method, $dto);
        $em->flush();

        return $this->json($this->serialize($method));
    }

    #[Route('/{id}', name: 'api_admin_shipping_delete', methods: ['DELETE'])]
    public function delete(ShippingMethod $method, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(ShippingVoter::DELETE);

        $em->remove($method);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function apply(ShippingMethod $method, ShippingMethodDto $dto): void
    {
        $description = null !== $dto->description && '' !== trim($dto->description) ? trim($dto->description) : null;

        $method->setName(trim($dto->name));
        $method->setDescription($description);
        $method->setPrice($dto->price);
        $method->setFreeAboveAmount($dto->freeAboveAmount);
        $method->setIsActive($dto->isActive);
        $method->setPosition($dto->position);
    }

    private function serialize(ShippingMethod $method): array
    {
        return [
            'id'              => $method->getId(),
            'name'            => $method->getName(),
            'description'     => $method->getDescription(),
            'price'           => $method->getPrice(),
            'freeAboveAmount' => $method->getFreeAboveAmount(),
            'isActive'        => $method->isActive(),
            'position'        => $method->getPosition(),
        ];
    }
}
