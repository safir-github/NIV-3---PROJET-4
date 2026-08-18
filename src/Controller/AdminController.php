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
 * - Affichage de la liste complète des sweat-shirts.
 * - Création d'un sweat-shirt et de ses stocks initiaux.
 * - Edition d'un sweat-shirt (prix, image, isFeatured, et stocks par taille).
 * - Suppression d'un sweat-shirt (et suppression en cascade des stocks associés).
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

    #[Route('/', name: 'app_admin_index', methods: ['GET'])]
    public function index(): Response
    {
        // Récupération de tous les produits pour affichage dans le tableau d'administration
        $products = $this->productRepository->findAll();

        return $this->render('admin/index.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/new', name: 'app_admin_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $product = new Product();
        
        // Création du formulaire de création de produit
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // --- GESTION DU TÉLÉVERSEMENT DE L'IMAGE ---
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $imagesDirectory = $this->getParameter('kernel.project_dir') . '/public/images';
                // Génération d'un nom de fichier unique sécurisé
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move($imagesDirectory, $newFilename);
                    $product->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors du téléversement de l\'image.');
                    return $this->render('admin/new.html.twig', [
                        'form' => $form,
                    ]);
                }
            }

            // --- CRÉATION EN BASE DU PRODUIT ---
            $this->entityManager->persist($product);

            // --- CRÉATION ET ASSIGNATION DES STOCKS PAR TAILLE ---
            $sizes = ['XS', 'S', 'M', 'L', 'XL'];
            foreach ($sizes as $size) {
                // Récupération de la valeur saisie pour chaque taille
                $quantity = $form->get('stock_' . $size)->getData() ?? 0;

                $stock = new ProductStock();
                $stock->setProduct($product);
                $stock->setSize($size);
                $stock->setQuantity($quantity);

                $this->entityManager->persist($stock);
            }

            // Sauvegarde définitive en base de données
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Le sweat-shirt %s a été créé avec succès.', $product->getName()));

            return $this->redirectToRoute('app_admin_index');
        }

        return $this->render('admin/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        // Recherche du produit
        $product = $this->productRepository->find($id);
        if (!$product) {
            throw $this->createNotFoundException('Le produit demandé n\'existe pas.');
        }

        // Récupération des quantités de stocks actuelles en BDD pour les pré-charger dans le formulaire
        $stockData = [];
        foreach ($product->getStocks() as $stock) {
            $stockData['stock_' . $stock->getSize() . '_data'] = $stock->getQuantity();
        }

        // Création du formulaire d'édition
        $form = $this->createForm(ProductType::class, $product, $stockData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // --- GESTION DU TÉLÉVERSEMENT DE L'IMAGE (Facultative en édition) ---
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $imagesDirectory = $this->getParameter('kernel.project_dir') . '/public/images';
                // Génération d'un nom unique
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();

                try {
                    // Suppression de l'ancienne image locale si elle existe
                    $oldImage = $product->getImage();
                    if ($oldImage) {
                        $oldImagePath = $imagesDirectory . '/' . $oldImage;
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }

                    // Déplacement du nouveau fichier
                    $imageFile->move($imagesDirectory, $newFilename);
                    $product->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors du téléversement de l\'image.');
                    return $this->render('admin/edit.html.twig', [
                        'form' => $form,
                        'product' => $product,
                    ]);
                }
            }

            // --- MISE À JOUR DES STOCKS EXISTANTS PAR TAILLE ---
            $sizes = ['XS', 'S', 'M', 'L', 'XL'];
            foreach ($sizes as $size) {
                $newQuantity = $form->get('stock_' . $size)->getData() ?? 0;

                // Recherche et mise à jour de la ligne de stock existante
                $stockFound = false;
                foreach ($product->getStocks() as $stock) {
                    if ($stock->getSize() === $size) {
                        $stock->setQuantity($newQuantity);
                        $this->entityManager->persist($stock);
                        $stockFound = true;
                        break;
                    }
                }

                // Cas de secours : si jamais une ligne de stock était manquante en BDD, on la crée
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

        return $this->render('admin/edit.html.twig', [
            'form' => $form,
            'product' => $product,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            throw $this->createNotFoundException('Le produit à supprimer n\'existe pas.');
        }

        // Protection contre les failles CSRF
        if ($this->isCsrfTokenValid('delete' . $product->getId(), $request->request->get('_token'))) {
            // Suppression de l'image locale associée pour libérer l'espace disque
            $imageFilename = $product->getImage();
            if ($imageFilename) {
                $imagePath = $this->getParameter('kernel.project_dir') . '/public/images/' . $imageFilename;
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            // La suppression du produit va automatiquement supprimer les stocks liés en cascade (Cascade remove)
            $this->entityManager->remove($product);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Le sweat-shirt %s a été supprimé avec succès.', $product->getName()));
        } else {
            $this->addFlash('error', 'Jeton CSRF de suppression invalide.');
        }

        return $this->redirectToRoute('app_admin_index');
    }
}
