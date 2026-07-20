<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Dto\FaqItemDto;
use App\Entity\FaqItem;
use App\Repository\FaqItemRepository;
use App\Security\Voter\FaqVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/faq')]
class FaqController extends AbstractController
{
    private function serialize(FaqItem $item): array
    {
        return [
            'id'        => $item->getId(),
            'question'  => $item->getQuestion(),
            'answer'    => $item->getAnswer(),
            'position'  => $item->getPosition(),
            'isActive'  => $item->isActive(),
            'createdAt' => $item->getCreatedAt()->format('Y-m-d H:i'),
        ];
    }

    #[Route('', name: 'api_admin_faq_list', methods: ['GET'])]
    public function list(FaqItemRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted(FaqVoter::VIEW);

        return $this->json(array_map($this->serialize(...), $repo->findAllOrdered()));
    }

    #[Route('', name: 'api_admin_faq_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] FaqItemDto $dto, FaqItemRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(FaqVoter::CREATE);

        $item = new FaqItem();
        $item->setQuestion(trim($dto->question));
        $item->setAnswer(trim($dto->answer));
        $item->setPosition($repo->getMaxPosition() + 1);
        $item->setIsActive($dto->isActive);

        $em->persist($item);
        $em->flush();

        return $this->json($this->serialize($item), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_faq_update', methods: ['PATCH'])]
    public function update(int $id, #[MapRequestPayload] FaqItemDto $dto, FaqItemRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(FaqVoter::EDIT);

        $item = $repo->find($id);
        if (!$item) {
            return $this->json(['message' => 'FAQ introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $item->setQuestion(trim($dto->question));
        $item->setAnswer(trim($dto->answer));
        $item->setIsActive($dto->isActive);

        $em->flush();

        return $this->json($this->serialize($item));
    }

    #[Route('/{id}', name: 'api_admin_faq_delete', methods: ['DELETE'])]
    public function delete(int $id, FaqItemRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(FaqVoter::DELETE);

        $item = $repo->find($id);
        if (!$item) {
            return $this->json(['message' => 'FAQ introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($item);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/move-up', name: 'api_admin_faq_move_up', methods: ['POST'])]
    public function moveUp(int $id, FaqItemRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(FaqVoter::EDIT);

        $all = $repo->findAllOrdered();
        $idx = array_search($id, array_column(array_map(fn ($i) => ['id' => $i->getId()], $all), 'id'));

        if (false === $idx || 0 === $idx) {
            return $this->json(['message' => 'Impossible de monter cet élément.'], Response::HTTP_BAD_REQUEST);
        }

        $current  = $all[$idx];
        $previous = $all[$idx - 1];

        $posA = $current->getPosition();
        $posB = $previous->getPosition();

        if ($posA === $posB) {
            $posA = $idx;
            $posB = $idx - 1;
        }

        $current->setPosition($posB);
        $previous->setPosition($posA);
        $em->flush();

        return $this->json(array_map($this->serialize(...), $repo->findAllOrdered()));
    }

    #[Route('/{id}/move-down', name: 'api_admin_faq_move_down', methods: ['POST'])]
    public function moveDown(int $id, FaqItemRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(FaqVoter::EDIT);

        $all  = $repo->findAllOrdered();
        $last = count($all) - 1;
        $idx  = array_search($id, array_column(array_map(fn ($i) => ['id' => $i->getId()], $all), 'id'));

        if (false === $idx || $idx === $last) {
            return $this->json(['message' => 'Impossible de descendre cet élément.'], Response::HTTP_BAD_REQUEST);
        }

        $current = $all[$idx];
        $next    = $all[$idx + 1];

        $posA = $current->getPosition();
        $posB = $next->getPosition();

        if ($posA === $posB) {
            $posA = $idx;
            $posB = $idx + 1;
        }

        $current->setPosition($posB);
        $next->setPosition($posA);
        $em->flush();

        return $this->json(array_map($this->serialize(...), $repo->findAllOrdered()));
    }
}
