<?php

declare(strict_types=1);

namespace App\Service\Manager;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Florimond\LogBundle\Service\Manager\ApplicationLogManager;

class CategoryManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ApplicationLogManager $logManager,
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    public function create(string $name): ?Category
    {
        $category = new Category();
        $category->setName($name);
        $category->setSlug($this->generateUniqueSlug($name));

        return $this->insert($category) ? $category : null;
    }

    public function delete(Category $category, bool $flush = true): bool
    {
        $this->em->remove($category);

        if ($flush) {
            try {
                $this->em->flush();
            } catch (\Throwable $e) {
                $this->logManager->reportException($e);

                return false;
            }
        }

        return true;
    }

    public function insert(Category $category, bool $flush = true): bool
    {
        $this->em->persist($category);

        if ($flush) {
            try {
                $this->em->flush();
            } catch (\Throwable $e) {
                $this->logManager->reportException($e);

                return false;
            }
        }

        return true;
    }

    private function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $base    = $this->slugify($name);
        $slug    = $base;
        $counter = 2;

        while (true) {
            $existing = $this->categoryRepository->findBySlug($slug);

            if (null === $existing || $existing->getId() === $excludeId) {
                break;
            }

            $slug = $base.'-'.$counter;
            ++$counter;
        }

        return $slug;
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = (string) preg_replace('/[^a-z0-9\-]/', '-', $text);
        $text = (string) preg_replace('/-+/', '-', $text);

        return trim($text, '-') ?: 'categorie';
    }
}
