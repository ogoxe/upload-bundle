<?php

declare(strict_types=1);

namespace Pentatrion\UploadBundle\Form;

use Override;
use Pentatrion\UploadBundle\Service\FileManagerHelperInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class EntityFilePickerType extends AbstractType
{
    public function __construct(
        private readonly FileManagerHelperInterface $fileManagerHelper,
        private readonly NormalizerInterface $normalizer
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            fn($uploadedFile) => $uploadedFile,
            function ($uploadedFile) {
                if (!$uploadedFile || $uploadedFile->isEmpty()) {
                    return null;
                }

                return $uploadedFile;
            }
        ));
    }

    /**
     * @throws ExceptionInterface
     */
    #[Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $value = $form->getData();
        $fileManagerConfig = $this->fileManagerHelper->completeConfig($options['fileManagerConfig']);
        $fileManagerConfig['multiple'] = false;

        $view->vars['attr']['data-name'] = $view->vars['full_name'];
        $view->vars['attr']['data-minifilemanager'] = json_encode($fileManagerConfig);
        $view->vars['attr']['data-entity-form-file-picker'] = 'true';

        /* @deprecated */
        $view->vars['attr']['data-uploaded-files'] = json_encode([$this->normalizer->normalize($value)]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('fileManagerConfig');
        $resolver->setDefault('delete_empty', true);
    }

    #[Override]
    public function getParent(): ?string
    {
        return UploadedFileType::class;
    }
}
