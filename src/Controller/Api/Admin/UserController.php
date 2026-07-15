<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Voter\UserVoter;
use App\Service\AuthMailer;
use App\Service\Manager\PasswordResetManager;
use App\Service\Manager\UserManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/utilisateurs')]
class UserController extends AbstractController
{
    /** Rôles attribuables depuis l'admin (ROLE_USER est implicite). */
    private const ASSIGNABLE_ROLES = ['ROLE_ADMIN'];

    public function __construct(
        private readonly UserManager $userManager,
        private readonly PasswordResetManager $passwordResetManager,
        private readonly AuthMailer $authMailer,
    ) {
    }

    #[Route('', name: 'api_admin_users_list', methods: ['GET'])]
    public function list(Request $request, UserRepository $userRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted(UserVoter::VIEW);

        $page = max(1, $request->query->getInt('page', 1));
        $pageSize = min(100, max(1, $request->query->getInt('pageSize', 20)));
        $query = $request->query->getString('q');

        $result = $userRepository->searchPaginated($query, $page, $pageSize);

        return $this->json([
            'items' => array_map($this->serializeUser(...), $result['items']),
            'total' => $result['total'],
            'page' => $page,
            'pageSize' => $pageSize,
        ]);
    }

    #[Route('/{id}/reinitialiser-mot-de-passe', name: 'api_admin_users_reset_password', methods: ['POST'])]
    public function resetPassword(User $user): JsonResponse
    {
        $this->denyAccessUnlessGranted(UserVoter::RESET_PASSWORD, $user);

        $token = $this->passwordResetManager->createToken($user);

        if (!$token) {
            return $this->json(
                ['message' => 'Une erreur est survenue.'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        $this->authMailer->sendPasswordResetCode($user, $token->getCode());

        return $this->json(['message' => sprintf('Un code de réinitialisation a été envoyé à %s.', $user->getEmail())]);
    }

    #[Route('/{id}/verifier', name: 'api_admin_users_verify', methods: ['POST'])]
    public function verify(User $user): JsonResponse
    {
        $this->denyAccessUnlessGranted(UserVoter::VERIFY, $user);

        if ($user->isVerified()) {
            return $this->json(
                ['message' => 'Ce compte est déjà vérifié.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if (!$this->userManager->verifyEmail($user)) {
            return $this->json(
                ['message' => 'Une erreur est survenue.'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return $this->json($this->serializeUser($user));
    }

    #[Route('/{id}/renvoyer-verification', name: 'api_admin_users_resend_verification', methods: ['POST'])]
    public function resendVerification(User $user): JsonResponse
    {
        $this->denyAccessUnlessGranted(UserVoter::RESEND_VERIFICATION, $user);

        if ($user->isVerified() || !$user->getVerificationToken()) {
            return $this->json(
                ['message' => 'Ce compte est déjà vérifié.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->authMailer->sendVerificationEmail($user);

        return $this->json(['message' => sprintf('E-mail de vérification renvoyé à %s.', $user->getEmail())]);
    }

    #[Route('/{id}/roles', name: 'api_admin_users_update_roles', methods: ['PUT'])]
    public function updateRoles(User $user, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(UserVoter::EDIT_ROLES, $user);

        if ($this->isCurrentUser($user)) {
            return $this->json(
                ['message' => 'Vous ne pouvez pas modifier vos propres rôles.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        /** @var array{roles?: mixed} $payload */
        $payload = $request->toArray();
        $roles = $payload['roles'] ?? null;

        if (!is_array($roles)) {
            return $this->json(
                ['message' => 'Le champ "roles" est obligatoire.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $user->setRoles(array_values(array_intersect(self::ASSIGNABLE_ROLES, $roles)));

        if (!$this->userManager->update($user)) {
            return $this->json(
                ['message' => 'Une erreur est survenue.'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return $this->json($this->serializeUser($user));
    }

    #[Route('/{id}', name: 'api_admin_users_delete', methods: ['DELETE'])]
    public function delete(User $user): JsonResponse
    {
        $this->denyAccessUnlessGranted(UserVoter::DELETE, $user);

        if ($this->isCurrentUser($user)) {
            return $this->json(
                ['message' => 'Vous ne pouvez pas supprimer votre propre compte.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if (!$this->userManager->delete($user)) {
            return $this->json(
                ['message' => 'Une erreur est survenue.'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function isCurrentUser(User $user): bool
    {
        return $this->getUser()?->getUserIdentifier() === $user->getUserIdentifier();
    }

    /**
     * @return array{id: int|null, email: string|null, roles: list<string>, isVerified: bool}
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'isVerified' => $user->isVerified(),
        ];
    }
}
