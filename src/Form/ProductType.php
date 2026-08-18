<?php

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

/**
 * Formulaire de gestion de produit (ProductType)
 * 
 * Formulaire utilisé pour ajouter et modifier un sweat-shirt.
 * Il contient les propriétés de base du produit (nom, prix, vedette, image)
 * et ajoute 5 champs entiers non mappés pour gérer les stocks par taille.
 */
class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Détermine si on est en mode édition (le produit a déjà un ID)
        $isEdit = $options['data'] && $options['data']->getId() !== null;

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du sweat-shirt',
                'constraints' => [
                    new NotBlank(message: 'Veuillez renseigner le nom du produit.'),
                ],
            ])
            ->add('price', NumberType::class, [
                'label' => 'Prix (en €)',
                'scale' => 2,
                'constraints' => [
                    new NotBlank(message: 'Veuillez renseigner le prix.'),
                    new PositiveOrZero(message: 'Le prix doit être positif.'),
                ],
            ])
            ->add('isFeatured', CheckboxType::class, [
                'label' => 'Mettre en avant sur la page d\'accueil',
                'required' => false,
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image du sweat-shirt (format JPG, PNG ou WEBP)',
                'mapped' => false, // Géré manuellement dans le contrôleur pour l'upload
                'required' => !$isEdit, // Obligatoire uniquement à la création
                'constraints' => !$isEdit ? [
                    new NotBlank(message: 'Veuillez téléverser une image pour ce produit.'),
                    new File(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Veuillez téléverser une image valide (JPG, PNG, ou WEBP).'
                    )
                ] : [
                    new File(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Veuillez téléverser une image valide (JPG, PNG, ou WEBP).'
                    )
                ],
            ])
            
            // --- CHAMPS NON MAPPÉS POUR LES QUANTITÉS EN STOCK PAR TAILLE ---
            ->add('stock_XS', IntegerType::class, [
                'label' => 'Stock XS',
                'mapped' => false,
                'required' => true,
                'data' => $options['stock_XS_data'] ?? 0,
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir le stock pour XS.'),
                    new PositiveOrZero(message: 'Le stock doit être positif ou nul.'),
                ],
            ])
            ->add('stock_S', IntegerType::class, [
                'label' => 'Stock S',
                'mapped' => false,
                'required' => true,
                'data' => $options['stock_S_data'] ?? 0,
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir le stock pour S.'),
                    new PositiveOrZero(message: 'Le stock doit être positif ou nul.'),
                ],
            ])
            ->add('stock_M', IntegerType::class, [
                'label' => 'Stock M',
                'mapped' => false,
                'required' => true,
                'data' => $options['stock_M_data'] ?? 0,
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir le stock pour M.'),
                    new PositiveOrZero(message: 'Le stock doit être positif ou nul.'),
                ],
            ])
            ->add('stock_L', IntegerType::class, [
                'label' => 'Stock L',
                'mapped' => false,
                'required' => true,
                'data' => $options['stock_L_data'] ?? 0,
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir le stock pour L.'),
                    new PositiveOrZero(message: 'Le stock doit être positif ou nul.'),
                ],
            ])
            ->add('stock_XL', IntegerType::class, [
                'label' => 'Stock XL',
                'mapped' => false,
                'required' => true,
                'data' => $options['stock_XL_data'] ?? 0,
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir le stock pour XL.'),
                    new PositiveOrZero(message: 'Le stock doit être positif ou nul.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
            // Options personnalisées pour passer les valeurs des stocks au formulaire en édition
            'stock_XS_data' => 0,
            'stock_S_data' => 0,
            'stock_M_data' => 0,
            'stock_L_data' => 0,
            'stock_XL_data' => 0,
        ]);
    }
}
