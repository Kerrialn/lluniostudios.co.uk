<?php

namespace App\Form;

use App\Entity\Product;
use App\Form\Type\IntegerType;
use App\Model\CartItemFormModel;
use Nette\Utils\Strings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CartItemForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $formBuilder, array $options): void
    {
        /**
         * @var Product $product
         */
        $product = $options['product'];

        $formBuilder
            ->add('quantity', IntegerType::class, [
                'label' => false,
            ]);

        foreach ($product->getProductOptions() as $productOption) {

            $choices = [];
            foreach ($productOption->getProductOptionValues() as $val) {
                $choices[$val->getValue()] = $val->getId();
            }

            $formBuilder->add(Strings::webalize($productOption->getName()), ChoiceType::class, [
                'row_attr' => [
                    'class' => 'form-floating',
                ],
                'choices' => $choices,
                'mapped' => false,
                'label' => $productOption->getName(),
                'placeholder' => sprintf('Select %s…', $productOption->getName()),
                'required' => true,
                'autocomplete' => true,
            ]);
        }

    }

    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setDefaults([
            'data_class' => CartItemFormModel::class,
        ]);
        $optionsResolver
            ->setRequired('product')
            ->setAllowedTypes('product', Product::class);
    }
}
