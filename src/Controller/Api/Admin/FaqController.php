<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Entity\FaqItem;
use App\Repository\FaqItemRepository;
use App\Security\Voter\FaqVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    public function create(Request $request, FaqItemRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(FaqVoter::CREATE);
        $data = $request->toArray();

        $question = trim((string) ($data['question'] ?? ''));
        $answer   = trim((string) ($data['answer'] ?? ''));

        if ('' === $question || '' === $answer) {
            return $this->json(['message' => 'La question et la réponse sont requises.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $item = new FaqItem();
        $item->setQuestion($question);
        $item->setAnswer($answer);
        $item->setPosition($repo->getMaxPosition() + 1);
        $item->setIsActive((bool) ($data['isActive'] ?? true));

        $em->persist($item);
        $em->flush();

        return $this->json($this->serialize($item), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_faq_update', methods: ['PATCH'])]
    public function update(int $id, Request $request, FaqItemRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(FaqVoter::EDIT);

        $item = $repo->find($id);
        if (!$item) {
            return $this->json(['message' => 'FAQ introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->toArray();

        if (array_key_exists('question', $data)) {
            $q = trim((string) $data['question']);
            if ('' === $q) {
                return $this->json(['message' => 'La question ne peut pas être vide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $item->setQuestion($q);
        }

        if (array_key_exists('answer', $data)) {
            $a = trim((string) $data['answer']);
            if ('' === $a) {
                return $this->json(['message' => 'La réponse ne peut pas être vide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $item->setAnswer($a);
        }

        if (array_key_exists('isActive', $data)) {
            $item->setIsActive((bool) $data['isActive']);
        }

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

        if ($idx === false || $idx === 0) {
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

        if ($idx === false || $idx === $last) {
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
