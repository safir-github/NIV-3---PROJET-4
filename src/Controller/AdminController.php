<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\ProductStock;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur d'Administration (AdminController)
 * 
 * Ce contrôleur gère le back-office d'administration des produits :
 * - Page unique d'administration regroupant l'ajout et l'édition en ligne.
 * - Traitement d'ajout via le formulaire de création de produit.
 * - Traitement d'édition en ligne via des formulaires HTML sécurisés dédiés par produit.
 * - Suppression d'un sweat-shirt et de ses stocks en cascade.
 */
#[Route('/admin')]
class AdminController extends AbstractController
{
    private ProductRepository $productRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(ProductRepository $productRepository, EntityManagerInterface $entityManager)
    {
        $this->productRepository = $productRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * Page unique du back-office : liste des produits, formulaires de modification en ligne
     * et formulaire d'ajout en haut.
     */
    #[Route('/', name: 'app_admin_index', methods: ['GET'])]
    public function index(): Response
    {
        $products = $this->productRepository->findAll();

        // Formulaire d'ajout vierge
        $product = new Product();
        $addForm = $this->createForm(ProductType::class, $product);

        return $this->render('admin/index.html.twig', [
            'products' => $products,
            'addForm' => $addForm->createView(),
        ]);
    }

    /**
     * Endpoint POST de création d'un nouveau produit (redirection vers index en GET)
     */
    #[Route('/new', name: 'app_admin_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        // Sécurité GET : redirection vers la page unique du back-office
        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('app_admin_index');
        }

        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $imagesDirectory = $this->getParameter('kernel.project_dir') . '/public/images';
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move($imagesDirectory, $newFilename);
                    $product->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors du téléversement de l\'image.');
                    return $this->redirectToRoute('app_admin_index');
                }
            }

            $this->entityManager->persist($product);

            // Création des stocks par taille
            $sizes = ['XS', 'S', 'M', 'L', 'XL'];
            foreach ($sizes as $size) {
                $quantity = $form->get('stock_' . $size)->getData() ?? 0;

                $stock = new ProductStock();
                $stock->setProduct($product);
                $stock->setSize($size);
                $stock->setQuantity($quantity);

                $this->entityManager->persist($stock);
            }

            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Le sweat-shirt %s a été créé avec succès.', $product->getName()));
        } else {
            $this->addFlash('error', 'Le formulaire d\'ajout contient des erreurs.');
        }

        return $this->redirectToRoute('app_admin_index');
    }

    /**
     * Endpoint POST de modification d'un produit (redirection vers index en GET)
     */
    #[Route('/{id}/edit', name: 'app_admin_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        // Sécurité GET : redirection vers la page unique du back-office
        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('app_admin_index');
        }

        $product = $this->productRepository->find($id);
        if (!$product) {
            throw $this->createNotFoundException('Le produit demandé n\'existe pas.');
        }

        // Vérification du token CSRF unique associé à ce produit précis
        if (!$this->isCsrfTokenValid('edit_product_' . $product->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF de modification invalide.');
            return $this->redirectToRoute('app_admin_index');
        }

        // Récupération des données brutes saisies dans le formulaire en ligne du produit
        $name = $request->request->get('name');
        $price = $request->request->get('price');
        $isFeatured = $request->request->has('isFeatured');

        // Validation basique
        if (empty($name) || empty($price)) {
            $this->addFlash('error', 'Le nom et le prix du sweat-shirt ne peuvent pas être vides.');
            return $this->redirectToRoute('app_admin_index');
        }

        $product->setName($name);
        $product->setPrice((float) $price);
        $product->setIsFeatured($isFeatured);

        // --- GESTION DU TÉLÉVERSEMENT D'IMAGE DE REMPLACEMENT ---
        /** @var UploadedFile $imageFile */
        $imageFile = $request->files->get('imageFile');

        if ($imageFile) {
            $imagesDirectory = $this->getParameter('kernel.project_dir') . '/public/images';
            $newFilename = uniqid() . '.' . $imageFile->guessExtension();

            try {
                // Suppression de l'ancienne image si elle existe
                $oldImage = $product->getImage();
                if ($oldImage) {
                    $oldImagePath = $imagesDirectory . '/' . $oldImage;
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                $imageFile->move($imagesDirectory, $newFilename);
                $product->setImage($newFilename);
            } catch (FileException $e) {
                $this->addFlash('error', 'Une erreur est survenue lors du téléversement de l\'image.');
                return $this->redirectToRoute('app_admin_index');
            }
        }

        // --- MISE À JOUR EN DIRECT DES STOCKS PAR TAILLE ---
        $sizes = ['XS', 'S', 'M', 'L', 'XL'];
        foreach ($sizes as $size) {
            $newQuantity = (int) $request->request->get('stock_' . $size, 0);

            $stockFound = false;
            foreach ($product->getStocks() as $stock) {
                if ($stock->getSize() === $size) {
                    $stock->setQuantity($newQuantity);
                    $this->entityManager->persist($stock);
                    $stockFound = true;
                    break;
                }
            }

            if (!$stockFound) {
                $stock = new ProductStock();
                $stock->setProduct($product);
                $stock->setSize($size);
                $stock->setQuantity($newQuantity);
                $this->entityManager->persist($stock);
            }
        }

        $this->entityManager->flush();
        $this->addFlash('success', sprintf('Le sweat-shirt %s a été mis à jour avec succès.', $product->getName()));

        return $this->redirectToRoute('app_admin_index');
    }

    /**
     * Suppression d'un produit
     */
    #[Route('/{id}/delete', name: 'app_admin_delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            throw $this->createNotFoundException('Le produit à supprimer n\'existe pas.');
        }

        if ($this->isCsrfTokenValid('delete' . $product->getId(), $request->request->get('_token'))) {
            $imageFilename = $product->getImage();
            if ($imageFilename) {
                $imagePath = $this->getParameter('kernel.project_dir') . '/public/images/' . $imageFilename;
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            $this->entityManager->remove($product);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Le sweat-shirt %s a été supprimé avec succès.', $product->getName()));
        } else {
            $this->addFlash('error', 'Jeton CSRF de suppression invalide.');
        }

        return $this->redirectToRoute('app_admin_index');
    }
}
