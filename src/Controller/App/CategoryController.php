<?php

namespace App\Controller\App;

use App\Entity\Category;
use App\Form\App\CategoryType;
use App\Service\CategoryService;
use Fagathe\CorePhp\Breadcrumb\BreadcrumbItem;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/app/category', name: 'app_category_')]
final class CategoryController extends AbstractController
{

    public function __construct(
        private CategoryService $categoryService
    ) {
    }

    #[Route('', name: 'index')]
    public function index(): Response
    {
        return $this->render('app/category/index.html.twig', $this->categoryService->manage());
    }

    #[Route('/edit/{id}', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(#[MapEntity(mapping: ['id' => 'id'])] Category $category, Request $request): Response
    {
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);
        $breadcrumb = $this->categoryService->breadcrumb([
            new BreadcrumbItem('Modifier la catégorie ' . $category->getName()),
        ]);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->categoryService->saveCategory($category);
            $this->addFlash('success', 'Catégorie modifiée avec succès.');

            return $this->redirectToRoute('app_category_edit', ['id' => $category->getId()]);
        }

        return $this->render('app/category/edit.html.twig', compact('form', 'category', 'breadcrumb'));
    }

    #[Route('/add', name: 'add', methods: ['GET', 'POST'])]
    public function add(Request $request): Response
    {
        $category = new Category;
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);
        $breadcrumb = $this->categoryService->breadcrumb([
            new BreadcrumbItem('Ajouter une catégorie', $this->generateUrl('app_category_add'))
        ]);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->categoryService->saveCategory($category, true);
            $this->addFlash('success', 'Catégorie ajoutée avec succès.');

            return $this->redirectToRoute('app_category_index');
        }

        return $this->render('app/category/add.html.twig', compact('form', 'category', 'breadcrumb'));
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Category $category): Response
    {
        $this->denyAccessUnlessGranted('OWNER', $category);

        if (!$this->isCsrfTokenValid('delete-category-' . $category->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $this->categoryService->deleteCategory($category->getId());

        return $this->redirectToRoute('app_category_index');
    }
}