<?php
namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\Admin\User\UserType;
use App\Service\UserService;
use Fagathe\CorePhp\Breadcrumb\BreadcrumbItem;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: "/admin/user", name: "admin_user_")]
final class UserController extends AbstractController
{

    public function __construct(
        // Injecter les services nécessaires ici
        private UserService $userService
    ) {
        // Initialisation si nécessaire
    }

    #[Route(path: '/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $user = new User;
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->userService->createUser($user)) {
                $this->addFlash('success', 'User created successfully.');
                return $this->redirectToRoute('admin_user_index');
            } else {
                $this->addFlash('error', 'There was an error creating the user.');
            }
        }

        $breadcrumb = $this->userService->breadcrumb([new BreadcrumbItem('Créer un utilisateur')]);

        return $this->render('admin/user/create.html.twig', compact('form', 'user', 'breadcrumb'));
    }

    #[Route(path: '/edit/{id}', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(User $user, Request $request): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->userService->saveUser($user)) {
                $this->addFlash('success', 'User updated successfully.');
            } else {
                $this->addFlash('error', 'There was an error updating the user.');
            }
        }

        $breadcrumb = $this->userService->breadcrumb([new BreadcrumbItem('Modifier un utilisateur')]);
        return $this->render('admin/user/edit.html.twig', compact('form', 'user', 'breadcrumb'));
    }

    #[Route(path: '', name: 'index', methods: ['GET'])]
    public function manageUsers(Request $request): Response
    {
        return $this->render('admin/user/index.html.twig', $this->userService->manageUsers($request));
    }

    #[Route(path: '/delete/{id}', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request): Response
    {
        // 1. Récupération du token envoyé par le formulaire
        // 'request' correspond au $_POST, '_token' est le name de ton input hidden
        $submittedToken = $request->request->get('_token');

        // 2. Validation du Token CSRF
        // Le premier argument doit être EXACTEMENT le même string que dans ton Twig
        // Twig : 'delete' ~ category.id  ---> PHP : 'delete' . $category->getId()
        if ($this->isCsrfTokenValid('delete' . $id, $submittedToken)) {

            // 3. Appel au service pour la suppression
            // (J'utilise la méthode deleteCategory de ton service existant)
            if ($this->userService->deleteUser($id)) {
                $this->addFlash('success', 'L\'utilisateur a été supprimé avec succès.');
            } else {
                $this->addFlash('danger', 'Une erreur est survenue lors de la suppression.');
            }
        } else {
            // Si le token est invalide (attaque CSRF potentielle)
            $this->addFlash('danger', 'Token de sécurité invalide, impossible de supprimer.');
        }

        return $this->redirectToRoute('admin_user_index');
    }
}