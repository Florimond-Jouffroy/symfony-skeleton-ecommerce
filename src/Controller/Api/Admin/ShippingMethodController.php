<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Entity\ShippingMethod;
use App\Repository\ShippingMethodRepository;
use App\Security\Voter\ShippingVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(ShippingVoter::CREATE);

        $data   = $request->toArray();
        $errors = $this->validate($data);
        if ($errors) {
            return $this->json(['message' => implode(' ', $errors)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $method = new ShippingMethod();
        $this->hydrate($method, $data);
        $em->persist($method);
        $em->flush();

        return $this->json($this->serialize($method), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_shipping_update', methods: ['PUT'])]
    public function update(ShippingMethod $method, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(ShippingVoter::EDIT);

        $data   = $request->toArray();
        $errors = $this->validate($data);
        if ($errors) {
            return $this->json(['message' => implode(' ', $errors)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->hydrate($method, $data);
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

    /** @return string[] */
    private function validate(array $data): array
    {
        $errors = [];
        if (empty(trim((string) ($data['name'] ?? '')))) {
            $errors[] = 'Le nom est obligatoire.';
        }
        if (!isset($data['price']) || !is_numeric($data['price']) || (int) $data['price'] < 0) {
            $errors[] = 'Le prix doit être un entier positif ou nul (en centimes).';
        }

        return $errors;
    }

    private function hydrate(ShippingMethod $method, array $data): void
    {
        $method->setName(trim((string) ($data['name'] ?? '')));
        $method->setDescription(isset($data['description']) && '' !== trim((string) $data['description'])
            ? trim((string) $data['description'])
            : null);
        $method->setPrice(max(0, (int) ($data['price'] ?? 0)));
        $method->setFreeAboveAmount(isset($data['freeAboveAmount']) && $data['freeAboveAmount'] !== null && $data['freeAboveAmount'] !== ''
            ? max(0, (int) $data['freeAboveAmount'])
            : null);
        $method->setIsActive((bool) ($data['isActive'] ?? true));
        $method->setPosition(max(0, (int) ($data['position'] ?? 0)));
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
