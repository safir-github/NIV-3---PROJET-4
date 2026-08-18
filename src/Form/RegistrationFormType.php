<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Formulaire d'inscription (RegistrationFormType)
 * 
 * Cette classe définit la structure et les règles de validation du formulaire d'inscription.
 * Elle mappe par défaut les données saisies vers l'entité User, à l'exception du mot de passe en clair
 * et des conditions d'utilisation qui sont gérés manuellement.
 */
class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Champ Email avec validation basique de présence
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez saisir une adresse email.'
                    ),
                ],
            ])
            // Champ Nom complet (name) - requis par le brief
            ->add('name', TextType::class, [
                'label' => 'Nom complet',
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez saisir votre nom complet.'
                    ),
                ],
            ])
            // Champ Adresse de livraison (deliveryAddress) - requis par le brief
            ->add('deliveryAddress', TextType::class, [
                'label' => 'Adresse de livraison',
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez saisir votre adresse de livraison.'
                    ),
                ],
            ])
            // Case à cocher pour accepter les conditions d'utilisation
            ->add('agreeTerms', CheckboxType::class, [
                'label' => "J'accepte les conditions d'utilisation",
                'mapped' => false,
                'constraints' => [
                    new IsTrue(
                        message: "Vous devez accepter nos conditions d'utilisation pour vous inscrire."
                    ),
                ],
            ])
            // Champ Mot de passe
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Mot de passe',
                // mapped => false indique que ce champ n'est pas directement enregistré tel quel en BDD.
                // Le mot de passe en clair sera récupéré et haché dans le contrôleur.
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez saisir un mot de passe.'
                    ),
                    new Length(
                        min: 6,
                        minMessage: 'Votre mot de passe doit faire au moins {{ limit }} caractères.',
                        // longueur maximale autorisée pour des raisons de sécurité
                        max: 4096
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
