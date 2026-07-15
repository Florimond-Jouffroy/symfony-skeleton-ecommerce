<?php

declare(strict_types=1);

namespace App\Service\Manager;

use App\Entity\ProductCategory;
use App\Repository\ProductCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Florimond\LogBundle\Service\Manager\ApplicationLogManager;

/**
 * Gère les opérations CRUD sur les catégories de produits.
 * Génère automatiquement un slug unique à partir du nom.
 */
class ProductCategoryManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ApplicationLogManager $logManager,
        private readonly ProductCategoryRepository $repository,
    ) {
    }

    public function create(string $name): ?ProductCategory
    {
        $category = new ProductCategory();
        $category->setName($name);
        $category->setSlug($this->generateUniqueSlug($name));

        return $this->insert($category) ? $category : null;
    }

    /**
     * Met à jour le nom et, si nécessaire, le slug.
     * Le slug n'est régénéré que si le nom a vraiment changé pour éviter de
     * casser des URLs existantes lors de corrections mineures (casse, espaces…).
     */
    public function update(ProductCategory $category, string $name, ?int $taxRate = null): bool
    {
        $category->setName($name);
        if ($this->slugify($name) !== $category->getSlug()) {
            $category->setSlug($this->generateUniqueSlug($name, $category->getId()));
        }
        $category->setTaxRate($taxRate);

        return $this->flush();
    }

    public function delete(ProductCategory $category): bool
    {
        $this->em->remove($category);

        return $this->flush();
    }

    public function insert(ProductCategory $category, bool $flush = true): bool
    {
        $this->em->persist($category);

        return $flush ? $this->flush() : true;
    }

    private function flush(): bool
    {
        try {
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->logManager->reportException($e);

            return false;
        }

        return true;
    }

    private function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $base    = $this->slugify($name);
        $slug    = $base;
        $counter = 2;

        while (true) {
            $existing = $this->repository->findBySlug($slug);
            if (null === $existing || $existing->getId() === $excludeId) {
                break;
            }
            $slug = $base.'-'.$counter++;
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
